<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProjectMemberSeeder extends Seeder
{
    public function run(): void
    {
        $project = Project::where('is_system', false)->first();
        $admin = User::where('username', 'admin')->first();
        $vandalier = User::where('username', 'Vandalier')->first();

        // Admin is the project lead, and also a formal member row.
        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $admin->id,
        ]);

        // Vandalier is the (sole) team member on the project.
        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $vandalier->id,
        ]);

        $this->command->info('Seeded: project membership - admin (lead) + Vandalier (member).');
    }
}