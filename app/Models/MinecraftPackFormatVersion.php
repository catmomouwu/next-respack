<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MinecraftPackFormatVersion extends Model
{
    protected $fillable = [
        'minecraft_pack_format_id', 'edition', 'version_name', 'version_type',
        'version_channel', 'is_snapshot', 'is_prerelease', 'is_release_candidate',
        'release_date', 'source_url', 'source_updated_at', 'is_manual',
    ];

    protected function casts(): array
    {
        return [
            'is_snapshot' => 'boolean',
            'is_prerelease' => 'boolean',
            'is_release_candidate' => 'boolean',
            'is_manual' => 'boolean',
            'release_date' => 'date',
            'source_updated_at' => 'datetime',
        ];
    }

    public function format(): BelongsTo
    {
        return $this->belongsTo(MinecraftPackFormat::class, 'minecraft_pack_format_id');
    }
}
