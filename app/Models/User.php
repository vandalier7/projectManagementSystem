<?php

namespace App\Models;

use App\Models\Project;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;
    /** @use HasFactory<UserFactory> */
    

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'email',
        'password',
        'full_name',
        'phone',
        'department',
        'system_role',
        'status',
        'last_login',
        'login_count',
        'avatar_url',
        'profile_completed',
        'must_change_password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'last_login' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->system_role === 'admin';
    }

    protected static function booted(): void
    {
        static::created(function (User $user) {
            $systemProjects = Project::where('is_system', true)->get();

            foreach ($systemProjects as $project) {
                $project->members()->create([
                    'user_id' => $user->id,
                    'joined_at' => now(),
                ]);
            }
        });
    }

    public function projectMemberships()
    {
        return $this->hasMany(\App\Models\ProjectMember::class);
    }
}