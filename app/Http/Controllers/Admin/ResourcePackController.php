<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ResourcePack;
use App\Services\ResourcePack\UploadService;
use App\Services\Security\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResourcePackController extends Controller
{
    public function index(Request $request): View
    {
        $packs = ResourcePack::query()
            ->with(['user', 'currentVersion'])
            ->withCount('versions')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.trim((string) $request->q).'%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)
                    ->orWhereHas('user', fn ($u) => $u->where('username', 'like', $term)->orWhere('email', 'like', $term)));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.packs.index', compact('packs'));
    }

    public function toggleStatus(Request $request, ResourcePack $pack, AuditLogger $audit): RedirectResponse
    {
        $pack->status = $pack->status === ResourcePack::STATUS_ACTIVE ? ResourcePack::STATUS_DISABLED : ResourcePack::STATUS_ACTIVE;
        $pack->save();
        $audit->log('admin.pack_status_changed', $pack, __('Administrator changed resource pack status.'), ['status' => $pack->status]);

        return back()->with('status', __('Resource pack status updated.'));
    }

    public function destroy(ResourcePack $pack, UploadService $uploads, AuditLogger $audit): RedirectResponse
    {
        $audit->log('admin.pack_deleted', $pack, __('Administrator deleted a resource pack.'), ['versions' => $pack->versions()->count()]);
        $uploads->deletePack($pack);

        return back()->with('status', __('Resource pack and its files were deleted.'));
    }
}
