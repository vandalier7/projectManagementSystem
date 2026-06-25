<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

## Public routes — no token required.
Route::post('/login', [AuthController::class, 'login']);

## All routes below require a valid Sanctum token,
## unless AUTH_BYPASS=true is set in .env.
$middleware = env('AUTH_BYPASS') === 'true' ? [] : ['auth:sanctum'];

Route::middleware($middleware)->group(function () {

	Route::post('/logout', [AuthController::class, 'logout']);

	Route::apiResource('users', UserController::class);
	Route::apiResource('projects', ProjectController::class);
	Route::apiResource('tasks', TaskController::class);

});