<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('username', 'admin')->first();

        // --- System project (the first project) ---
        $systemProject = Project::create([
            'name' => 'General',
            'description' => 'System-wide project. All users are members by default.',
            'status' => 'active',
            'start_date' => null,
            'end_date' => null,
            'budget' => null,
            'spent' => 0.00,
            'is_system' => true,
            'lead_id' => $admin->id,
            'created_by' => $admin->id,
        ]);

        // Users were created before this project existed, so the User::booted()
        // auto-join hook never fired for them. Attach everyone manually, same
        // as the original seeder did.
        foreach (User::all() as $user) {
            $systemProject->members()->create([
                'user_id' => $user->id,
                'joined_at' => now(),
            ]);
        }

        // --- First (and only) non-system project ---

        // Seed the banner: copy the source asset (versioned in the repo)
        // into the public disk, and store only the relative path on the
        // model. The full URL is computed at read time via
        // Project::getBannerUrlAttribute().
        $bannerSource = database_path('seeders/assets/banners/dbms.jpg');
        $bannerPath = 'banners/dbms.jpg';

        if (file_exists($bannerSource)) {
            Storage::disk('public')->put($bannerPath, file_get_contents($bannerSource));
        } else {
            $bannerPath = null;
            $this->command->warn("Banner asset not found at {$bannerSource} - skipping banner seed.");
        }

        Project::create([
            'name' => 'Project Management System',
            'description' => "A rewrite of what was tried before — built to be used by local teams, combining a kanban-style board with task chains and a commit-history-style timeline of the work itself.",
            'status' => 'active',
            'start_date' => '2026-06-22',
            'end_date' => null,
            'budget' => null,
            'spent' => 0.00,
            'is_system' => false,
            'lead_id' => $admin->id,
            'created_by' => $admin->id,
            'task_default_submission_mode' => 'require',
            'banner_path' => $bannerPath,
            'theme_color' => '#2a2c83',
        ]);

        $this->command->info('Seeded: 2 projects (1 system, 1 non-system).');
    }
}