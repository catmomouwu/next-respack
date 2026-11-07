<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DownloadLog;
use App\Models\MinecraftPackFormat;
use App\Models\MinecraftPackFormatSyncLog;
use App\Models\ResourcePack;
use App\Models\User;
use App\Services\Minecraft\PackFormatSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, PackFormatSyncService $sync): View
    {
        $stats = [
            'users' => User::query()->count(),
            'packs' => ResourcePack::query()->where('status', ResourcePack::STATUS_ACTIVE)->count(),
            'downloads' => DownloadLog::query()->count(),
            'downloads_30d' => DownloadLog::query()->where('created_at', '>=', now()->subDays(30))->count(),
            'formats' => MinecraftPackFormat::query()->count(),
            'storage' => (int) ResourcePack::query()->withSum('versions', 'file_size')->get()->sum('versions_sum_file_size'),
        ];

        $recentPacks = ResourcePack::query()->with(['user', 'currentVersion'])->latest()->limit(8)->get();
        $recentUsers = User::query()->latest()->limit(8)->get();
        $recentAudit = AuditLog::query()->with('actor')->latest()->limit(10)->get();
        $lastSync = MinecraftPackFormatSyncLog::query()->latest('id')->first();

        $daily = DownloadLog::query()
            ->where('created_at', '>=', now()->subDays(14))
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $chart = [];
        for ($i = 13; $i >= 0; $i--) {
            $day = now()->subDays($i)->toDateString();
            $chart[] = ['day' => $day, 'total' => (int) ($daily[$day] ?? 0)];
        }

        if (nr_setting_bool('pack_format_auto_sync', true) && $sync->isDue() && Cache::add('nr-auto-pack-format-sync', 1, 3600)) {
            dispatch(function () use ($sync) {
                $sync->syncFromWiki('auto');
            })->afterResponse();
        }

        return view('admin.dashboard', compact('stats', 'recentPacks', 'recentUsers', 'recentAudit', 'lastSync', 'chart'));
    }
}
