<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
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
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function previousTask()
    {
        return $this->belongsTo(Task::class, 'previous_task_id');
    }
}