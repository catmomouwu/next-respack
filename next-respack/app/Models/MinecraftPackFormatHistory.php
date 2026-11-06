<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MinecraftPackFormatHistory extends Model
{
    protected $table = 'minecraft_pack_format_history';

    protected $fillable = ['sync_log_id', 'dataset_version', 'snapshot', 'note'];

    public function syncLog(): BelongsTo
    {
        return $this->belongsTo(MinecraftPackFormatSyncLog::class, 'sync_log_id');
    }
}
