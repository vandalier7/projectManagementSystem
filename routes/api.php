<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectMemberController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// ── Public ──────────────────────────────────────────────────────────────
Route::post('/login', [AuthController::class, 'login']);

// ── Authenticated (Sanctum bearer token) ────────────────────────────────
// Every controller acts as $request->user(). Never trust a user id sent by
// the client to decide who is acting.
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);

    // Own profile
    Route::post('/profile/complete', [UserController::class, 'completeProfile']);
    Route::post('/profile/change-password', [UserController::class, 'changePassword']);
    Route::post('/profile/change-email', [UserController::class, 'changeEmail']);
    Route::post('/admin/change-password', [UserController::class, 'changeAdminPassword']);

    // Users (admin-managed)
    Route::post('/users/member', [UserController::class, 'createMember']);
    Route::post('/users/{user}/force-password-change', [UserController::class, 'forcePasswordChange']);
    Route::apiResource('users', UserController::class);

    // Invitations
    Route::get('/invitations/{token}', [InvitationController::class, 'show']);
    Route::post('/invitations/{token}/complete', [InvitationController::class, 'complete']);

    // Projects
    Route::get('/projects/system/tasks', [TaskController::class, 'systemTasks']);
    Route::get('/projects/{project}/heartbeat', [ProjectController::class, 'heartbeat']);
    Route::post('/projects/{project}/logo', [ProjectController::class, 'uploadLogo']);
    Route::post('/projects/{project}/banner', [ProjectController::class, 'uploadBanner']);
    Route::patch('/projects/{project}/theme-color', [ProjectController::class, 'updateThemeColor']);
    Route::apiResource('projects', ProjectController::class);

    // Project members
    Route::get('/projects/{project}/members', [ProjectMemberController::class, 'index']);
    Route::post('/projects/{project}/members', [ProjectMemberController::class, 'store']);
    Route::delete('/projects/{project}/members/{user}', [ProjectMemberController::class, 'destroy']);

    // Tasks
    Route::get('/tasks/mine', [TaskController::class, 'mine']);
    Route::post('/tasks/reorder', [TaskController::class, 'reorder']);
    Route::get('/tasks/{task}/history', [TaskController::class, 'history']);
    Route::apiResource('tasks', TaskController::class);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);
});
