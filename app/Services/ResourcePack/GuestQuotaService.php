<?php

namespace App\Services\ResourcePack;

use App\Models\ResourcePack;
use App\Models\ResourcePackVersion;

class GuestQuotaService
{
    public function globalLimitBytes(): int
    {
        $megabytes = nr_setting_int('guest_global_quota_mb', (int) config('nextrespack.guest_global_quota_mb', 10240));

        return max(0, $megabytes) * 1024 * 1024;
    }

    public function usedBytes(): int
    {
        return (int) ResourcePackVersion::query()
            ->withoutTrashed()
            ->whereHas('pack', fn ($query) => $query
                ->withoutTrashed()
                ->where('upload_type', ResourcePack::UPLOAD_TYPE_GUEST))
            ->sum('file_size');
    }

    public function check(int $bytes): bool
    {
        $limit = $this->globalLimitBytes();

        return $limit <= 0 || $this->usedBytes() + $bytes <= $limit;
    }

    /** @return array{used:int, limit:int, remaining:int, percent:float} */
    public function usage(): array
    {
        $used = $this->usedBytes();
        $limit = $this->globalLimitBytes();

        return [
            'used' => $used,
            'limit' => $limit,
            'remaining' => $limit <= 0 ? -1 : max(0, $limit - $used),
            'percent' => $limit > 0 ? min(100, round($used / $limit * 100, 1)) : 0.0,
        ];
    }
}
