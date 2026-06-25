<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaskController extends Controller
{
    public function index()
    {
        return Task::all();
    }

    public function show(Task $task)
    {
        return $task;
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'project_id' => 'required|exists:projects,id',
            'assigned_to' => 'nullable|exists:users,id',
            'priority' => 'nullable|string|max:255',
            'requires_submission' => 'nullable|boolean',
            'due_date' => 'nullable|date',
        ]);

        $project = Project::findOrFail($validated['project_id']);

        if (!array_key_exists('requires_submission', $validated) || $validated['requires_submission'] === null) {
            $validated['requires_submission'] = $this->resolveDefaultSubmissionMode($project);
        }

        $validated['status'] = 'todo';

        $task = Task::create($validated);

        return response()->json($task, 201);
    }

    public function update(Request $request, Task $task)
    {
        $validated = $request->validate([
            'acting_as_user_id' => 'required|exists:users,id',
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:255',
            'assigned_to' => 'sometimes|nullable|exists:users,id',
            'priority' => 'sometimes|string|max:255',
            'status' => 'sometimes|in:todo,in_progress,submitted,done,closed',
            'submission_link' => 'sometimes|string|max:255',
            'submission_notes' => 'sometimes|nullable|string',
            'review_comment' => 'sometimes|nullable|string',
            'due_date' => 'nullable|date',
        ]);

        $actingUser = User::findOrFail($validated['acting_as_user_id']);

        $result = DB::transaction(function () use ($task, $validated, $request, $actingUser) {
            $task = Task::lockForUpdate()->findOrFail($task->id);

            if (array_key_exists('status', $validated)) {
                $newStatus = $validated['status'];
                $currentStatus = $task->status;

                if ($currentStatus === 'submitted' && in_array($newStatus, ['done', 'closed'])) {
                    if (!$task->canBeReviewedBy($actingUser)) {
                        abort(response()->json(['message' => 'Only the project lead or an admin can review a submission.'], 403));
                    }

                    if ($newStatus === 'done') {
                        $task->reviewed_by = $actingUser->id;
                        $task->reviewed_at = now();
                        $task->completed_date = now();
                    } else {
                        $task->review_comment = $validated['review_comment'] ?? 'No comment provided.';
                        $task->reviewed_by = $actingUser->id;
                        $task->reviewed_at = now();
                        $task->status = 'closed';
                        $task->save();

                        $revision = Task::create([
                            'title' => $request->input('new_title', $task->title),
                            'description' => $request->input('new_description', $task->description),
                            'project_id' => $task->project_id,
                            'assigned_to' => $request->input('new_assigned_to', $task->assigned_to),
                            'priority' => $task->priority,
                            'requires_submission' => true,
                            'status' => 'todo',
                            'previous_task_id' => $task->id,
                            'due_date' => $request->input('new_due_date'),
                        ]);

                        return ['closed_task' => $task, 'revision_task' => $revision];
                    }
                }

                elseif ($currentStatus === 'submitted' && $newStatus === 'in_progress') {
                    if (!$task->canBeSubmittedBy($actingUser)) {
                        abort(response()->json(['message' => 'Only the assigned member can take back this submission.'], 403));
                    }
                    $task->submission_link = null;
                    $task->submission_notes = null;
                    $task->submitted_at = null;
                }

                elseif ($currentStatus === 'todo' && $newStatus === 'in_progress') {
                    if (!$task->isAssignedTo($actingUser) && !$task->canBeEditedBy($actingUser)) {
                        abort(response()->json(['message' => 'Only the assignee, lead, or admin can start this task.'], 403));
                    }
                }

                elseif ($newStatus === 'done' && $task->canBeSelfCompletedBy($actingUser)) {
                    $task->completed_date = now();
                }

                elseif ($newStatus === 'submitted' && $currentStatus === 'in_progress') {
                    if (!$task->canBeSubmittedBy($actingUser)) {
                        abort(response()->json(['message' => 'Only the assigned member can submit this task.'], 403));
                    }
                    if (!$request->filled('submission_link')) {
                        abort(response()->json(['message' => 'A submission link is required to submit this task.'], 422));
                    }
                    $task->submission_link = $validated['submission_link'];
                    $task->submission_notes = $validated['submission_notes'] ?? null;
                    $task->submitted_at = now();
                }

                else {
                    abort(response()->json([
                        'message' => "The transition from '{$currentStatus}' to '{$newStatus}' is not allowed.",
                    ], 403));
                }
            }

            unset($validated['acting_as_user_id'], $validated['submission_link'], $validated['submission_notes'], $validated['review_comment']);

            $basicFields = array_intersect_key($validated, array_flip(['title', 'description', 'assigned_to', 'priority', 'due_date']));
            if (!empty($basicFields) && !$task->canBeEditedBy($actingUser)) {
                abort(response()->json(['message' => 'Only the project lead or an admin can edit task details.'], 403));
            }

            $task->fill($validated);
            $task->save();

            return $task;
        });

        return response()->json($result);
    }

    ## Admin or lead only — soft deletes the task.
    public function destroy(Request $request, Task $task)
    {
        $actingUser = User::findOrFail($request->input('acting_as_user_id'));

        if (!$task->canBeEditedBy($actingUser)) {
            return response()->json(['message' => 'Only the project lead or an admin can delete tasks.'], 403);
        }

        $task->delete();

        return response()->json(null, 204);
    }

    private function resolveDefaultSubmissionMode(Project $project): bool
    {
        return match ($project->task_default_submission_mode) {
            'require' => true,
            'no_require' => false,
            'match_last' => Task::where('project_id', $project->id)
                ->latest()
                ->first()
                ?->requires_submission ?? true,
            default => true,
        };
    }
}