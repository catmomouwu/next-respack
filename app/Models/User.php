<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, MustVerifyEmailTrait, Notifiable, SoftDeletes;

    protected $fillable = [
        'username', 'email', 'password', 'role', 'is_active', 'storage_quota', 'locale', 'timezone',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'storage_quota' => 'integer',
        ];
    }

    public function quota(): HasOne
    {
        return $this->hasOne(UserQuota::class);
    }

    public function resourcePacks(): HasMany
    {
        return $this->hasMany(ResourcePack::class);
    }

    public function downloadLogs(): HasMany
    {
        return $this->hasMany(DownloadLog::class);
    }

    public function isAdmin(): bool
    {
        return $this->role->atLeast(UserRole::Admin);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    public function ensureQuota(): UserQuota
    {
        return $this->quota()->firstOrCreate([], [
            'used_bytes' => 0,
            'pack_count' => 0,
        ]);
    }

    public function usedBytes(): int
    {
        return (int) ($this->quota?->used_bytes ?? 0);
    }

    public function displayName(): string
    {
        return $this->username ?: Str::before($this->email, '@');
    }
}
