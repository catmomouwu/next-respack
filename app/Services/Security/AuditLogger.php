<?php

namespace App\Services\Security;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuditLogger
{
    public function log(string $action, ?Model $subject = null, ?string $description = null, array $metadata = []): void
    {
        try {
            AuditLog::create([
                'actor_id' => Auth::id(),
                'action' => $action,
                'subject_type' => $subject ? $subject::class : null,
                'subject_id' => $subject?->getKey(),
                'description' => $description,
                'metadata' => $metadata ?: null,
                'ip_hash' => request() ? substr(hash('sha256', request()->ip().'|'.config('app.key')), 0, 40) : null,
            ]);
        } catch (Throwable $e) {
            Log::warning('Unable to write audit log: '.$e->getMessage());
        }
    }
}
