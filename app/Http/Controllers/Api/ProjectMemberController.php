<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use Illuminate\Http\Request;

class ProjectMemberController extends Controller
{
    // List members of a project. Same access rule as viewing the project
    // itself — admin sees any, members must already belong to it.
    public function index(Request $request, Project $project)
    {
        $user = $request->user();

        if (!$user->isAdmin()) {
            $isMember = $project->members()->where('user_id', $user->id)->exists();
            if (!$isMember) {
                return response()->json(['message' => 'You do not have access to this project.'], 403);
            }
        }

        return $project->members()->with('user')->get();
    }

    // Admin or the project's lead can add a member.
    public function store(Request $request, Project $project)
    {
        if (!$this->canManageMembers($request->user(), $project)) {
            return response()->json(['message' => 'Only the project lead or an admin can add members.'], 403);
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $alreadyMember = $project->members()->where('user_id', $validated['user_id'])->exists();
        if ($alreadyMember) {
            return response()->json(['message' => 'This user is already a member of this project.'], 422);
        }

        $member = ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $validated['user_id'],
        ]);

        return response()->json($member->load('user'), 201);
    }

    // Admin or the project's lead can remove a member.
    // The project's lead cannot be removed this way — leadership change
    // is admin-only, via ProjectController@update, never a membership delete.
    public function destroy(Request $request, Project $project, User $user)
    {
        if (!$this->canManageMembers($request->user(), $project)) {
            return response()->json(['message' => 'Only the project lead or an admin can remove members.'], 403);
        }

        if ($project->isLedBy($user)) {
            return response()->json(['message' => 'The project lead cannot be removed as a member. Reassign the lead first.'], 422);
        }

        if ($project->is_system) {
            return response()->json(['message' => 'Members cannot be removed from a system project.'], 422);
        }

        $membership = $project->members()->where('user_id', $user->id)->first();

        if (!$membership) {
            return response()->json(['message' => 'This user is not a member of this project.'], 404);
        }

        $membership->delete();

        return response()->json(null, 204);
    }

    private function canManageMembers(User $actingUser, Project $project): bool
    {
        return $actingUser->isAdmin() || $project->isLedBy($actingUser);
    }
}