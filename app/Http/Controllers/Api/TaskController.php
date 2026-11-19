<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Http\Resources\DashboardTaskResource;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\Task;
use App\Services\NotificationService;
use App\Services\TaskLifecycle\TaskLifecycleEngine;
use App\Services\TaskLifecycle\TaskRevisionChain;
use App\Services\TaskLifecycle\TaskStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * HTTP layer for tasks. Every action acts as the authenticated user
 * ($request->user()); status changes are delegated to TaskLifecycleEngine.
 */
class TaskController extends Controller
{
    public function __construct(
        private readonly TaskLifecycleEngine $lifecycle,
        private readonly TaskRevisionChain $revisions,
    ) {}

    /** Admin only — every task across all projects. */
    public function index(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403, 'Only admins can list all tasks.');

        return Task::all();
    }

    public function show(Task $task)
    {
        $this->authorize('view', $task);

        return $task;
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $project = Project::findOrFail($validated['project_id']);
        $notifyAll = (bool) ($validated['notify_all'] ?? false);
        unset($validated['notify_all']);

        $validated['requires_submission'] ??= $project->defaultRequiresSubmission();
        $validated['status'] = TaskStatus::TODO;

        $task = Task::create($validated);

        if ($task->assigned_to) {
            $this->notifyAssigned($task, $project, $request->user()->id, $notifyAll);
        }

        return response()->json($task, 201);
    }

    public function update(UpdateTaskRequest $request, Task $task): JsonResponse
    {
        $result = $this->lifecycle->update(
            $task,
            $request->user(),
            $request->changes(),
            $request->revisionOverrides(),
        );

        return response()->json($result);
    }

    /** Lead or admin — soft deletes the task. */
    public function destroy(Task $task): JsonResponse
    {
        $this->authorize('delete', $task);

        $task->delete();

        return response()->json(null, 204);
    }

    /**
     * Bulk-updates sort_order for tasks within a column.
     * Expects: { ordered_ids: [1, 3, 2, 5] }
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ordered_ids' => 'required|array',
            'ordered_ids.*' => 'integer|exists:tasks,id',
        ]);

        $projects = Project::whereIn('id', Task::whereIn('id', $validated['ordered_ids'])->select('project_id'))->get();

        foreach ($projects as $project) {
            abort_unless(
                $request->user()->can('manageTasks', $project),
                403,
                'Only the project lead or an admin can reorder tasks.'
            );
        }

        DB::transaction(function () use ($validated) {
            foreach ($validated['ordered_ids'] as $index => $id) {
                Task::where('id', $id)->update(['sort_order' => $index]);
            }
        });

        return response()->json(['message' => 'Reordered.']);
    }

    /** The revision chain behind a task, newest first. */
    public function history(Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        $chain = $this->revisions->ancestorsOf($task)->each->setRelation('project', $task->project);

        return response()->json(['history' => TaskResource::collection($chain)]);
    }

    /**
     * "Prioritized Tasks" dashboard widget — the current user's open tasks
     * across every non-system project.
     */
    public function mine(Request $request)
    {
        $tasks = Task::with(['project', 'assignee'])
            ->where('assigned_to', $request->user()->id)
            ->whereHas('project', fn ($q) => $q->where('is_system', false))
            ->whereNotIn('status', TaskStatus::FINISHED)
            ->orderBy('due_date')
            ->get();

        return DashboardTaskResource::collection($tasks);
    }

    /**
     * System project dashboard widget. Admins see every task in it; everyone
     * else sees only tasks assigned to them plus unassigned ones.
     */
    public function systemTasks(Request $request)
    {
        $user = $request->user();
        $systemProject = Project::main() ?? abort(404, 'System project not found.');

        $tasks = Task::with(['project', 'assignee'])
            ->where('project_id', $systemProject->id)
            ->when(!$user->isAdmin(), fn ($q) => $q->where(
                fn ($q) => $q->where('assigned_to', $user->id)->orWhereNull('assigned_to')
            ))
            ->orderBy('due_date')
            ->get();

        return DashboardTaskResource::collection($tasks);
    }

    private function notifyAssigned(Task $task, Project $project, int $createdBy, bool $notifyAll): void
    {
        NotificationService::send(
            projectId: $project->id,
            type: 'task_assigned',
            title: $project->is_system
                ? "New General Task: {$task->title}"
                : "New {$project->name} Task: {$task->title}",
            body: null,
            actionUrl: $project->is_system
                ? "/dashboard?task={$task->id}"
                : "/projects/{$project->id}?task={$task->id}",
            createdBy: $createdBy,
            notifyAll: $notifyAll,
            targetUserId: $task->assigned_to,
        );
    }
}
