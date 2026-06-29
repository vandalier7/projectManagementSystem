<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectMember;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
	## Admin sees all projects.
	## Members see only projects they belong to via project_members.
	public function index(Request $request)
	{
		$user = $request->user();

		if ($user->isAdmin()) {
            return Project::with('lead')->get();
        }

        return Project::with('lead')->whereHas('members', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->get();
	}

	## Admin sees any project.
	## Members must have a row in project_members for this project.
	public function show(Request $request, Project $project)
	{
		$user = $request->user();

		if (!$user->isAdmin()) {
			$isMember = $project->members()->where('user_id', $user->id)->exists();

			if (!$isMember) {
				return response()->json(['message' => 'You do not have access to this project.'], 403);
			}
		}

		return $project->load(['lead', 'tasks.assignee', 'tasks.previousTask', 'members.user']);
	}

	## Admin only — creates a new project.
	public function store(Request $request)
	{
		if (!$request->user()->isAdmin()) {
			return response()->json(['message' => 'Only admins can create projects.'], 403);
		}

		$validated = $request->validate([
			'name' => 'required|string|max:255',
			'description' => 'nullable|string|max:255',
			'status' => 'nullable|string|max:255',
			'start_date' => 'nullable|date',
			'end_date' => 'nullable|date|after_or_equal:start_date',
			'budget' => 'nullable|numeric|min:0',
			'spent' => 'nullable|numeric|min:0',
			'lead_id' => 'required|exists:users,id',
			'created_by' => 'required|exists:users,id',
			'task_default_submission_mode' => 'nullable|in:require,no_require,match_last',
		]);

		$project = Project::create($validated);

		// Auto-add the lead as a project member on creation.
		ProjectMember::firstOrCreate([
			'project_id' => $project->id,
			'user_id' => $project->lead_id,
		]);

		return response()->json($project, 201);
	}

	## Admin only — updates a project, including reassigning the lead.
	## The project's lead has no special authority over the project record
	## itself — their authority is scoped to tasks within it, not the
	## project's own fields. This is a deliberate, reviewed decision.
	public function update(Request $request, Project $project)
	{
		if (!$request->user()->isAdmin()) {
			return response()->json(['message' => 'Only an admin can edit this project.'], 403);
		}

		$validated = $request->validate([
			'name' => 'sometimes|string|max:255',
			'description' => 'nullable|string|max:255',
			'status' => 'sometimes|string|max:255',
			'start_date' => 'nullable|date',
			'end_date' => 'nullable|date|after_or_equal:start_date',
			'budget' => 'nullable|numeric|min:0',
			'spent' => 'nullable|numeric|min:0',
			'lead_id' => 'sometimes|exists:users,id',
			'task_default_submission_mode' => 'sometimes|in:require,no_require,match_last',
		]);

		$project->update($validated);

		return response()->json($project);
	}

	## Admin only — deletes a project.
	public function destroy(Request $request, Project $project)
	{
		if (!$request->user()->isAdmin()) {
			return response()->json(['message' => 'Only admins can delete projects.'], 403);
		}

		$project->delete();

		return response()->json(null, 204);
	}
}