<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Concerns\BuildsProjects;
use Tests\TestCase;

/**
 * Regression tests for the impersonation bug: the API used to decide who was
 * acting from `acting_as_user_id` / `?user=` sent by the client, so any
 * logged-in user could act as an admin. Identity now comes only from the token.
 */
class AuthorizationTest extends TestCase
{
    use BuildsProjects, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
    }

    public function test_api_requires_a_token(): void
    {
        $this->getJson('/api/projects')->assertUnauthorized();
        $this->getJson('/api/tasks/mine')->assertUnauthorized();
    }

    public function test_member_cannot_approve_by_claiming_to_be_admin(): void
    {
        $task = $this->makeTask(['status' => 'submitted', 'submission_link' => 'x']);

        Sanctum::actingAs($this->member);
        $this->putJson("/api/tasks/{$task->id}", [
            'status' => 'done',
            'acting_as_user_id' => $this->admin->id,
        ])->assertForbidden();

        $this->assertSame('submitted', $task->fresh()->status);
    }

    public function test_member_cannot_delete_by_claiming_to_be_admin(): void
    {
        $task = $this->makeTask();

        Sanctum::actingAs($this->member);
        $this->deleteJson("/api/tasks/{$task->id}", ['acting_as_user_id' => $this->admin->id])
            ->assertForbidden();

        $this->assertNotSoftDeleted($task);
    }

    public function test_my_tasks_ignores_user_query_param(): void
    {
        $this->makeTask(['title' => 'Member task']);

        Sanctum::actingAs($this->outsider);
        $this->getJson("/api/tasks/mine?user={$this->member->id}")
            ->assertOk()
            ->assertJsonCount(0);

        Sanctum::actingAs($this->member);
        $this->getJson('/api/tasks/mine')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.title', 'Member task')
            ->assertJsonPath('0.project.id', $this->project->id)
            ->assertJsonPath('0.project_lead_id', $this->lead->id);
    }

    public function test_outsider_cannot_read_task_history(): void
    {
        $task = $this->makeTask();

        Sanctum::actingAs($this->outsider);
        $this->getJson("/api/tasks/{$task->id}/history?user={$this->member->id}")->assertForbidden();
    }

    public function test_project_lead_can_read_history_of_any_task_in_their_project(): void
    {
        $task = $this->makeTask();

        Sanctum::actingAs($this->lead);
        $this->getJson("/api/tasks/{$task->id}/history")->assertOk();
    }

    public function test_outsider_cannot_view_project_or_heartbeat(): void
    {
        Sanctum::actingAs($this->outsider);
        $this->getJson("/api/projects/{$this->project->id}")->assertForbidden();
        $this->getJson("/api/projects/{$this->project->id}/heartbeat")->assertForbidden();
    }

    public function test_system_tasks_shows_members_only_their_own_and_unassigned(): void
    {
        $this->makeTask(['title' => 'Mine', 'project_id' => $this->systemProject->id]);
        $this->makeTask(['title' => 'Open', 'project_id' => $this->systemProject->id, 'assigned_to' => null]);
        $this->makeTask(['title' => 'Theirs', 'project_id' => $this->systemProject->id, 'assigned_to' => $this->lead->id]);

        Sanctum::actingAs($this->member);
        $titles = collect($this->getJson('/api/projects/system/tasks')->assertOk()->json())->pluck('title');
        $this->assertEqualsCanonicalizing(['Mine', 'Open'], $titles->all());

        Sanctum::actingAs($this->admin);
        $this->getJson('/api/projects/system/tasks')->assertOk()->assertJsonCount(3);
    }

    public function test_system_project_endpoint_returns_members(): void
    {
        Sanctum::actingAs($this->admin);
        $this->getJson('/api/projects/system')
            ->assertOk()
            ->assertJsonPath('id', $this->systemProject->id)
            ->assertJsonPath('is_system', true);
    }

    public function test_lead_can_manage_members_but_member_cannot(): void
    {
        Sanctum::actingAs($this->member);
        $this->postJson("/api/projects/{$this->project->id}/members", ['user_id' => $this->outsider->id])
            ->assertForbidden();

        Sanctum::actingAs($this->lead);
        $this->postJson("/api/projects/{$this->project->id}/members", ['user_id' => $this->outsider->id])
            ->assertCreated();
    }

    public function test_only_admin_can_edit_project_record(): void
    {
        Sanctum::actingAs($this->lead);
        $this->putJson("/api/projects/{$this->project->id}", ['name' => 'x'])->assertForbidden();

        Sanctum::actingAs($this->admin);
        $this->putJson("/api/projects/{$this->project->id}", ['name' => 'Renamed'])->assertOk();
    }

    public function test_user_can_change_own_email_with_password(): void
    {
        $this->member->update(['password' => 'secret123']);

        Sanctum::actingAs($this->member);
        $this->postJson('/api/profile/change-email', ['email' => 'new@example.com', 'password' => 'wrong'])
            ->assertForbidden();
        $this->postJson('/api/profile/change-email', ['email' => 'new@example.com', 'password' => 'secret123'])
            ->assertOk()
            ->assertJsonPath('email', 'new@example.com');
    }
}
