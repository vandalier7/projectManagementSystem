<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

/**
 * Who can do what to a project. Auto-discovered by Laravel and used via
 * `$this->authorize('ability', $project)` in controllers or `$user->can(...)`.
 *
 * Roles:
 *  - Admin: everything.
 *  - Project lead: authority over the project's tasks, members and branding,
 *    but not the project record itself (name, dates, lead) — that's admin-only.
 *  - Member: can view the project.
 */
class ProjectPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function view(User $user, Project $project): bool
    {
        return $project->hasMember($user);
    }

    /** Create tasks, reorder the board. */
    public function manageTasks(User $user, Project $project): bool
    {
        return $project->isLedBy($user);
    }

    public function manageMembers(User $user, Project $project): bool
    {
        return $project->isLedBy($user);
    }

    /** Logo, banner and theme colour. */
    public function updateBranding(User $user, Project $project): bool
    {
        return $project->isLedBy($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Project $project): bool
    {
        return false;
    }

    public function delete(User $user, Project $project): bool
    {
        return false;
    }
}
