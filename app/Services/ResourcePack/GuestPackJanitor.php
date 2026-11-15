<?php

namespace App\Services\ResourcePack;

use App\Models\DownloadLog;
use App\Models\ResourcePack;
use App\Services\Security\AuditLogger;
use App\Services\Storage\StorageManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class GuestPackJanitor
{
    private const LOCK_KEY = 'guests:purge:lock';

    private const THROTTLE_KEY = 'guests:purge:throttle';

    public function __construct(
        private readonly StorageManager $storage,
        private readonly AuditLogger $audit,
    ) {}

    public function expiredCount(): int
    {
        return $this->expiredQuery()->count();
    }

    public function failedCount(): int
    {
        return ResourcePack::query()
            ->where('upload_type', ResourcePack::UPLOAD_TYPE_GUEST)
            ->where('status', ResourcePack::STATUS_DELETION_FAILED)
            ->count();
    }

    /**
     * @return array{deleted:int, failed:int, locked:bool}
     */
    public function purgeBatch(int $limit = 25): array
    {
        $deleted = 0;
        $failed = 0;

        foreach ($this->expiredQuery()->limit(max(1, $limit))->get() as $pack) {
            try {
                if ($this->deletePack($pack, 'expired')) {
                    $deleted++;
                } else {
                    $failed++;
                }
            } catch (Throwable $e) {
                $failed++;
                $this->recordFailure($pack, $e->getMessage());
                Log::warning('Unable to purge an expired guest upload.', [
                    'pack_id' => $pack->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return ['deleted' => $deleted, 'failed' => $failed, 'locked' => false];
    }

    /**
     * Administrator-triggered cleanup. Uses a lock so two requests never scan
     * and delete the same batch at the same time.
     *
     * @return array{deleted:int, failed:int, locked:bool}
     */
    public function purgeNow(int $limit = 100): array
    {
        $lock = Cache::lock(self::LOCK_KEY, 120);

        if (! $lock->get()) {
            return ['deleted' => 0, 'failed' => 0, 'locked' => true];
        }

        try {
            return $this->purgeBatch($limit);
        } finally {
            $lock->release();
        }
    }

    /**
     * Shared-hosting sites may have no cron. Requests trigger a small batch at
     * most once every 15 minutes, protected by a lock.
     *
     * @return array{deleted:int, failed:int, locked:bool}
     */
    public function purgeThrottled(int $limit = 10, int $minutes = 15): array
    {
        $lock = Cache::lock(self::LOCK_KEY, 60);

        if (! $lock->get()) {
            return ['deleted' => 0, 'failed' => 0, 'locked' => true];
        }

        try {
            if (! Cache::add(self::THROTTLE_KEY, now()->getTimestamp(), now()->addMinutes($minutes))) {
                return ['deleted' => 0, 'failed' => 0, 'locked' => false];
            }

            return $this->purgeBatch($limit);
        } finally {
            $lock->release();
        }
    }

    /**
     * Remove the stored files first. Only when every file is gone is the
     * database record removed. A failed file deletion is persisted so it can
     * be retried instead of being reported as deleted.
     */
    public function deletePack(ResourcePack $pack, string $reason = 'expired'): bool
    {
        $pack->forceFill(['status' => ResourcePack::STATUS_PENDING_DELETION])->save();

        $versions = $pack->versions()->withTrashed()->get();
        $errors = [];

        foreach ($versions as $version) {
            try {
                $adapter = $this->storage->adapter($version->storage_driver);
                $adapter->delete($version->storage_path);

                if ($adapter->exists($version->storage_path)) {
                    throw new RuntimeException('The stored file still exists after deletion.');
                }
            } catch (Throwable $e) {
                $errors[] = $version->storage_path.': '.$e->getMessage();
                Log::warning('Unable to delete a guest upload file.', [
                    'pack_id' => $pack->id,
                    'version_id' => $version->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($errors !== []) {
            $this->recordFailure($pack, implode('; ', $errors));

            return false;
        }

        DB::transaction(function () use ($pack, $versions) {
            DownloadLog::query()->where('resource_pack_id', $pack->id)->delete();

            foreach ($versions as $version) {
                $version->forceDelete();
            }

            $pack->forceDelete();
        });

        $this->audit->log('guest.pack_purged', $pack, __('Expired guest upload deleted.'), [
            'reason' => $reason,
            'versions' => $versions->count(),
        ]);

        return true;
    }

    private function recordFailure(ResourcePack $pack, string $message): void
    {
        $pack->forceFill([
            'status' => ResourcePack::STATUS_DELETION_FAILED,
            'delete_attempts' => (int) $pack->delete_attempts + 1,
            'delete_error' => Str::limit($message, 1000, '…'),
            'delete_attempted_at' => now(),
        ])->save();

        $this->audit->log('guest.pack_delete_failed', $pack, __('Guest upload deletion failed and will be retried.'), [
            'attempts' => (int) $pack->delete_attempts,
        ]);
    }

    private function expiredQuery()
    {
        return ResourcePack::query()
            ->where('upload_type', ResourcePack::UPLOAD_TYPE_GUEST)
            ->whereNotNull('guest_expires_at')
            ->where('guest_expires_at', '<=', now())
            ->orderBy('guest_expires_at');
    }
}
