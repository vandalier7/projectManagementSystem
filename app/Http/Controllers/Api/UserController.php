<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\ProjectMember;
use App\Models\Project;
use App\Models\Task;

class UserController extends Controller
{
	## Returns all users. No restriction — any authenticated user can view the list.
	public function index()
	{
		return User::all();
	}

	## Returns a single user. No restriction — any authenticated user can view.
	public function show(User $user)
	{
		return $user;
	}

	## Admin only — creates a new user account.
	public function store(Request $request)
	{
		if (!$request->user()->isAdmin()) {
			return response()->json(['message' => 'Only admins can create users.'], 403);
		}

		$validated = $request->validate([
			'username' => 'required|string|max:50|unique:users,username',
			'email' => 'required|email|max:100|unique:users,email',
			'password' => 'required|string|min:6',
			'full_name' => 'required|string|max:100',
			'phone' => 'nullable|string|max:20',
			'department' => 'nullable|string|max:50',
			'system_role' => 'required|in:admin,team_member',
		]);

		$validated['password'] = bcrypt($validated['password']);

		$user = User::create($validated);

		$this->syncMainProjectMembership($user);

		return response()->json($user, 201);
	}

	## Admin only — updates an existing user account.
	public function update(Request $request, User $user)
	{
		if (!$request->user()->isAdmin()) {
			return response()->json(['message' => 'Only admins can edit users.'], 403);
		}

		if ($error = $this->verifyPassword($request)) {
			return $error;
		}

		$validated = $request->validate([
			'username' => ['sometimes', 'string', 'max:50', Rule::unique('users', 'username')->ignore($user->id)],
			'email' => ['sometimes', 'email', 'max:100', Rule::unique('users', 'email')->ignore($user->id)],
			'full_name' => 'sometimes|string|max:100',
			'phone' => 'nullable|string|max:20',
			'department' => 'nullable|string|max:50',
			'system_role' => 'sometimes|in:admin,team_member',
			'status' => 'sometimes|string|max:20',
		]);

		$user->update($validated);

		$this->syncMainProjectMembership($user);

		return response()->json($user);
	}

	## Admin only — deletes a user account.
	public function destroy(Request $request, User $user)
	{
		if (!$request->user()->isAdmin()) {
			return response()->json(['message' => 'Only admins can delete users.'], 403);
		}

		if ($error = $this->verifyPassword($request)) {
			return $error;
		}

		if ($user->system_role === 'admin') {
			return response()->json([
				'message' => 'Admin accounts cannot be deleted.'
			], 403);
		}

		if ($user->id === $request->user()->id) {
			return response()->json([
				'message' => 'You cannot delete your own account.'
			], 403);
		}

		// Wipe the email so it frees up for reuse (e.g. re-inviting someone
		// with the same address later) while keeping it unique-safe against
		// other soft-deleted rows, in case the column has a unique constraint.
		$user->update([
			'email' => 'deleted_' . $user->id . '_' . now()->timestamp . '@deleted.local',
		]);

		Task::where('assigned_to', $user->id)
			->whereNotIn('status', ['done', 'closed'])
			->update(['assigned_to' => null]);

		$membershipCount = $user->projectMemberships()->count();
		\Log::info("About to delete {$membershipCount} memberships for user {$user->id}");
		$deleted = $user->projectMemberships()->delete();
		\Log::info("Deleted count: {$deleted}");

		$user->delete();

		return response()->json(null, 204);
	}

	public function createMember(Request $request)
	{
		if (!$request->user()->isAdmin()) {
			return response()->json([
				'message' => 'Only admins can create users.'
			], 403);
		}

		$validated = $request->validate([
			'email' => 'required|email|max:100',
		]);

		$existing = User::where('email', $validated['email'])->first();

		if ($existing && $existing->profile_completed) {
			return response()->json([
				'message' => 'This email is already registered to a completed account.'
			], 422);
		}

		$tempPassword = Str::random(12);

		if ($existing) {
			// Pending invite — overwrite completely
			$existing->update([
				'username' => null,
				'password' => $tempPassword,
				'full_name' => null,
				'phone' => null,
				'department' => null,
				'system_role' => 'team_member',
				'must_change_password' => true,
				'profile_completed' => false,
			]);

			$user = $existing;
		} else {
			$user = User::create([
				'username' => null,
				'email' => $validated['email'],
				'password' => $tempPassword,
				'full_name' => null,
				'phone' => null,
				'department' => null,
				'system_role' => 'team_member',
				'must_change_password' => false,
				'profile_completed' => false,
			]);
		}

		$this->syncMainProjectMembership($user);

		return response()->json([
			'user' => $user,
			'temp_password' => $tempPassword,
		], 201);
	}

	private function syncMainProjectMembership(User $user): void
	{
		$main = Project::main();

		if (!$main) {
			return;
		}

		ProjectMember::firstOrCreate([
			'project_id' => $main->id,
			'user_id' => $user->id,
		]);

		if ($user->system_role === 'admin' && $main->lead_id !== $user->id) {
			$main->update(['lead_id' => $user->id]);
		}
	}

	public function completeProfile(Request $request)
	{
		$user = $request->user();

		$validated = $request->validate([
			'username' => 'required|string|max:50|unique:users,username',
			'full_name' => 'required|string|max:100',
			'password' => 'required|string|min:6',
		]);

		$user->update([
			'username' => $validated['username'],
			'full_name' => $validated['full_name'],
			'password' => $validated['password'],
			'profile_completed' => true,
		]);

		return response()->json($user);
	}

	## Verifies the currently-authenticated user's password, for high-security confirmations.
	private function verifyPassword(Request $request): ?\Illuminate\Http\JsonResponse
	{
		$validated = $request->validate([
			'password' => 'required|string',
		]);

		if (!Hash::check($validated['password'], $request->user()->password)) {
			return response()->json(['message' => 'Incorrect password.'], 403);
		}

		return null;
	}

	## Admin only — forces a user to change their password, generating a new temp password.
	public function forcePasswordChange(Request $request, User $user)
	{
		if (!$request->user()->isAdmin()) {
			return response()->json(['message' => 'Only admins can force a password change.'], 403);
		}

		if ($error = $this->verifyPassword($request)) {
			return $error;
		}

		$tempPassword = Str::random(12);

		$user->update([
			'password' => $tempPassword,
			'must_change_password' => true,
		]);

		return response()->json([
			'user' => $user,
			'temp_password' => $tempPassword,
		]);
	}

	public function changePassword(Request $request)
	{
		$user = $request->user();

		if ($user->system_role === 'admin') {
			return response()->json([
				'message' => 'Admin accounts must use the admin password change endpoint.'
			], 403);
		}

		$validated = $request->validate([
			'password' => 'required|string|min:6',
		]);

		$user->update([
			'password' => $validated['password'],
			'must_change_password' => false,
		]);

		return response()->json($user);
	}

	## Admin only — lets the currently-authenticated admin change their own password. Requires the current password, unlike the generic changePassword() above.
	public function changeAdminPassword(Request $request)
	{
		$user = $request->user();

		if ($user->system_role !== 'admin') {
			return response()->json(['message' => 'Only admin accounts can use this endpoint.'], 403);
		}

		$validated = $request->validate([
			'current_password' => 'required|string',
			'password' => 'required|string|min:6|confirmed',
		]);

		if (!Hash::check($validated['current_password'], $user->password)) {
			return response()->json(['message' => 'Current password is incorrect.'], 403);
		}

		$user->update([
			'password' => $validated['password'],
		]);

		return response()->json($user);
	}
}

