<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectMemberController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\NotificationController;
use Illuminate\Support\Facades\Route;

// Public routes — no token required.
Route::post('/login', [AuthController::class, 'login']);

// AUTH_BYPASS only has any effect when app.env is 'local'. This is checked
// in code, not just by convention — even if AUTH_BYPASS=true is left in a
// production .env by mistake, app()->environment() will not be 'local'
// there, and this line evaluates to false regardless of the flag's value.
// This makes the dangerous case (bypass active in production) structurally
// impossible rather than just discouraged.
$bypassActive = app()->environment('local') && env('AUTH_BYPASS') === 'true';

$middleware = $bypassActive ? [] : ['auth:sanctum'];

Route::middleware($middleware)->group(function () {

    // Route::middleware('auth:sanctum')->post('/users/member', [InvitationController::class, 'store']);
    Route::middleware('auth:sanctum')->post('/users/member', [UserController::class, 'createMember']);
    Route::middleware('auth:sanctum')->post('/profile/complete', [UserController::class, 'completeProfile']);
    Route::post('/users/{user}/force-password-change', [UserController::class, 'forcePasswordChange']);
    Route::middleware('auth:sanctum')->post('/profile/change-password', [UserController::class, 'changePassword']);
    Route::middleware('auth:sanctum')->post('/profile/change-email', [UserController::class, 'changeEmail']);
    Route::middleware('auth:sanctum')->post('/admin/change-password', [UserController::class, 'changeAdminPassword']);

    Route::post('/projects/{project}/logo', [ProjectController::class, 'uploadLogo']);
    Route::post('/projects/{project}/banner', [ProjectController::class, 'uploadBanner']);
    Route::patch('/projects/{project}/theme-color', [ProjectController::class, 'updateThemeColor']);

    Route::get('/invitations/{token}', [InvitationController::class, 'show']);
    Route::post('/invitations/{token}/complete', [InvitationController::class, 'complete']);

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/projects/{project}/heartbeat', [ProjectController::class, 'heartbeat']);
    Route::post('/tasks/reorder', [TaskController::class, 'reorder']);
    Route::get('/tasks/mine', [TaskController::class, 'mine']);
    Route::get('/projects/system/tasks', [TaskController::class, 'systemTasks']);

    Route::apiResource('users', UserController::class);
    Route::apiResource('projects', ProjectController::class);
    Route::apiResource('tasks', TaskController::class);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);


    Route::get('/tasks/{task}/history', [TaskController::class, 'history']);
    Route::get('/projects/{project}/members', [ProjectMemberController::class, 'index']);
    Route::post('/projects/{project}/members', [ProjectMemberController::class, 'store']);
    Route::delete('/projects/{project}/members/{user}', [ProjectMemberController::class, 'destroy']);

});