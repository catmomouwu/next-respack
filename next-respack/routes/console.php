<?php

use App\Services\Minecraft\PackFormatSyncService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Keep the Minecraft pack format dataset fresh. The service itself checks
// whether a sync is due (interval + auto-sync setting), so this is cheap.
Schedule::call(function () {
    app(PackFormatSyncService::class)->maybeSync();
})->hourly()->name('next-respack-pack-format-sync')->withoutOverlapping();
