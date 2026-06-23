<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username', 50)->unique();
            $table->string('email', 100)->unique();
            $table->string('password');
            $table->string('full_name', 100);
            $table->string('phone', 20)->nullable();
            $table->string('department', 50)->nullable();
            $table->enum('system_role', ['admin', 'team_lead', 'team_member'])
                ->default('team_member');
            $table->string('status', 20)->default('active');
            $table->dateTime('last_login')->nullable();
            $table->unsignedInteger('login_count')->default(0);
            $table->string('avatar_url')->nullable();
            $table->timestamps();

            $table->index('system_role');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};