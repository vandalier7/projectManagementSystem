<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;

class TaskController extends Controller
{
    public function index()
    {
        return Task::all();
    }

    // Cross-project view for the dashboard's "Prioritized Tasks" widget —
    // every task assigned to `user`, regardless of which project it's in,
    // shaped to match what DashboardTaskModal expects (nested `project`,
    // `project_lead_id` per task since each row can belong to a different
    // project's lead).
    public function mine(Request $request)
    {
        $validated = $request->validate([
            'user' => 'required|exists:users,id',
        ]);

        $tasks = Task::with([
                'project:id,name,lead_id',
                'assignee:id,full_name,deleted_at',
            ])
            ->where('assigned_to', $validated['user'])
            ->whereHas('project', fn ($q) => $q->where('is_system', false))
            ->whereNotIn('status', ['done', 'closed'])
            ->orderBy('due_date')
            ->get()
            ->map(function (Task $task) use ($validated) {
                $actingUser = User::find($validated['user']);
                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'description' => $task->description,
                    'status' => $task->status,
                    'priority' => $task->priority,
                    'requires_submission' => $task->requires_submission,
                    'sort_order' => $task->sort_order,
                    'due_date' => $task->due_date,
                    'assignee' => $task->assignee
                        ? ['id' => $task->assignee->id, 'full_name' => $task->assignee->full_name]
                        : null,
                    'submission_link' => $task->submission_link,
                    'submission_notes' => $task->submission_notes,
                    'submitted_at' => $task->submitted_at,
                    'review_comment' => $task->review_comment,
                    'reviewed_at' => $task->reviewed_at,
                    'completed_date' => $task->completed_date,
                    // The widget only shows live/open tasks, so the revision
                    // chain (previous_task) isn't rendered here — left null
                    // to keep the payload light. TaskModal-side history nav
                    // only matters once you're already inside a task.
                    'previous_task' => null,
                    'project' => $task->project ? [
                        'id' => $task->project->id,
                        'name' => $task->project->name,
                    ] : null,
                    'project_lead_id' => $task->project?->lead_id,
                ];
            });

