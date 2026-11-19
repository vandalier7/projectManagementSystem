<?php

namespace Tests\Feature;

use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Concerns\BuildsProjects;
use Tests\TestCase;

class TaskLifecycleTest extends TestCase
{
    use BuildsProjects, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
    }

    public function test_full_happy_path_todo_to_done(): void
    {
        $task = $this->makeTask();

        Sanctum::actingAs($this->member);
        $this->putJson("/api/tasks/{$task->id}", ['status' => 'in_progress'])->assertOk();
        $this->putJson("/api/tasks/{$task->id}", [
            'status' => 'submitted',
            'submission_link' => 'https://github.com/x/y/commit/1',
        ])->assertOk();

        Sanctum::actingAs($this->lead);
        $this->putJson("/api/tasks/{$task->id}", ['status' => 'done'])
            ->assertOk()
            ->assertJsonPath('status', 'done')
            ->assertJsonPath('reviewed_by', $this->lead->id);

        $this->assertNotNull($task->fresh()->completed_date);
    }

    public function test_submitting_requires_a_link(): void
    {
        $task = $this->makeTask(['status' => 'in_progress']);

        Sanctum::actingAs($this->member);
        $this->putJson("/api/tasks/{$task->id}", ['status' => 'submitted'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'A submission link is required to submit this task.');
    }

    public function test_member_cannot_approve_their_own_submission(): void
    {
        $task = $this->makeTask(['status' => 'submitted', 'submission_link' => 'x']);

        Sanctum::actingAs($this->member);
        $this->putJson("/api/tasks/{$task->id}", ['status' => 'done'])->assertForbidden();

        $this->assertSame('submitted', $task->fresh()->status);
    }

    public function test_rejection_closes_task_and_creates_linked_revision(): void
    {
        $task = $this->makeTask(['status' => 'submitted', 'submission_link' => 'x']);

        Sanctum::actingAs($this->lead);
        $response = $this->putJson("/api/tasks/{$task->id}", [
            'status' => 'closed',
            'review_comment' => 'Missing mobile layout',
            'new_title' => 'Build landing page (v2)',
        ])->assertOk();

        $response->assertJsonPath('closed_task.status', 'closed')
            ->assertJsonPath('closed_task.review_comment', 'Missing mobile layout')
            ->assertJsonPath('revision_task.title', 'Build landing page (v2)')
            ->assertJsonPath('revision_task.status', 'todo')
            ->assertJsonPath('revision_task.previous_task_id', $task->id)
            ->assertJsonPath('revision_task.assigned_to', $this->member->id);
    }

    public function test_member_can_take_back_a_submission(): void
    {
        $task = $this->makeTask([
            'status' => 'submitted',
            'submission_link' => 'x',
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($this->member);
        $this->putJson("/api/tasks/{$task->id}", ['status' => 'in_progress'])->assertOk();

        $fresh = $task->fresh();
        $this->assertSame('in_progress', $fresh->status);
        $this->assertNull($fresh->submission_link);
        $this->assertNull($fresh->submitted_at);
    }

    public function test_starting_an_unassigned_task_claims_it(): void
    {
        $task = $this->makeTask(['assigned_to' => null]);

        Sanctum::actingAs($this->member);
        $this->putJson("/api/tasks/{$task->id}", ['status' => 'in_progress'])
            ->assertOk()
            ->assertJsonPath('assigned_to', $this->member->id);
    }

    public function test_lead_claiming_a_task_drops_the_submission_requirement(): void
    {
        $task = $this->makeTask(['assigned_to' => null]);

        Sanctum::actingAs($this->lead);
        $this->putJson("/api/tasks/{$task->id}", ['status' => 'in_progress'])
            ->assertOk()
            ->assertJsonPath('requires_submission', false);
    }

    public function test_task_without_submission_can_be_self_completed(): void
    {
        $task = $this->makeTask(['status' => 'in_progress', 'requires_submission' => false]);

        Sanctum::actingAs($this->member);
        $this->putJson("/api/tasks/{$task->id}", ['status' => 'done'])->assertOk();
    }

    public function test_task_requiring_submission_cannot_skip_review(): void
    {
        $task = $this->makeTask(['status' => 'in_progress']);

        Sanctum::actingAs($this->member);
        $this->putJson("/api/tasks/{$task->id}", ['status' => 'done'])->assertForbidden();
    }

    public function test_invalid_transition_is_rejected(): void
    {
        $task = $this->makeTask();

        Sanctum::actingAs($this->lead);
        $this->putJson("/api/tasks/{$task->id}", ['status' => 'submitted', 'submission_link' => 'x'])
            ->assertForbidden()
            ->assertJsonPath('message', "The transition from 'todo' to 'submitted' is not allowed.");
    }

    public function test_only_lead_or_admin_can_edit_details(): void
    {
        $task = $this->makeTask();

        Sanctum::actingAs($this->member);
        $this->putJson("/api/tasks/{$task->id}", ['title' => 'Hacked'])->assertForbidden();

        Sanctum::actingAs($this->lead);
        $this->putJson("/api/tasks/{$task->id}", ['title' => 'Renamed'])->assertOk();

        $this->assertSame('Renamed', $task->fresh()->title);
    }

    public function test_submission_fields_cannot_be_mass_assigned(): void
    {
        $task = $this->makeTask();

        Sanctum::actingAs($this->lead);
        $this->putJson("/api/tasks/{$task->id}", ['title' => 'x', 'review_comment' => 'sneaky'])->assertOk();

        $this->assertNull($task->fresh()->review_comment);
    }

    public function test_history_returns_full_chain_beyond_ten_revisions(): void
    {
        $previous = null;
        for ($i = 1; $i <= 13; $i++) {
            $previous = $this->makeTask([
                'title' => "Attempt {$i}",
                'status' => $i < 13 ? 'closed' : 'todo',
                'previous_task_id' => $previous?->id,
            ]);
        }

        Sanctum::actingAs($this->member);
        $this->getJson("/api/tasks/{$previous->id}/history")
            ->assertOk()
            ->assertJsonCount(12, 'history')
            ->assertJsonPath('history.0.title', 'Attempt 12')
            ->assertJsonPath('history.11.title', 'Attempt 1');
    }

    public function test_project_show_nests_revision_chain(): void
    {
        $first = $this->makeTask(['title' => 'v1', 'status' => 'closed']);
        $second = $this->makeTask(['title' => 'v2', 'status' => 'closed', 'previous_task_id' => $first->id]);
        $this->makeTask(['title' => 'v3', 'previous_task_id' => $second->id]);

        Sanctum::actingAs($this->member);
        $tasks = collect($this->getJson("/api/projects/{$this->project->id}")->assertOk()->json('tasks'));

        $v3 = $tasks->firstWhere('title', 'v3');
        $this->assertSame('v2', $v3['previous_task']['title']);
        $this->assertSame('v1', $v3['previous_task']['previous_task']['title']);
        $this->assertSame($this->member->id, $v3['previous_task']['assignee']['id']);

        // A closed task on the board still gets its own chain.
        $this->assertSame('v1', $tasks->firstWhere('title', 'v2')['previous_task']['title']);
    }

    public function test_deleting_finished_task_is_admin_only(): void
    {
        $task = $this->makeTask(['status' => 'done']);

        Sanctum::actingAs($this->lead);
        $this->deleteJson("/api/tasks/{$task->id}")
            ->assertForbidden()
            ->assertJsonPath('message', 'Only admins can delete completed or closed tasks.');

        Sanctum::actingAs($this->admin);
        $this->deleteJson("/api/tasks/{$task->id}")->assertNoContent();
        $this->assertSoftDeleted($task);
    }

    public function test_only_lead_or_admin_can_create_tasks(): void
    {
        $payload = ['title' => 'New', 'project_id' => $this->project->id];

        Sanctum::actingAs($this->member);
        $this->postJson('/api/tasks', $payload)->assertForbidden();

        Sanctum::actingAs($this->lead);
        $this->postJson('/api/tasks', $payload)->assertCreated()->assertJsonPath('status', 'todo');
    }

    public function test_reorder_requires_lead_or_admin(): void
    {
        $a = $this->makeTask();
        $b = $this->makeTask();

        Sanctum::actingAs($this->member);
        $this->postJson('/api/tasks/reorder', ['ordered_ids' => [$b->id, $a->id]])->assertForbidden();

        Sanctum::actingAs($this->lead);
        $this->postJson('/api/tasks/reorder', ['ordered_ids' => [$b->id, $a->id]])->assertOk();
        $this->assertSame(0, Task::find($b->id)->sort_order);
    }
}
