<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DownloadLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $logs = AuditLog::query()
            ->with('actor')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.trim((string) $request->q).'%';
                $q->where(fn ($w) => $w->where('action', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhereHas('actor', fn ($a) => $a->where('username', 'like', $term)));
            })
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->action))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        $actions = AuditLog::query()->select('action')->distinct()->orderBy('action')->pluck('action');

        $downloads = DownloadLog::query()
            ->with(['pack', 'version'])
            ->latest()
            ->paginate(30, ['*'], 'downloads_page')
            ->withQueryString();

        return view('admin.logs.index', compact('logs', 'actions', 'downloads'));
    }
}
