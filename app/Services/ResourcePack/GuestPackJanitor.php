<?php

namespace App\Services\ResourcePack;

use App\Models\ResourcePack;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class GuestPackJanitor
{
    public function __construct(private readonly UploadService $uploads) {}

    public function expiredCount(): int
    {
        return ResourcePack::query()
            ->whereNotNull('guest_expires_at')
            ->where('guest_expires_at', '<=', now())
            ->count();
    }

    public function purge(?int $limit = null): int
    {
        $query = ResourcePack::query()
            ->whereNotNull('guest_expires_at')
            ->where('guest_expires_at', '<=', now())
            ->orderBy('guest_expires_at');

        if ($limit !== null) {
            $query->limit($limit);
        }

        $deleted = 0;

        foreach ($query->get() as $pack) {
            try {
                $this->uploads->deletePack($pack);
                $pack->forceDelete();
                $deleted++;
            } catch (Throwable $e) {
                Log::warning('Unable to purge an expired guest upload.', [
                    'pack_id' => $pack->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $deleted;
    }

    /**
     * Clean up expired guest uploads without requiring a cron entry.
     * Shared hosting visitors trigger this at most once every 15 minutes.
     */
    public function purgeThrottled(int $limit = 20, int $minutes = 15): int
    {
        if (! Cache::add('guest-packs:purge', now()->getTimestamp(), now()->addMinutes($minutes))) {
            return 0;
        }

        try {
            return $this->purge($limit);
        } catch (Throwable $e) {
            Log::warning('Throttled guest upload purge failed.', ['error' => $e->getMessage()]);

            return 0;
        }
    }
}
