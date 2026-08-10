<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Remove existing unique constraint first (if it exists)
            $table->dropUnique(['username']);

            // Make username nullable and keep it unique
            $table->string('username')
                ->nullable()
                ->unique()
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);

            $table->string('username')
                ->nullable(false)
                ->unique()
                ->change();
        });
    }
};