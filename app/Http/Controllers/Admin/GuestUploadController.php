<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ResourcePack;
use App\Services\ResourcePack\GuestPackJanitor;
use App\Services\ResourcePack\GuestQuotaService;
use App\Services\Security\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GuestUploadController extends Controller
{
    public function index(Request $request, GuestPackJanitor $janitor, GuestQuotaService $quota): View
    {
        $packs = ResourcePack::query()
            ->where('upload_type', ResourcePack::UPLOAD_TYPE_GUEST)
            ->with('currentVersion')
            ->withCount('versions')
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.trim((string) $request->q).'%';
                $query->where(fn ($inner) => $inner
                    ->where('name', 'like', $term)
                    ->orWhereHas('currentVersion', fn ($version) => $version->where('original_filename', 'like', $term)));
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                match ((string) $request->status) {
                    'expired' => $query->whereNotNull('guest_expires_at')->where('guest_expires_at', '<=', now()),
                    'failed' => $query->where('status', ResourcePack::STATUS_DELETION_FAILED),
                    'active' => $query->where('status', ResourcePack::STATUS_ACTIVE)->where('guest_expires_at', '>', now()),
                    'disabled' => $query->where('status', ResourcePack::STATUS_DISABLED),
                    default => $query->where('status', (string) $request->status),
                };
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $failures = ResourcePack::query()
            ->where('upload_type', ResourcePack::UPLOAD_TYPE_GUEST)
            ->where('status', ResourcePack::STATUS_DELETION_FAILED)
            ->orderByDesc('delete_attempted_at')
            ->limit(20)
            ->get();

        $stats = [
            'total' => ResourcePack::query()->where('upload_type', ResourcePack::UPLOAD_TYPE_GUEST)->count(),
            'expired' => $janitor->expiredCount(),
            'failed' => $janitor->failedCount(),
            'quota' => $quota->usage(),
        ];

        return view('admin.guests.index', compact('packs', 'failures', 'stats'));
    }

    public function destroy(ResourcePack $pack, GuestPackJanitor $janitor, AuditLogger $audit): RedirectResponse
    {
        abort_unless($pack->isGuest(), 404);

        $audit->log('admin.guest_deleted', $pack, __('Administrator deleted a guest upload.'));

        if (! $janitor->deletePack($pack, 'admin')) {
            return back()->withErrors([
                'guest' => __('The stored file could not be deleted yet. The upload is queued for retry.'),
            ]);
        }

        return back()->with('status', __('Guest upload and its stored file were deleted.'));
    }

    public function toggle(ResourcePack $pack, AuditLogger $audit): RedirectResponse
    {
        abort_unless($pack->isGuest(), 404);

        $pack->status = $pack->status === ResourcePack::STATUS_ACTIVE
            ? ResourcePack::STATUS_DISABLED
            : ResourcePack::STATUS_ACTIVE;
        $pack->save();

        $audit->log('admin.guest_status_changed', $pack, __('Administrator changed a guest download link status.'), [
            'status' => $pack->status,
        ]);

        return back()->with('status', __('Guest download link status updated.'));
    }

    public function cleanup(GuestPackJanitor $janitor, AuditLogger $audit): RedirectResponse
    {
        $result = $janitor->purgeNow();

        if ($result['locked']) {
            return back()->with('status', __('A guest upload cleanup is already running. Please try again in a moment.'));
        }

        $audit->log('admin.guest_cleanup', null, __('Manual guest upload cleanup executed.'), $result);

        $message = __('Deleted :deleted expired guest uploads.', ['deleted' => $result['deleted']]);

        if ($result['failed'] > 0) {
            return back()->with('status', $message)->withErrors([
                'guest' => __(':failed deletions failed and were recorded for retry.', ['failed' => $result['failed']]),
            ]);
        }

        return back()->with('status', $message);
    }
}
