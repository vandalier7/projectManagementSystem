<?php

namespace App\Services\TaskLifecycle;

use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Task Lifecycle Engine — the custom-built component that enforces task state
 * transitions, revision chains and role-based permissions.
 *
 * Every status change goes through transition(). It runs inside a transaction
 * with a row lock so two people acting on the same task at once (e.g. a lead
 * reviewing while the member takes the submission back) can't interleave.
 *
 * Controllers stay thin: they validate input, call the engine, and return the
 * result. Rejections are thrown as TaskLifecycleException, which renders itself
 * as a JSON error response.
 */
class TaskLifecycleEngine
{
    /** Fields only the project lead or an admin may change directly. */
    private const DETAIL_FIELDS = ['title', 'description', 'assigned_to', 'priority', 'due_date'];

    /**
     * Apply an update to a task: an optional status transition plus optional
     * detail edits.
     *
     * @param  array  $changes   Validated input (status, detail fields, submission/review fields)
     * @param  array  $revision  Overrides for the revision task created on rejection
     *                           (new_title, new_description, new_assigned_to, new_priority, new_due_date)
     * @return Task|array{closed_task: Task, revision_task: Task}
     */
    public function update(Task $task, User $actor, array $changes, array $revision = []): Task|array
    {
        return DB::transaction(function () use ($task, $actor, $changes, $revision) {
            $task = Task::lockForUpdate()->findOrFail($task->id);

            if (array_key_exists('status', $changes)) {
                $result = $this->transition($task, $actor, $changes['status'], $changes, $revision);

                // A rejection closes the task and spawns a revision — nothing
                // else in this request applies to the closed task.
                if (is_array($result)) {
                    return $result;
                }
            }

            $details = array_intersect_key($changes, array_flip(self::DETAIL_FIELDS));

            if (!empty($details) && !$task->canBeEditedBy($actor)) {
                throw TaskLifecycleException::forbidden('Only the project lead or an admin can edit task details.');
            }

            // Submission and review fields are only ever set by the
            // transitions above — never mass-assigned from the request.
            $task->fill(array_intersect_key($changes, array_flip([...self::DETAIL_FIELDS, 'status'])));
            $task->save();

            return $task;
        });
    }

    /**
     * Validate and apply a single status transition to a locked task.
     * Mutates $task in place; returns an array only when a rejection produced
     * a revision task.
     */
    private function transition(Task $task, User $actor, string $to, array $changes, array $revision): Task|array
    {
        $from = $task->status;

        return match (true) {
            $from === TaskStatus::SUBMITTED && $to === TaskStatus::DONE
                => $this->approve($task, $actor),

            $from === TaskStatus::SUBMITTED && $to === TaskStatus::CLOSED
                => $this->reject($task, $actor, $changes['review_comment'] ?? null, $revision),

            $from === TaskStatus::SUBMITTED && $to === TaskStatus::IN_PROGRESS
                => $this->takeBack($task, $actor),

            $from === TaskStatus::TODO && $to === TaskStatus::IN_PROGRESS
                => $this->start($task, $actor),

            $to === TaskStatus::DONE && is_null($task->assigned_to) && $task->project->is_system
                => $this->completeUnassignedSystemTask($task, $actor),

            $to === TaskStatus::DONE && $task->canBeSelfCompletedBy($actor)
                => $this->selfComplete($task),

            $from === TaskStatus::IN_PROGRESS && $to === TaskStatus::SUBMITTED
                => $this->submit($task, $actor, $changes),

            default => throw TaskLifecycleException::forbidden(
                "The transition from '{$from}' to '{$to}' is not allowed."
            ),
        };
    }

    // ── Review ──────────────────────────────────────────────────────────

    private function approve(Task $task, User $actor): Task
    {
        $this->ensureCanReview($task, $actor);

        $task->reviewed_by = $actor->id;
        $task->reviewed_at = now();
        $task->completed_date = now();

        return $task;
    }

    /**
     * Rejecting a submission closes the task and creates a fresh revision
     * linked back to it via previous_task_id, so the full history is kept.
     */
    private function reject(Task $task, User $actor, ?string $comment, array $revision): array
    {
        $this->ensureCanReview($task, $actor);

        $task->review_comment = $comment ?? 'No comment provided.';
        $task->reviewed_by = $actor->id;
        $task->reviewed_at = now();
        $task->status = TaskStatus::CLOSED;
        $task->save();

        $revisionTask = Task::create([
            'title' => $revision['new_title'] ?? $task->title,
            'description' => $revision['new_description'] ?? $task->description,
            'project_id' => $task->project_id,
            'assigned_to' => $revision['new_assigned_to'] ?? $task->assigned_to,
            'priority' => $revision['new_priority'] ?? $task->priority,
            'requires_submission' => true,
            'status' => TaskStatus::TODO,
            'previous_task_id' => $task->id,
            'due_date' => $revision['new_due_date'] ?? null,
        ]);

        return ['closed_task' => $task, 'revision_task' => $revisionTask];
    }

    private function ensureCanReview(Task $task, User $actor): void
    {
        if (!$task->canBeReviewedBy($actor)) {
            throw TaskLifecycleException::forbidden('Only the project lead or an admin can review a submission.');
        }
    }

    // ── Member actions ──────────────────────────────────────────────────

    private function takeBack(Task $task, User $actor): Task
    {
        if (!$task->canBeSubmittedBy($actor)) {
            throw TaskLifecycleException::forbidden('Only the assigned member can take back this submission.');
        }

        $task->submission_link = null;
        $task->submission_notes = null;
        $task->submitted_at = null;

        return $task;
    }

    /**
     * Starting an unassigned task claims it. If the claimer is the lead or an
     * admin, the task no longer needs submission — they'd be reviewing their
     * own work.
     */
    private function start(Task $task, User $actor): Task
    {
        $isUnassigned = is_null($task->assigned_to);

        if (!$isUnassigned && !$task->isAssignedTo($actor) && !$task->canBeEditedBy($actor)) {
            throw TaskLifecycleException::forbidden('Only the assignee, lead, or admin can start this task.');
        }

        if ($isUnassigned) {
            $task->assigned_to = $actor->id;
        }

        if ($task->canBeEditedBy($actor)) {
            $task->requires_submission = false;
        }

        return $task;
    }

    private function submit(Task $task, User $actor, array $changes): Task
    {
        if (!$task->canBeSubmittedBy($actor)) {
            throw TaskLifecycleException::forbidden('Only the assigned member can submit this task.');
        }

        if (blank($changes['submission_link'] ?? null)) {
            throw TaskLifecycleException::invalid('A submission link is required to submit this task.');
        }

        $task->submission_link = $changes['submission_link'];
        $task->submission_notes = $changes['submission_notes'] ?? null;
        $task->submitted_at = now();

        return $task;
    }

    // ── Direct completion ───────────────────────────────────────────────

    /** Anyone can pick up and finish an unassigned task in the system project. */
    private function completeUnassignedSystemTask(Task $task, User $actor): Task
    {
        $task->assigned_to = $actor->id;
        $task->completed_date = now();

        return $task;
    }

    private function selfComplete(Task $task): Task
    {
        $task->completed_date = now();

        return $task;
    }
}
