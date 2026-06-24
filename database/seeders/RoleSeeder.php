<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::create(['name' => 'Frontend']);
        Role::create(['name' => 'Backend']);

        $this->command->info('Seeded: 2 default roles.');
    }
}