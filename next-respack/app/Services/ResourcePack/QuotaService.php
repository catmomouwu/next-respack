<?php

namespace App\Services\ResourcePack;

use App\Models\ResourcePackVersion;
use App\Models\User;
use App\Models\UserQuota;
use Illuminate\Support\Facades\DB;

class QuotaService
{
    public function check(User $user, int $bytes): bool
    {
        $quota = $user->storage_quota ?: (int) config('nextrespack.default_quota_mb', 2048) * 1024 * 1024;
        $used = $user->ensureQuota()->used_bytes;

        return ($used + $bytes) <= $quota;
    }

    /**
     * Reserve quota atomically inside the current transaction.
     * @throws \Illuminate\Validation\ValidationException
     */
    public function reserve(User $user, int $bytes): void
    {
        $quota = UserQuota::query()->where('user_id', $user->id)->lockForUpdate()->first();

        if (! $quota) {
            $quota = UserQuota::create(['user_id' => $user->id, 'used_bytes' => 0, 'pack_count' => 0]);
            $quota = UserQuota::query()->whereKey($quota->id)->lockForUpdate()->firstOrFail();
        }

        $limit = $user->storage_quota ?: (int) config('nextrespack.default_quota_mb', 2048) * 1024 * 1024;

        if ($quota->used_bytes + $bytes > $limit) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'file' => __('Storage quota exceeded. Used :used of :limit.', [
                    'used' => nr_bytes($quota->used_bytes),
                    'limit' => nr_bytes($limit),
                ]),
            ]);
        }

        $quota->used_bytes += $bytes;
        $quota->save();
    }

    public function release(User $user, int $bytes): void
    {
        $quota = UserQuota::query()->where('user_id', $user->id)->lockForUpdate()->first();
        if ($quota) {
            $quota->used_bytes = max(0, $quota->used_bytes - $bytes);
            $quota->save();
        }
    }

    public function recalculate(User $user): void
    {
        DB::transaction(function () use ($user) {
            $quota = UserQuota::query()->where('user_id', $user->id)->lockForUpdate()->firstOrCreate(
                ['user_id' => $user->id],
                ['used_bytes' => 0, 'pack_count' => 0]
            );

            $used = (int) \App\Models\ResourcePackVersion::query()
                ->withoutTrashed()
                ->whereHas('pack', fn ($q) => $q->withoutTrashed()->where('user_id', $user->id))
                ->sum('file_size');

            $packs = (int) \App\Models\ResourcePack::query()
                ->where('user_id', $user->id)
                ->count();

            $quota->used_bytes = $used;
            $quota->pack_count = $packs;
            $quota->save();
        });
    }

    public function usage(User $user): array
    {
        $quota = $user->ensureQuota();
        $limit = $user->storage_quota ?: (int) config('nextrespack.default_quota_mb', 2048) * 1024 * 1024;

        return [
            'used' => (int) $quota->used_bytes,
            'limit' => $limit,
            'remaining' => max(0, $limit - (int) $quota->used_bytes),
            'count' => (int) $quota->pack_count,
            'percent' => $limit > 0 ? min(100, round(((int) $quota->used_bytes / $limit) * 100, 1)) : 0,
        ];
    }
}
