<?php

namespace App\Console\Commands;

use App\Services\ResourcePack\GuestPackJanitor;
use Illuminate\Console\Command;

class PurgeGuestPacks extends Command
{
    protected $signature = 'guests:purge {--limit=100 : Maximum number of expired guest uploads to process} {--dry-run : Only report how many guest uploads have expired}';

    protected $description = 'Delete expired guest resource packs and their stored files';

    public function handle(GuestPackJanitor $janitor): int
    {
        if ($this->option('dry-run')) {
            $this->info(__('Expired guest uploads: :count', ['count' => $janitor->expiredCount()]));

            return self::SUCCESS;
        }

        $result = $janitor->purgeBatch(max(1, (int) $this->option('limit')));

        $this->info(__('Deleted :count expired guest uploads.', ['count' => $result['deleted']]));

        if ($result['failed'] > 0) {
            $this->error(__(':count guest upload deletions failed and were recorded for retry.', ['count' => $result['failed']]));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
