<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Services\TaskLifecycle\TaskStatus;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'project_id',
        'assigned_to',
        'priority',
        'requires_submission',
        'status',
        'previous_task_id',
        'submission_link',
        'submission_notes',
        'submitted_at',
        'review_comment',
        'reviewed_by',
        'reviewed_at',
        'due_date',
        'completed_date',
        'sort_order',
    ];

    protected $casts = [
        'requires_submission' => 'boolean',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'due_date' => 'date',
        'completed_date' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to')->withTrashed();
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function previousTask()
    {
        return $this->belongsTo(Task::class, 'previous_task_id');
    }

    public function isAssignedTo(User $user): bool
    {
        return $this->assigned_to === $user->id;
    }

    public function canBeReviewedBy(User $user): bool
    {
        return $this->project->isLedBy($user) || $user->isAdmin();
    }

    public function canBeEditedBy(User $user): bool
    {
        if (in_array($this->status, TaskStatus::LOCKED, true) && !$user->isAdmin()) {
            return false;
        }
        return $this->project->isLedBy($user) || $user->isAdmin();
    }

    public function canBeSubmittedBy(User $user): bool
    {
        return $this->isAssignedTo($user);
    }

    public function canBeSelfCompletedBy(User $user): bool
    {
        if (!$this->isAssignedTo($user)) {
            return false;
        }

        return $this->project->isLedBy($user) || !$this->requires_submission;
    }

    protected static function booted(): void
    {
        static::saving(function (Task $task) {
            if ($task->project->is_system) {
                $task->requires_submission = false;
            }
        });
    }
}