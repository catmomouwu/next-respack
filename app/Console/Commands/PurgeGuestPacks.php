<?php

namespace App\Console\Commands;

use App\Services\ResourcePack\GuestPackJanitor;
use Illuminate\Console\Command;

class PurgeGuestPacks extends Command
{
    protected $signature = 'guests:purge {--dry-run : Only report how many guest uploads have expired}';

    protected $description = 'Delete expired guest resource packs and their stored files';

    public function handle(GuestPackJanitor $janitor): int
    {
        if ($this->option('dry-run')) {
            $this->info(__('Expired guest uploads: :count', ['count' => $janitor->expiredCount()]));

            return self::SUCCESS;
        }

        $deleted = $janitor->purge();

        $this->info(__('Deleted :count expired guest uploads.', ['count' => $deleted]));

        return self::SUCCESS;
    }
}
