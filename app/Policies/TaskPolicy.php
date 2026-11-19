<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use App\Services\TaskLifecycle\TaskStatus;
use Illuminate\Auth\Access\Response;

/**
 * Coarse task permissions. Status transitions have their own, finer rules and
 * are enforced by TaskLifecycleEngine.
 */
class TaskPolicy
{
    /** The assignee, or anyone who can see the task's project. */
    public function view(User $user, Task $task): bool
    {
        return $task->isAssignedTo($user) || $user->can('view', $task->project);
    }

    public function delete(User $user, Task $task): Response
    {
        if (in_array($task->status, TaskStatus::FINISHED, true) && !$user->isAdmin()) {
            return Response::deny('Only admins can delete completed or closed tasks.');
        }

        return $task->canBeEditedBy($user)
            ? Response::allow()
            : Response::deny('Only the project lead or an admin can delete tasks.');
    }
}
