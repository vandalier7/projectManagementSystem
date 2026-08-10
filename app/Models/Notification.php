<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'user_id',
        'created_by',
        'type',
        'title',
        'body',
        'action_url',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reads()
    {
        return $this->hasMany(NotificationRead::class);
    }

    // Read state for a specific user (single row, may not exist yet)
    public function readFor($userId)
    {
        return $this->reads()->where('user_id', $userId);
    }

    /**
     * Scope: all notifications visible to a given user, across every
     * project they're a member of (project 1 / General always included
     * since every user is auto-member there), joined with that user's
     * read state.
     *
     * $projectIds should be the list of project ids the user belongs to.
     */
    public function scopeForUser(Builder $query, int $userId, ?array $projectIds): Builder
    {
        return $query
            ->when($projectIds !== null, fn ($q) => $q->whereIn('project_id', $projectIds))
            ->where(function (Builder $q) use ($userId) {
                $q->whereNull('user_id')
                ->orWhere('user_id', $userId);
            })
            ->with(['reads' => function ($q) use ($userId) {
                $q->where('user_id', $userId);
            }])
            ->orderByDesc('created_at');
    }

    // Scope: only unread for a given user (no read row, or read_at null)
    public function scopeUnreadForUser(Builder $query, int $userId): Builder
    {
        return $query->whereDoesntHave('reads', function (Builder $q) use ($userId) {
            $q->where('user_id', $userId)->whereNotNull('read_at');
        });
    }
}