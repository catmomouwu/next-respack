<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DownloadLog extends Model
{
    protected $fillable = [
        'resource_pack_id', 'resource_pack_version_id', 'method', 'status_code',
        'range_header', 'bytes_sent', 'is_range', 'ip_hash',
    ];

    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'bytes_sent' => 'integer',
            'is_range' => 'boolean',
        ];
    }

    public function pack(): BelongsTo
    {
        return $this->belongsTo(ResourcePack::class, 'resource_pack_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ResourcePackVersion::class, 'resource_pack_version_id');
    }
}
