<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MinecraftPackFormat extends Model
{
    protected $fillable = [
        'edition', 'format_key', 'format_major', 'format_minor', 'format_value',
        'source_url', 'source_updated_at', 'dataset_version', 'is_manual',
    ];

    protected function casts(): array
    {
        return [
            'format_major' => 'integer',
            'format_minor' => 'integer',
            'source_updated_at' => 'datetime',
            'is_manual' => 'boolean',
        ];
    }

    public static function makeKey(string $edition, int $major, ?int $minor): string
    {
        return $edition.':'.$major.':'.($minor === null ? '-' : $minor);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(MinecraftPackFormatVersion::class, 'minecraft_pack_format_id');
    }

    public function isRange(): bool
    {
        return $this->versions()->where('version_type', 'release')->exists();
    }

    public function formatLabel(): string
    {
        return $this->format_value;
    }
}
