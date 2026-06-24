<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

class TaskSeeder extends Seeder
{
    public function run(): void
    {
        $project = Project::first();
        $lead = User::where('username', 'lead1')->first();
        $members = User::where('system_role', 'team_member')->get();
        $memberA = $members->get(0);
        $memberB = $members->get(1);

        // Regular task, still in progress, not yet submitted
        Task::create([
            'title' => 'Build login form',
            'description' => 'Implement the login UI and wire it to the auth endpoint.',
            'project_id' => $project->id,
            'assigned_to' => $memberA->id,
            'priority' => 'high',
            'requires_submission' => true,
            'status' => 'in_progress',
            'due_date' => now()->addDays(5),
        ]);

        // Regular task, submitted, awaiting lead review
        Task::create([
            'title' => 'Set up CI pipeline',
            'description' => 'Add GitHub Actions workflow for running tests on push.',
            'project_id' => $project->id,
            'assigned_to' => $memberB->id,
            'priority' => 'medium',
            'requires_submission' => true,
            'status' => 'submitted',
            'submission_link' => 'https://github.com/example/repo/pull/12',
            'submission_notes' => 'Added workflow file, tested on a sample PR.',
            'submitted_at' => now()->subHours(3),
            'due_date' => now()->addDays(2),
        ]);

        // Regular task, approved and done
        Task::create([
            'title' => 'Write database seeders',
            'description' => 'Seed users, projects, and roles for local development.',
            'project_id' => $project->id,
            'assigned_to' => $memberA->id,
            'priority' => 'low',
            'requires_submission' => true,
            'status' => 'done',
            'submission_link' => 'https://github.com/example/repo/pull/8',
            'submission_notes' => 'Covers all core tables.',
            'submitted_at' => now()->subDays(2),
            'reviewed_by' => $lead->id,
            'reviewed_at' => now()->subDays(1),
            'completed_date' => now()->subDays(1),
            'due_date' => now()->subDays(1),
        ]);

        // Rejected task (closed) + its revision chain
        $closedTask = Task::create([
            'title' => 'Add password reset email',
            'description' => 'Send a reset link when a user requests a password reset.',
            'project_id' => $project->id,
            'assigned_to' => $memberB->id,
            'priority' => 'medium',
            'requires_submission' => true,
            'status' => 'closed',
            'submission_link' => 'https://github.com/example/repo/pull/15',
            'submission_notes' => 'Reset email sends, but link expiry is not enforced.',
            'submitted_at' => now()->subDays(3),
            'review_comment' => 'Good start, but the reset link needs to expire after 1 hour. Please add that check.',
            'reviewed_by' => $lead->id,
            'reviewed_at' => now()->subDays(2),
            'due_date' => now()->subDays(2),
        ]);

        Task::create([
            'title' => 'Add password reset email (with link expiry)',
            'description' => 'Same as before, but enforce a 1 hour expiry on the reset link.',
            'project_id' => $project->id,
            'assigned_to' => $memberB->id,
            'priority' => 'medium',
            'requires_submission' => true,
            'status' => 'in_progress',
            'previous_task_id' => $closedTask->id,
            'due_date' => now()->addDays(1),
        ]);

        // Simple task, no submission required, member can self-complete
        Task::create([
            'title' => 'Update README with setup instructions',
            'description' => 'Document the Docker setup steps for new contributors.',
            'project_id' => $project->id,
            'assigned_to' => $memberA->id,
            'priority' => 'low',
            'requires_submission' => false,
            'status' => 'done',
            'completed_date' => now()->subHours(5),
            'due_date' => now()->subHours(5),
        ]);

        // Lead's own task, self-approved, no review involved
        Task::create([
            'title' => 'Review and merge open pull requests',
            'description' => 'Weekly PR review pass.',
            'project_id' => $project->id,
            'assigned_to' => $lead->id,
            'priority' => 'medium',
            'requires_submission' => true,
            'status' => 'done',
            'completed_date' => now()->subHours(1),
            'due_date' => now(),
        ]);

        $this->command->info('Seeded: 7 tasks covering all lifecycle states.');
    }
}