<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StorageMigration extends Model
{
    protected $fillable = ['from_driver', 'to_driver', 'status', 'stats', 'error', 'started_at', 'finished_at'];

    protected function casts(): array
    {
        return [
            'stats' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