        return response()->json($tasks);
    }

    public function history(Request $request, Task $task)
    {
        $validated = $request->validate([
            'user' => 'required|exists:users,id',
        ]);

        $actingUser = User::findOrFail($validated['user']);

        if ($task->assigned_to !== (int) $validated['user']) {
            return response()->json(['message' => 'You do not have access to this task.'], 403);
        }

        $task->load([
            'previousTask.assignee',
            'previousTask.previousTask.assignee',
            'previousTask.previousTask.previousTask.assignee',
            'previousTask.previousTask.previousTask.previousTask.assignee',
            'previousTask.previousTask.previousTask.previousTask.previousTask.assignee',
            'previousTask.previousTask.previousTask.previousTask.previousTask.previousTask.assignee',
            'previousTask.previousTask.previousTask.previousTask.previousTask.previousTask.previousTask.assignee',
            'previousTask.previousTask.previousTask.previousTask.previousTask.previousTask.previousTask.previousTask.assignee',
            'previousTask.previousTask.previousTask.previousTask.previousTask.previousTask.previousTask.previousTask.previousTask.assignee',
            'previousTask.previousTask.previousTask.previousTask.previousTask.previousTask.previousTask.previousTask.previousTask.previousTask.assignee',
        ]);

        $chain = [];
        $cursor = $task->previousTask;

        while ($cursor) {
            $chain[] = [
                'id' => $cursor->id,
                'title' => $cursor->title,
                'description' => $cursor->description,
                'status' => $cursor->status,
                'priority' => $cursor->priority,
                'requires_submission' => $cursor->requires_submission,
                'sort_order' => $cursor->sort_order,
                'due_date' => $cursor->due_date,
                'assignee' => $this->resolveAssigneeName($cursor, $actingUser),
                'submission_link' => $cursor->submission_link,
                'submission_notes' => $cursor->submission_notes,
                'submitted_at' => $cursor->submitted_at,
                'review_comment' => $cursor->review_comment,
                'reviewed_at' => $cursor->reviewed_at,
                'completed_date' => $cursor->completed_date,
            ];
            $cursor = $cursor->previousTask;
        }

        return response()->json(['history' => $chain]);
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
            'notify_all' => 'nullable|boolean',
        ]);

        $project = Project::findOrFail($validated['project_id']);

        if (!array_key_exists('requires_submission', $validated) || $validated['requires_submission'] === null) {
            $validated['requires_submission'] = $this->resolveDefaultSubmissionMode($project);
        }

        // $validated['status'] = $project->is_system ? 'in_progress' : 'todo';
        $validated['status'] = 'todo';

        $notifyAll = (bool) ($validated['notify_all'] ?? false);
        unset($validated['notify_all']); // not a Task column, don't pass to create()

        $task = Task::create($validated);

       if ($task->assigned_to) {
            $isGeneral = $project->id === 1;

            NotificationService::send(
                projectId: $project->id,
                type: 'task_assigned',
                title: $isGeneral
                    ? "New General Task: {$task->title}"
                    : "New {$project->name} Task: {$task->title}",
                body: null,
                actionUrl: $isGeneral
                    ? "/dashboard?task={$task->id}"
                    : "/projects/{$project->id}?task={$task->id}",
                createdBy: $request->user()->id,
                notifyAll: $notifyAll,
                targetUserId: $task->assigned_to,
            );
        }

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
                    if (
                        !$task->isAssignedTo($actingUser) &&
                        !$task->canBeEditedBy($actingUser) &&
                        !is_null($task->assigned_to)
                    ) {
                        abort(response()->json(['message' => 'Only the assignee, lead, or admin can start this task.'], 403));
                    }

                    if (is_null($task->assigned_to)) {
                        $task->assigned_to = $actingUser->id;
                    }

                    // If the claimer is lead or admin, submission is not required.
                    if ($task->canBeEditedBy($actingUser)) {
                        $task->requires_submission = false;
                    }
                }

                elseif ($newStatus === 'done' && is_null($task->assigned_to) && $task->project->is_system) {
                    $task->assigned_to = $actingUser->id;
                    $task->completed_date = now();
                }

                elseif ($newStatus === 'done' && $task->canBeSelfCompletedBy($actingUser)) {
                    $task->completed_date = now();
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

        if (in_array($task->status, ['done', 'closed']) && !$actingUser->isAdmin()) {
            return response()->json(['message' => 'Only admins can delete completed or closed tasks.'], 403);
        }

        if (!$task->canBeEditedBy($actingUser)) {
            return response()->json(['message' => 'Only the project lead or an admin can delete tasks.'], 403);
        }

        $task->delete();

        return response()->json(null, 204);
    }

    ## Bulk-updates sort_order for tasks within a column.
    ## Expects: { ordered_ids: [1, 3, 2, 5] }
    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'ordered_ids' => 'required|array',
            'ordered_ids.*' => 'integer|exists:tasks,id',
        ]);

        foreach ($validated['ordered_ids'] as $index => $id) {
            Task::where('id', $id)->update(['sort_order' => $index]);
        }

        return response()->json(['message' => 'Reordered.']);
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

    // Dashboard widget for the system project — the only project every user
    // is a member of. Admins see every task in it; everyone else sees only
    // tasks assigned to them plus unassigned tasks (never other members' tasks).
    public function systemTasks(Request $request)
    {
        $validated = $request->validate([
            'user' => 'required|exists:users,id',
        ]);

        $user = User::findOrFail($validated['user']);
        $systemProject = Project::where('is_system', true)->firstOrFail();

        $query = Task::with([
                'project:id,name,lead_id',
                'assignee:id,full_name,deleted_at',
            ])
            ->where('project_id', $systemProject->id);

        if (!$user->isAdmin()) {
            $query->where(function ($q) use ($user) {
                $q->where('assigned_to', $user->id)
                ->orWhereNull('assigned_to');
            });
        }

        $tasks = $query
            ->orderBy('due_date')
            ->get()
            ->map(function (Task $task) use ($user) {
                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'description' => $task->description,
                    'status' => $task->status,
                    'priority' => $task->priority,
                    'requires_submission' => $task->requires_submission,
                    'sort_order' => $task->sort_order,
                    'due_date' => $task->due_date,
                    'assignee' => $this->resolveAssigneeName($task, $user),
                    'submission_link' => $task->submission_link,
                    'submission_notes' => $task->submission_notes,
                    'submitted_at' => $task->submitted_at,
                    'review_comment' => $task->review_comment,
                    'reviewed_at' => $task->reviewed_at,
                    'completed_date' => $task->completed_date,
                    'previous_task' => null,
                    'project' => $task->project ? [
                        'id' => $task->project->id,
                        'name' => $task->project->name,
                    ] : null,
                    'project_lead_id' => $task->project?->lead_id,
                ];
            });

        return response()->json($tasks);
    }

    private function resolveAssigneeName(Task $task, User $actingUser, ?Project $project = null): ?array
    {
        if (!$task->assignee) {
            return null;
        }

        $project ??= $task->project;
        $isDeleted = $task->assignee->trashed();
        $canSeeRealName = $actingUser->isAdmin()
            || !$isDeleted
            || ($project?->display_deleted_user_names ?? false);

        return [
            'id' => $task->assignee->id,
            'full_name' => $canSeeRealName ? $task->assignee->full_name : 'Deleted User',
        ];
    }
}
