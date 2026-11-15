<?php

namespace App\Http\Controllers;

use App\Models\DownloadLog;
use App\Models\ResourcePack;
use App\Models\ResourcePackVersion;
use App\Services\ResourcePack\QuotaService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, QuotaService $quota): View
    {
        $user = $request->user();
        $usage = $quota->usage($user);

        $packs = ResourcePack::query()
            ->where('user_id', $user->id)
            ->with('currentVersion')
            ->latest()
            ->limit(6)
            ->get();

        $downloads = DownloadLog::query()
            ->whereHas('pack', fn ($q) => $q->withoutTrashed()->where('user_id', $user->id))
            ->where('created_at', '>=', now()->subDays(14))
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day');

        $totalDownloads = ResourcePackVersion::query()
            ->whereHas('pack', fn ($q) => $q->withoutTrashed()->where('user_id', $user->id))
            ->sum('downloads_count');

        $packCount = ResourcePack::query()->where('user_id', $user->id)->count();

        $chart = [];
        for ($i = 13; $i >= 0; $i--) {
            $day = now()->subDays($i)->toDateString();
            $chart[] = ['day' => $day, 'total' => (int) ($downloads[$day] ?? 0)];
        }

        return view('dashboard', compact('usage', 'packs', 'totalDownloads', 'packCount', 'chart'));
    }
}
