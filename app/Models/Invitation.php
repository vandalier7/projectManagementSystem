<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Invitation extends Model
{
    protected $fillable = [
        'email',
        'token',
        'system_role',
        'expires_at',
        'used_at',
        'invited_by',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function inviter()
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    /**
     * Generate a plaintext token, store its hash, return the plaintext
     * so it can be embedded in the email link (never persisted in plain form).
     */
    public static function generateToken(): array
    {
        $plaintext = Str::random(64);

        return [
            'plaintext' => $plaintext,
            'hashed' => hash('sha256', $plaintext),
        ];
    }

    public static function findValidByPlaintextToken(string $plaintext): ?self
    {
        return static::where('token', hash('sha256', $plaintext))
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();
    }
}