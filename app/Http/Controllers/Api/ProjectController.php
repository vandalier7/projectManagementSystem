<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Jobs\ExtractProjectThemeColor;

class ProjectController extends Controller
{
	## Admin sees all projects.
	## Members see only projects they belong to via project_members.
	public function index(Request $request)
	{
		$user = $request->user();

		if ($user->isAdmin()) {
			return Project::visible()->with('lead')->get();
		}

		return Project::visible()->with('lead')->whereHas('members', function ($query) use ($user) {
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

		return $project->load([
			'lead',
			'tasks.assignee',
			'tasks.previousTask.assignee',
			'tasks.previousTask.previousTask.assignee',
			'tasks.previousTask.previousTask.previousTask.assignee',
			'tasks.previousTask.previousTask.previousTask.previousTask.assignee',
			'tasks.previousTask.previousTask.previousTask.previousTask.previousTask.assignee',
			'tasks.previousTask.previousTask.previousTask.previousTask.previousTask.previousTask.assignee',
			'tasks.previousTask.previousTask.previousTask.previousTask.previousTask.previousTask.previousTask.assignee',
			'tasks.previousTask.previousTask.previousTask.previousTask.previousTask.previousTask.previousTask.previousTask.assignee',
			'tasks.previousTask.previousTask.previousTask.previousTask.previousTask.previousTask.previousTask.previousTask.previousTask.assignee',
			'tasks.previousTask.previousTask.previousTask.previousTask.previousTask.previousTask.previousTask.previousTask.previousTask.previousTask.assignee',
			'members.user:id,full_name,system_role,email,phone,department,avatar_url,profile_completed',
		]);
	}

	## Returns the most recent update timestamp across the project and its tasks.
	public function heartbeat(Project $project)
	{
		$latestTask = $project->tasks()->latest('updated_at')->first();

		$latest = collect([
			$project->updated_at,
			$latestTask?->updated_at,
		])->filter()->max();

		return response()->json(['last_updated' => $latest]);
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
			'theme_color' => ['sometimes', 'nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
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
	##
	## Exception: theme_color is handled separately in updateThemeColor(),
	## which the lead CAN call. It is intentionally excluded from
	## validation here so this endpoint can stay strictly admin-only
	## without the two rules getting tangled together.
	public function update(Request $request, Project $project)
	{
		if (!$request->user()->isAdmin()) {
			return response()->json(['message' => 'Only an admin can edit this project.'], 403);
		}

		if ($project->is_system && $request->has('lead_id')) {
			return response()->json(['message' => "The Main project's lead is managed automatically."], 403);
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

	## Admin OR the project lead — updates only the theme color.
	## Split out from update() on purpose: this is the one project field
	## the lead is trusted with, so it gets its own narrowly-scoped
	## endpoint instead of widening what update() accepts.
	public function updateThemeColor(Request $request, Project $project)
	{
		$user = $request->user();

		if (!$user->isAdmin() && !$project->isLedBy($user)) {
			return response()->json(['message' => 'Only an admin or the project lead can change the theme color.'], 403);
		}

		if ($project->is_system) {
			return response()->json(['message' => "The Main project's theme color is managed automatically."], 403);
		}

		$validated = $request->validate([
			'theme_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
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

		if ($project->is_system) {
			return response()->json(['message' => 'The Main project cannot be deleted.'], 403);
		}

		$project->delete();

		return response()->json(null, 204);
	}
	## Admin only — uploads or replaces a project's logo.
	public function uploadLogo(Request $request, Project $project)
	{
		if (!$request->user()->isAdmin() && !$project->isLedBy($request->user())) {
			return response()->json(['message' => 'Only admins or the project lead can upload a project banner.'], 403);
		}

		if ($project->is_system) {
			return response()->json(['message' => "The Main project's logo cannot be changed."], 403);
		}

		$request->validate([
			'logo' => 'required|image|max:2048',
		]);

		if ($project->logo_path) {
			Storage::disk('public')->delete($project->logo_path);
		}

		$path = $request->file('logo')->store('projects/logos', 'public');

		$project->update(['logo_path' => $path]);

		return response()->json($project);
	}

	## Admin only — uploads or replaces a project's banner.
	public function uploadBanner(Request $request, Project $project)
	{
		if (!$request->user()->isAdmin() && !$project->isLedBy($request->user())) {
			return response()->json(['message' => 'Only admins or the project lead can upload a project banner.'], 403);
		}

		if ($project->is_system) {
			return response()->json(['message' => "The Main project's banner cannot be changed."], 403);
		}

		$request->validate([
			'banner' => 'required|image|max:5120',
		]);

		if ($project->banner_path) {
			Storage::disk('public')->delete($project->banner_path);
		}

		$path = $request->file('banner')->store('projects/banners', 'public');

		$project->update(['banner_path' => $path]);

		return response()->json($project);
	}
}