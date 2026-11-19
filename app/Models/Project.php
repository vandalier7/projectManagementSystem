<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;




class Project extends Model
{
    protected $fillable = [
         'name',
         'description',
         'status',
         'start_date',
         'end_date',
         'budget',
         'spent',
         'lead_id',
         'created_by',
         'task_default_submission_mode',
         'logo_path',
         'banner_path',
         'theme_color',
         'display_deleted_user_names',
     ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'budget' => 'decimal:2',
        'spent' => 'decimal:2',
        'is_system' => 'boolean',
    ];

    protected $appends = ['logo_url', 'banner_url'];

    public function lead()
    {
        return $this->belongsTo(User::class, 'lead_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members()
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function isLedBy(User $user): bool
    {
        return $this->lead_id === $user->id;
    }

    public function hasMember(User $user): bool
    {
        return $this->members()->where('user_id', $user->id)->exists();
    }

    /**
     * Whether a new task in this project should require submission, per the
     * project's task_default_submission_mode setting.
     */
    public function defaultRequiresSubmission(): bool
    {
        return match ($this->task_default_submission_mode) {
            'no_require' => false,
            'match_last' => $this->tasks()->latest()->first()?->requires_submission ?? true,
            default => true,
        };
    }

    public function scopeVisible($query)
    {
        return $query->where('is_system', false);
    }

    public static function main(): ?self
    {
        return static::where('is_system', true)->first();
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    public function getBannerUrlAttribute(): ?string
    {
        return $this->banner_path ? Storage::disk('public')->url($this->banner_path) : null;
    }
}