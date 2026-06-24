<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('username', 'admin1')->first();
        $lead = User::where('username', 'lead1')->first();

        Project::create([
            'name' => 'ProjectDashboard v2 Rewrite',
            'description' => 'Laravel + Next.js rewrite of the original dashboard.',
            'status' => 'active',
            'start_date' => now()->subWeeks(2),
            'end_date' => now()->addMonths(2),
            'budget' => 50000.00,
            'spent' => 5000.00,
            'lead_id' => $lead->id,
            'created_by' => $admin->id,
            'task_default_submission_mode' => 'require',
        ]);

        $this->command->info('Seeded: 1 project.');
    }
}