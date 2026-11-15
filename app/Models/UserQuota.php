<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserQuota extends Model
{
    protected $fillable = ['user_id', 'used_bytes', 'pack_count'];

    protected function casts(): array
    {
        return [
            'used_bytes' => 'integer',
            'pack_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
