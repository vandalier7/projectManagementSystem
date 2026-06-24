<?php

use Illuminate\Support\Facades\Route;
use App\Models\User;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Task;
use App\Models\Role;
use App\Models\ProjectMemberRole;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/debug/users', fn () => User::all());
Route::get('/debug/projects', fn () => Project::all());
Route::get('/debug/project-members', fn () => ProjectMember::all());
Route::get('/debug/tasks', fn () => Task::all());
Route::get('/debug/roles', fn () => Role::all());
Route::get('/debug/project-member-roles', fn () => ProjectMemberRole::all());
Route::get('/tasks-dev', function () {
    return view('tasks-dev');
});