<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MinecraftPackFormatSyncLog extends Model
{
    protected $fillable = [
        'status', 'trigger', 'source_url', 'parser_version', 'dataset_version',
        'records_added', 'records_updated', 'records_unchanged', 'records_removed',
        'changes', 'raw_payload', 'error', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
