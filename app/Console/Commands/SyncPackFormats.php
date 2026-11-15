<?php

namespace App\Console\Commands;

use App\Services\Minecraft\PackFormatSyncService;
use Illuminate\Console\Command;

class SyncPackFormats extends Command
{
    protected $signature = 'pack-formats:sync {--trigger=scheduler : Trigger label stored in the sync log}';

    protected $description = 'Synchronize Minecraft Java Edition resource pack formats from the wiki';

    public function handle(PackFormatSyncService $sync): int
    {
        $log = $sync->syncFromWiki((string) $this->option('trigger'));

        if ($log->status !== 'success') {
            $this->error(__('Synchronization failed: :message', ['message' => $log->error ?? __('unknown error')]));

            return self::FAILURE;
        }

        $this->info(__('Synchronization complete: :added added, :updated updated, :removed removed.', [
            'added' => $log->records_added,
            'updated' => $log->records_updated,
            'removed' => $log->records_removed,
        ]));

        return self::SUCCESS;
    }
}
