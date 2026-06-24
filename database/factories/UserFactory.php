<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= bcrypt('password'),
            'full_name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'department' => fake()->randomElement(['Engineering', 'Design', 'QA', 'Operations']),
            'system_role' => 'team_member',
            'status' => 'active',
            'login_count' => 0,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'system_role' => 'admin',
        ]);
    }
}