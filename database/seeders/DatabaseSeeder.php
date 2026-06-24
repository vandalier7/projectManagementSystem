<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $admin = User::factory()->admin()->create([
            'username' => 'admin1',
            'email' => 'admin@test.com',
            'full_name' => 'Admin Tester',
        ]);

        $lead = User::factory()->create([
            'username' => 'lead1',
            'email' => 'lead@test.com',
            'full_name' => 'Lead Tester',
        ]);

        $members = User::factory()->count(3)->create();

        $this->command->info("Seeded: 1 admin, 1 lead, {$members->count()} members.");

        $this->call([
            RoleSeeder::class,
            ProjectSeeder::class,
            ProjectMemberSeeder::class,
            TaskSeeder::class,
        ]);
    }
}