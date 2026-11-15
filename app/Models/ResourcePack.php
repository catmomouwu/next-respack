<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ResourcePack extends Model
{
    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_DISABLED = 'disabled';
    public const STATUS_PENDING_DELETION = 'pending_deletion';
    public const STATUS_DELETION_FAILED = 'deletion_failed';

    public const UPLOAD_TYPE_GUEST = 'guest';
    public const UPLOAD_TYPE_REGISTERED = 'registered';

    protected $fillable = [
        'public_id', 'user_id', 'upload_type', 'name', 'description', 'status', 'current_version_id',
        'guest_expires_at', 'guest_token_hash', 'guest_ip_hash',
        'delete_attempts', 'delete_error', 'delete_attempted_at',
    ];

    protected $hidden = ['guest_token_hash', 'guest_ip_hash'];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'guest_expires_at' => 'datetime',
            'delete_attempts' => 'integer',
            'delete_attempted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ResourcePackVersion::class);
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(ResourcePackVersion::class, 'current_version_id');
    }

    public function downloads(): HasMany
    {
        return $this->hasMany(DownloadLog::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->deleted_at === null
            && ! $this->isExpired();
    }

    public function isGuest(): bool
    {
        return $this->upload_type === self::UPLOAD_TYPE_GUEST || $this->user_id === null;
    }

    public function isExpired(): bool
    {
        return $this->guest_expires_at !== null && $this->guest_expires_at->isPast();
    }

    public function isPendingDeletion(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING_DELETION, self::STATUS_DELETION_FAILED], true);
    }

    public function remainingSeconds(): int
    {
        if ($this->guest_expires_at === null) {
            return 0;
        }

        return max(0, now()->diffInSeconds($this->guest_expires_at, false));
    }

    public function totalDownloads(): int
    {
        return (int) $this->versions()->sum('downloads_count');
    }

    public function totalSize(): int
    {
        return (int) $this->versions()->sum('file_size');
    }

    public function displayFormat(): string
    {
        $version = $this->currentVersion;

        return $version?->format_value ?? '—';
    }
}
