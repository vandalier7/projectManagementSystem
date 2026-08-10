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
        // Admin account
        $admin = User::create([
            'username' => 'admin',
            'email' => 'admin@temp.net',
            'password' => 'adminpass', // auto-hashed via the 'hashed' cast on User
            'full_name' => 'Admin User',
            'system_role' => 'admin',
            'status' => 'active',
            'login_count' => 0,
            'profile_completed' => true,
            'must_change_password' => false,
        ]);

        // Team member account (James Duran / Vandalier)
        $vandalier = User::create([
            'username' => 'Vandalier',
            'email' => 'jahadielduran26@gmail.com',
            'password' => 'qwertyuiop', // auto-hashed via the 'hashed' cast on User
            'full_name' => 'James Duran',
            'system_role' => 'team_member',
            'status' => 'active',
            'login_count' => 0,
            'profile_completed' => true,
            'must_change_password' => false,
        ]);

        $this->command->info('Seeded: 1 admin (admin@temp.net), 1 team member (Vandalier / jahadielduran26@gmail.com).');

        $this->call([
            RoleSeeder::class,
            ProjectSeeder::class,
            ProjectMemberSeeder::class,
            TaskSeeder::class,
        ]);
    }
}