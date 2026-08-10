<?php

namespace App\Http\Controllers\Api;

use App\Models\Invitation;
use App\Models\User;
use App\Notifications\InvitationNotification;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class InvitationController extends Controller
{
    /** Admin only — invites a new member by email. Replaces old direct-create flow. */
    public function store(Request $request)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Only admins can invite users.'], 403);
        }

        $validated = $request->validate([
            'email' => 'required|email|max:100|unique:users,email',
            'system_role' => 'required|in:admin,team_member',
        ]);

        // Prevent stacking multiple live invites for the same email.
        Invitation::where('email', $validated['email'])
            ->whereNull('used_at')
            ->delete();

        $token = Invitation::generateToken();

        $invitation = Invitation::create([
            'email' => $validated['email'],
            'token' => $token['hashed'],
            'system_role' => $validated['system_role'],
            'expires_at' => now()->addHours(48),
            'invited_by' => $request->user()->id,
        ]);

        Notification::route('mail', $invitation->email)
            ->notify(new InvitationNotification($token['plaintext'], $invitation->email));

        return response()->json(['message' => 'Invitation sent.'], 201);
    }

    /** Public — validates a token before showing the signup form. */
    public function show(string $token)
    {
        $invitation = Invitation::findValidByPlaintextToken($token);

        if (!$invitation) {
            return response()->json(['message' => 'This invite link is invalid or has expired.'], 404);
        }

        return response()->json(['email' => $invitation->email]);
    }

    /** Public — completes signup, creates the actual User. */
    public function complete(Request $request, string $token)
    {
        $invitation = Invitation::findValidByPlaintextToken($token);

        if (!$invitation) {
            return response()->json(['message' => 'This invite link is invalid or has expired.'], 404);
        }

        $validated = $request->validate([
            'username' => 'required|string|max:50|unique:users,username',
            'full_name' => 'required|string|max:100',
            'birthdate' => 'required|date|before:today',
            'password' => 'required|string|min:6|confirmed',
            'phone' => 'nullable|string|max:20',
        ]);

        $user = User::create([
            'username' => $validated['username'],
            'email' => $invitation->email,
            'password' => bcrypt($validated['password']),
            'full_name' => $validated['full_name'],
            'birthdate' => $validated['birthdate'],
            'phone' => $validated['phone'] ?? null,
            'system_role' => $invitation->system_role,
            'status' => 'active',
        ]);

        $invitation->update(['used_at' => now()]);

        return response()->json($user, 201);
    }
}