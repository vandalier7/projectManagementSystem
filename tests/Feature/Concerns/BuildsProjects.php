<?php

namespace Tests\Feature\Concerns;

use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Task;
use App\Models\User;

/**
 * A small world for API tests: one admin, a project with a lead and a member,
 * an outsider who belongs to no project, and the system ("Main") project.
 */
trait BuildsProjects
{
    protected User $admin;
    protected User $lead;
    protected User $member;
    protected User $outsider;
    protected Project $project;
    protected Project $systemProject;

    protected function buildWorld(): void
    {
        $this->admin = User::factory()->admin()->create();

        $this->systemProject = new Project([
            'name' => 'Main',
            'lead_id' => $this->admin->id,
            'created_by' => $this->admin->id,
        ]);
        $this->systemProject->is_system = true;
        $this->systemProject->save();

        $this->lead = User::factory()->create();
        $this->member = User::factory()->create();
        $this->outsider = User::factory()->create();

        $this->project = Project::create([
            'name' => 'Website Revamp',
            'lead_id' => $this->lead->id,
            'created_by' => $this->admin->id,
        ]);

        foreach ([$this->lead, $this->member] as $user) {
            ProjectMember::create(['project_id' => $this->project->id, 'user_id' => $user->id]);
        }
    }

    protected function makeTask(array $attributes = []): Task
    {
        return Task::create([
            'title' => 'Build landing page',
            'project_id' => $this->project->id,
            'assigned_to' => $this->member->id,
            'status' => 'todo',
            'requires_submission' => true,
            ...$attributes,
        ]);
    }
}
