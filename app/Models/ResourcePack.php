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

    protected $fillable = ['public_id', 'user_id', 'name', 'description', 'status', 'current_version_id'];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
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
        return $this->status === self::STATUS_ACTIVE && $this->deleted_at === null;
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
