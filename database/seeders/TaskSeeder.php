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
        $project = Project::where('is_system', false)->first();
        $admin = User::where('username', 'admin')->first();       // lead / reviewer
        $vandalier = User::where('username', 'Vandalier')->first(); // sole assignee

        // ============================================================
        // COMPLETED HISTORY (Jun 22 - Jul 27, 2026)
        // The project's own build, told through its own tasks.
        //
        // - requires_submission = true  -> normal code/feature work,
        //   submission_link is a dummy GitHub PR URL.
        // - requires_submission = false -> "go do the thing" action
        //   tasks (deploy it, test it) - submission_link (when present)
        //   points to the actual result (a live URL, a report, etc),
        //   never a fake PR.
        //
        // previous_task_id is ONLY used for genuine reject -> revise ->
        // resubmit chains (a closed task pointed to by the task that
        // revises it) - never for plain sequential/roadmap ordering.
        // ============================================================

        Task::create([
            'title' => 'Evaluate legacy codebase & plan the rewrite',
            'description' => 'Reviewed the existing website\'s code to decide what could be kept vs scrapped. Decided to restart the project on Next.js + Laravel and architected the database and core program flow.',
            'project_id' => $project->id,
            'assigned_to' => $vandalier->id,
            'priority' => 'medium',
            'requires_submission' => true,
            'status' => 'done',
            'submission_link' => 'https://github.com/example/pms/pull/1',
            'submission_notes' => 'Architecture doc + DB schema draft for the Next.js/Laravel rewrite.',
            'submitted_at' => '2026-06-22 17:00:00',
            'reviewed_by' => $admin->id,
            'reviewed_at' => '2026-06-22 18:00:00',
            'completed_date' => '2026-06-22 18:00:00',
            'due_date' => '2026-06-22',
        ]);

        Task::create([
            'title' => 'Containerize the app with Docker & build the database layer',
            'description' => 'Fully containerized the empty Laravel back end and the database with Docker. Database layer is built using Docker-driven migrations, with initial seeding in place for testing.',
            'project_id' => $project->id,
            'assigned_to' => $vandalier->id,
            'priority' => 'medium',
            'requires_submission' => true,
            'status' => 'done',
            'submission_link' => 'https://github.com/example/pms/pull/2',
            'submission_notes' => 'docker-compose stack up, migrations + seeders running clean.',
            'submitted_at' => '2026-06-24 16:00:00',
            'reviewed_by' => $admin->id,
            'reviewed_at' => '2026-06-24 17:00:00',
            'completed_date' => '2026-06-24 17:00:00',
            'due_date' => '2026-06-24',
        ]);

        Task::create([
            'title' => 'Continue architecting the system & build the API layer',
            'description' => 'Continued architecting the Project Management System and built out a first working slice of the API layer.',
            'project_id' => $project->id,
            'assigned_to' => $vandalier->id,
            'priority' => 'medium',
            'requires_submission' => true,
            'status' => 'done',
            'submission_link' => 'https://github.com/example/pms/pull/3',
            'submission_notes' => 'Auth + projects + tasks endpoints scaffolded.',
            'submitted_at' => '2026-06-27 15:00:00',
            'reviewed_by' => $admin->id,
            'reviewed_at' => '2026-06-27 16:00:00',
            'completed_date' => '2026-06-27 16:00:00',
            'due_date' => '2026-06-27',
        ]);

        Task::create([
            'title' => 'Start building the frontend & verify Docker portability',
            'description' => 'Created and tested the core logic of the system, and verified the Dockerized stack deploys cleanly on a different machine.',
            'project_id' => $project->id,
            'assigned_to' => $vandalier->id,
            'priority' => 'medium',
            'requires_submission' => true,
            'status' => 'done',
            'submission_link' => 'https://github.com/example/pms/pull/4',
            'submission_notes' => 'Confirmed clean docker-compose up on a second machine, no host-specific assumptions left.',
            'submitted_at' => '2026-06-30 14:00:00',
            'reviewed_by' => $admin->id,
            'reviewed_at' => '2026-06-30 15:00:00',
            'completed_date' => '2026-06-30 15:00:00',
            'due_date' => '2026-06-30',
        ]);

        // --- A real multi-hop rejection chain: security pass rejected
        // twice before being accepted. Demonstrates A <- B <- C traversal. ---

        $secA = Task::create([
            'title' => 'Security pass on database & API layer',
            'description' => 'Hardened the security of the system at the database and API layer, verified with curl-based tests.',
            'project_id' => $project->id,
            'assigned_to' => $vandalier->id,
            'priority' => 'high',
            'requires_submission' => true,
            'status' => 'closed',
            'submission_link' => 'https://github.com/example/pms/pull/5',
            'submission_notes' => 'Auth guards added on most endpoints, validated via curl.',
            'submitted_at' => '2026-07-01 12:00:00',
            'review_comment' => 'Missing rate-limiting on the auth endpoints - anyone can hammer /login and /register right now. Please add rate-limiting before this goes through.',
            'reviewed_by' => $admin->id,
            'reviewed_at' => '2026-07-01 13:00:00',
            'due_date' => '2026-07-01',
        ]);

        $secB = Task::create([
            'title' => 'Security pass on database & API layer (rate-limiting added)',
            'description' => 'Revision of the security pass: added rate-limiting on the auth endpoints as requested.',
            'project_id' => $project->id,
            'assigned_to' => $vandalier->id,
            'priority' => 'high',
            'requires_submission' => true,
            'status' => 'closed',
            'previous_task_id' => $secA->id,
            'submission_link' => 'https://github.com/example/pms/pull/6',
            'submission_notes' => 'Rate-limiting added to /login and /register.',
            'submitted_at' => '2026-07-02 09:00:00',
            'review_comment' => 'Rate-limiting looks good, but the password-reset endpoint still isn\'t covered. One more pass please.',
            'reviewed_by' => $admin->id,
            'reviewed_at' => '2026-07-02 10:00:00',
            'due_date' => '2026-07-02',
        ]);

        Task::create([
            'title' => 'Security pass on database & API layer (full endpoint coverage)',
            'description' => 'Revision of the security pass: extended rate-limiting and validation to cover the password-reset endpoint too.',
            'project_id' => $project->id,
            'assigned_to' => $vandalier->id,
            'priority' => 'high',
            'requires_submission' => true,
            'status' => 'done',
            'previous_task_id' => $secB->id,
            'submission_link' => 'https://github.com/example/pms/pull/7',
            'submission_notes' => 'All auth-related endpoints now rate-limited and validated. Full curl test suite passing.',
            'submitted_at' => '2026-07-02 14:00:00',
            'reviewed_by' => $admin->id,
            'reviewed_at' => '2026-07-02 15:00:00',
            'completed_date' => '2026-07-02 15:00:00',
            'due_date' => '2026-07-02',
        ]);

        Task::create([
            'title' => 'Polish website & finalize routes',
            'description' => 'Began polishing the website and finalizing the application routes.',
            'project_id' => $project->id,
            'assigned_to' => $vandalier->id,
            'priority' => 'low',
            'requires_submission' => true,
            'status' => 'done',
            'submission_link' => 'https://github.com/example/pms/pull/8',
            'submission_notes' => 'Route list finalized, dead routes removed.',
            'submitted_at' => '2026-07-04 14:00:00',
            'reviewed_by' => $admin->id,
            'reviewed_at' => '2026-07-04 15:00:00',
            'completed_date' => '2026-07-04 15:00:00',
            'due_date' => '2026-07-04',
        ]);

        Task::create([
            'title' => 'Complete the core application loop',
            'description' => 'The core loop of the system is now almost done and usable end to end.',
            'project_id' => $project->id,
            'assigned_to' => $vandalier->id,
            'priority' => 'high',
            'requires_submission' => true,
            'status' => 'done',
            'submission_link' => 'https://github.com/example/pms/pull/9',
            'submission_notes' => 'Kanban board + task lifecycle usable end-to-end in the dev environment.',
            'submitted_at' => '2026-07-06 14:00:00',
            'reviewed_by' => $admin->id,
            'reviewed_at' => '2026-07-06 15:00:00',
            'completed_date' => '2026-07-06 15:00:00',
            'due_date' => '2026-07-06',
        ]);

        Task::create([
            'title' => 'Improve UI/UX of modals, task boards & member list',
            'description' => 'General UI improvement pass: reworked the UI and UX of the Modals, Task Boards, and the Member list.',
            'project_id' => $project->id,
            'assigned_to' => $vandalier->id,
            'priority' => 'low',
            'requires_submission' => true,
            'status' => 'done',
            'submission_link' => 'https://github.com/example/pms/pull/10',
            'submission_notes' => 'Modals, kanban board, and member list restyled and made more consistent.',
            'submitted_at' => '2026-07-09 14:00:00',
            'reviewed_by' => $admin->id,
            'reviewed_at' => '2026-07-09 15:00:00',
            'completed_date' => '2026-07-09 15:00:00',
            'due_date' => '2026-07-09',
        ]);

        $notifSchema = Task::create([
            'title' => 'Design notification database schema',
            'description' => 'Began implementing peripherals such as automated account-creation emails and email notifications. Created the DB tables that will serve as the basis for notifications.',
            'project_id' => $project->id,
            'assigned_to' => $vandalier->id,
            'priority' => 'medium',
            'requires_submission' => true,
            'status' => 'done',
            'submission_link' => 'https://github.com/example/pms/pull/11',
            'submission_notes' => 'notifications + notification_types tables migrated.',
            'submitted_at' => '2026-07-08 14:00:00',
            'reviewed_by' => $admin->id,
            'reviewed_at' => '2026-07-08 15:00:00',
            'completed_date' => '2026-07-08 15:00:00',
            'due_date' => '2026-07-08',
        ]);

        // --- The real rejection chain from the accomplishments log:
        // the no-reply email approach was rejected, and a fresh
        // task was created to architect the alternative. ---

        $noReplyEmail = Task::create([
            'title' => 'Implement automated no-reply emails for account creation',
            'description' => 'Started working on automated emails (account creation, notifications, email-based auth) using a transactional no-reply email domain.',
            'project_id' => $project->id,
            'assigned_to' => $vandalier->id,
            'priority' => 'medium',
            'requires_submission' => true,
            'status' => 'closed',
            'submission_link' => 'https://github.com/example/pms/pull/12',
            'submission_notes' => 'Working proof-of-concept, but requires a paid email-sending domain/service.',
            'submitted_at' => '2026-07-10 12:00:00',
            'review_comment' => 'Email domains require ongoing cost we don\'t want to carry - rejecting this approach. Need an alternative that avoids a no-reply email dependency entirely.',
            'reviewed_by' => $admin->id,
            'reviewed_at' => '2026-07-10 13:00:00',
            'due_date' => '2026-07-10',
        ]);

        Task::create([
            'title' => 'Architect an alternative sign-up flow (no no-reply email)',
            'description' => 'Thought through an alternative for the sign-up flow. Architected a secure sign-up flow that does not require a no-reply email at all.',
            'project_id' => $project->id,
            'assigned_to' => $vandalier->id,
            'priority' => 'medium',
            'requires_submission' => true,
            'status' => 'done',
            'previous_task_id' => $noReplyEmail->id,
            'submission_link' => 'https://github.com/example/pms/pull/13',
            'submission_notes' => 'Design doc for token-based / admin-provisioned sign-up flow.',
            'submitted_at' => '2026-07-13 14:00:00',
            'reviewed_by' => $admin->id,
            'reviewed_at' => '2026-07-13 15:00:00',
            'completed_date' => '2026-07-13 15:00:00',
            'due_date' => '2026-07-13',
        ]);

        Task::create([
            'title' => 'Implement system tasks as a dashboard widget',
            'description' => 'Implemented system tasks (tasks not associated with any project) as a single widget on the main dashboard.',
            'project_id' => $project->id,
            'assigned_to' => $vandalier->id,
            'priority' => 'medium',
            'requires_submission' => true,
            'status' => 'done',
            'submission_link' => 'https://github.com/example/pms/pull/14',
            'submission_notes' => 'System-tasks widget live on the dashboard.',
            'submitted_at' => '2026-07-14 14:00:00',
            'reviewed_by' => $admin->id,
            'reviewed_at' => '2026-07-14 15:00:00',
            'completed_date' => '2026-07-14 15:00:00',
            'due_date' => '2026-07-14',
        ]);

        Task::create([
            'title' => 'Implement & test the new sign-up flow',
            'description' => 'Implemented the alternative sign-up flow (the one architected earlier). New sign-up flow implemented and tested to be working.',
            'project_id' => $project->id,
            'assigned_to' => $vandalier->id,
            'priority' => 'medium',
            'requires_submission' => true,
            'status' => 'done',
            'submission_link' => 'https://github.com/example/pms/pull/15',
            'submission_notes' => 'End-to-end sign-up flow tested, no email dependency.',
            'submitted_at' => '2026-07-17 14:00:00',
            'reviewed_by' => $admin->id,
            'reviewed_at' => '2026-07-17 15:00:00',
            'completed_date' => '2026-07-17 15:00:00',
            'due_date' => '2026-07-17',
        ]);

        Task::create([
            'title' => 'Implement & test the change-password flow',
            'description' => 'Implemented the change-password flow. Tested and confirmed working.',
            'project_id' => $project->id,
            'assigned_to' => $vandalier->id,
            'priority' => 'medium',
            'requires_submission' => true,
            'status' => 'done',
            'submission_link' => 'https://github.com/example/pms/pull/16',
            'submission_notes' => 'Change-password flow tested end-to-end.',
            'submitted_at' => '2026-07-20 14:00:00',
            'reviewed_by' => $admin->id,
            'reviewed_at' => '2026-07-20 15:00:00',
            'completed_date' => '2026-07-20 15:00:00',
            'due_date' => '2026-07-20',
        ]);

        Task::create([
            'title' => 'Add project logo customization',
            'description' => 'Added some customization for projects, starting with logo customization per project.',
            'project_id' => $project->id,
            'assigned_to' => $vandalier->id,
            'priority' => 'low',
            'requires_submission' => true,
            'status' => 'done',
            'submission_link' => 'https://github.com/example/pms/pull/17',
            'submission_notes' => 'Per-project logo upload + display working.',
            'submitted_at' => '2026-07-23 14:00:00',
            'reviewed_by' => $admin->id,
            'reviewed_at' => '2026-07-23 15:00:00',
            'completed_date' => '2026-07-23 15:00:00',
            'due_date' => '2026-07-23',
        ]);

        Task::create([
            'title' => 'Add project banner customization',
            'description' => 'Added banner customization per project. Customization tested and confirmed working across connected devices.',
            'project_id' => $project->id,
            'assigned_to' => $vandalier->id,
            'priority' => 'low',
            'requires_submission' => true,
            'status' => 'done',
            'submission_link' => 'https://github.com/example/pms/pull/18',
            'submission_notes' => 'Per-project banner upload verified across desktop and mobile.',
            'submitted_at' => '2026-07-27 14:00:00',
            'reviewed_by' => $admin->id,
            'reviewed_at' => '2026-07-27 15:00:00',
            'completed_date' => '2026-07-27 15:00:00',
            'due_date' => '2026-07-27',
        ]);

        // ============================================================
        // STILL TO BE DONE - new admin-facing features
        // Mixed lifecycle states below: some not started ('todo'),
        // some actively being worked ('in_progress'), some turned in
        // and awaiting review ('submitted'). None carry
        // previous_task_id - they're fresh tasks, not revisions of a
        // rejected one. 'priority' doubles as the difficulty signal
        // since there's no dedicated difficulty column on tasks.
        // ============================================================

        Task::create([
            'title' => 'Build in-app notifications feature',
            'description' => 'Implement the actual notification delivery/UI on top of the existing notifications DB schema (account events, task assignment, review outcomes, etc). Builds on the schema designed earlier in the project.',
            'project_id' => $project->id,
            'assigned_to' => $vandalier->id,
            'priority' => 'medium',
            'requires_submission' => true,
            'status' => 'submitted',
            'submission_link' => 'https://github.com/example/pms/pull/19',
            'submission_notes' => 'In-app notifications working for task assignment and review outcomes. Account-event notifications still pending a follow-up.',
            'submitted_at' => '2026-07-28 10:00:00',
            'due_date' => '2026-08-05',
        ]);

        // Action task, not a code submission: requires_submission = false
        // means it never passes through the 'submitted' review step at
        // all (todo -> in_progress -> done, self-completed). Not started yet.
        Task::create([
            'title' => 'Stand up an early test deployment (Docker)',
            'description' => 'Deploy the current build to a live environment for early testing, leveraging the existing Docker setup to keep this cheap and disposable.',
            'project_id' => $project->id,
            'assigned_to' => $vandalier->id,
            'priority' => 'low',
            'requires_submission' => false,
            'status' => 'todo',
            'due_date' => '2026-08-03',
        ]);

        Task::create([
            'title' => 'Admin: backup & restore tooling',
            'description' => 'Add admin-facing database backup and restore actions, including scheduled backups and a manual restore-from-snapshot flow.',
            'project_id' => $project->id,
            'assigned_to' => $vandalier->id,
            'priority' => 'high',
            'requires_submission' => true,
            'status' => 'in_progress',
            'due_date' => '2026-08-10',
        ]);

        Task::create([
            'title' => 'Admin: audit log viewer',
            'description' => 'Track and expose an admin-facing audit log of key system actions (logins, task reviews, project changes, admin actions).',
            'project_id' => $project->id,
            'assigned_to' => $vandalier->id,
            'priority' => 'medium',
            'requires_submission' => true,
            'status' => 'in_progress',
            'due_date' => '2026-08-08',
        ]);

        Task::create([
            'title' => 'Admin: reporting dashboard',
            'description' => 'Build admin-facing reports (project progress, task throughput, rejection/rework rates), drawing on the same data as the audit log.',
            'project_id' => $project->id,
            'assigned_to' => $vandalier->id,
            'priority' => 'high',
            'requires_submission' => true,
            'status' => 'todo',
            'due_date' => '2026-08-14',
        ]);

        Task::create([
            'title' => 'Build in-project chat',
            'description' => 'Add a chat feature scoped per project, for local teams collaborating on a given project to talk in context.',
            'project_id' => $project->id,
            'assigned_to' => $vandalier->id,
            'priority' => 'high',
            'requires_submission' => true,
            'status' => 'todo',
            'due_date' => '2026-08-17',
        ]);

        $this->command->info('Seeded: 18 completed tasks (Jun 22 - Jul 27, including a 3-hop rejection chain and a 2-hop rejection chain) + 6 pending tasks (1 submitted, 2 in_progress, 3 todo), all on Project Management System.');
    }
}