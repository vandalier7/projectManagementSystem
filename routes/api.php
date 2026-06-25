<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\UserController;
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

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::apiResource('users', UserController::class);
    Route::apiResource('projects', ProjectController::class);
    Route::apiResource('tasks', TaskController::class);

});