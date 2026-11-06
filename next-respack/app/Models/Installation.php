<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Installation extends Model
{
    protected $fillable = ['version', 'installed_at', 'admin_user_id', 'meta'];

    protected function casts(): array
    {
        return [
            'installed_at' => 'datetime',
            'meta' => 'array',
        ];
    }
}
