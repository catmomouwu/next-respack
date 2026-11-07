<?php

namespace App\Http\Controllers;

use App\Models\DownloadLog;
use App\Models\ResourcePack;
use App\Models\ResourcePackVersion;
use App\Services\Minecraft\PackFormatLookup;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(PackFormatLookup $formats): View
    {
        $stats = [
            'packs' => ResourcePack::query()->where('status', ResourcePack::STATUS_ACTIVE)->count(),
            'downloads' => DownloadLog::query()->count(),
            'formats' => $formats->hasData() ? \App\Models\MinecraftPackFormat::query()->count() : 0,
            'versions' => $formats->hasData() ? \App\Models\MinecraftPackFormatVersion::query()->count() : 0,
        ];

        $latestPacks = ResourcePack::query()
            ->where('status', ResourcePack::STATUS_ACTIVE)
            ->with(['currentVersion', 'user'])
            ->latest()
            ->limit(6)
            ->get();

        $latestFormats = $formats->latestFormats(8)->load('versions');

        return view('home', compact('stats', 'latestPacks', 'latestFormats'));
    }
}
