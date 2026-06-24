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
        $project = Project::first();
        $lead = User::where('username', 'lead1')->first();
        $members = User::where('system_role', 'team_member')
            ->where('id', '!=', $lead->id)
            ->get();

        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $lead->id,
        ]);

        foreach ($members as $member) {
            ProjectMember::create([
                'project_id' => $project->id,
                'user_id' => $member->id,
            ]);
        }

        $this->command->info('Seeded: project membership for lead + ' . $members->count() . ' members.');
    }
}