<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('description')->nullable();

            $table->foreignId('project_id')
                ->constrained('projects')
                ->cascadeOnDelete();

            $table->foreignId('assigned_to')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('priority')->default('medium');

            $table->boolean('requires_submission')->default(true);

            $table->enum('status', [
                'todo',
                'in_progress',
                'submitted',
                'done',
                'closed',
            ])->default('todo');

            $table->foreignId('previous_task_id')
                ->nullable()
                ->constrained('tasks')
                ->nullOnDelete();

            $table->string('submission_link')->nullable();
            $table->text('submission_notes')->nullable();
            $table->dateTime('submitted_at')->nullable();

            $table->text('review_comment')->nullable();
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();

            $table->date('due_date')->nullable();
            $table->dateTime('completed_date')->nullable();

            $table->timestamps();

            $table->index('project_id');
            $table->index('assigned_to');
            $table->index('status');
            $table->index('previous_task_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};