<?php

namespace App\Http\Controllers;

use App\Models\DownloadLog;
use App\Models\ResourcePack;
use App\Models\ResourcePackVersion;
use App\Services\Download\DownloadTokenService;
use App\Services\Download\ServerPropertiesGenerator;
use App\Services\ResourcePack\QuotaService;
use App\Services\ResourcePack\UploadService;
use App\Services\Security\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ResourcePackController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $packs = ResourcePack::query()
            ->where('user_id', $user->id)
            ->with('currentVersion')
            ->withCount('versions')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.trim((string) $request->q).'%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('description', 'like', $term));
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('packs.index', compact('packs'));
    }

    public function create(Request $request, QuotaService $quota): View
    {
        return view('packs.create', ['usage' => $quota->usage($request->user())]);
    }

    public function store(Request $request, UploadService $uploads, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file'],
            'name' => ['nullable', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'allow_duplicate' => ['nullable', 'boolean'],
        ]);

        $pack = $uploads->createPack($request->user(), $request->file('file'), [
            'name' => $data['name'] ?? '',
            'description' => $data['description'] ?? '',
            'allow_duplicate' => (bool) ($data['allow_duplicate'] ?? false),
        ]);

        $audit->log('pack.created', $pack, __('Resource pack uploaded.'), [
            'version_id' => $pack->current_version_id,
            'size' => $pack->currentVersion?->file_size,
        ]);

        return redirect()->route('packs.show', $pack)->with('status', __('Resource pack uploaded and validated.'));
    }

    public function show(Request $request, ResourcePack $pack, DownloadTokenService $tokens, ServerPropertiesGenerator $properties, QuotaService $quota): View
    {
        $this->authorizePack($request, $pack);

        $pack->load(['versions' => fn ($q) => $q->latest('version_number'), 'currentVersion']);

        $versions = $pack->versions->map(fn (ResourcePackVersion $version) => [
            'model' => $version,
            'token' => $tokens->tokenFor($version),
            'url' => $tokens->url($version),
        ]);

        $downloads = DownloadLog::query()
            ->where('resource_pack_id', $pack->id)
            ->latest()
            ->limit(10)
            ->get();

        $daily = DownloadLog::query()
            ->where('resource_pack_id', $pack->id)
            ->where('created_at', '>=', now()->subDays(14))
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $chart = [];
        for ($i = 13; $i >= 0; $i--) {
            $day = now()->subDays($i)->toDateString();
            $chart[] = ['day' => $day, 'total' => (int) ($daily[$day] ?? 0)];
        }

        $serverProperties = null;
        $serverPropertiesWarning = $properties->warning($pack);

        try {
            $serverProperties = $serverPropertiesWarning === null ? $properties->generate($pack, true) : null;
        } catch (\Throwable) {
            $serverProperties = null;
        }

        return view('packs.show', [
            'pack' => $pack,
            'versions' => $versions,
            'downloads' => $downloads,
            'chart' => $chart,
            'usage' => $quota->usage($request->user()),
            'serverProperties' => $serverProperties,
            'serverPropertiesWarning' => $serverPropertiesWarning,
        ]);
    }

    public function update(Request $request, ResourcePack $pack, AuditLogger $audit): RedirectResponse
    {
        $this->authorizePack($request, $pack);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $pack->update(['name' => $data['name'], 'description' => $data['description'] ?? null]);
        $audit->log('pack.updated', $pack, __('Resource pack details updated.'));

        return back()->with('status', __('Resource pack updated.'));
    }

    public function toggleStatus(Request $request, ResourcePack $pack, AuditLogger $audit): RedirectResponse
    {
        $this->authorizePack($request, $pack);

        $pack->status = $pack->status === ResourcePack::STATUS_ACTIVE ? ResourcePack::STATUS_DISABLED : ResourcePack::STATUS_ACTIVE;
        $pack->save();

        $audit->log('pack.status_changed', $pack, __('Resource pack status changed.'), ['status' => $pack->status]);

        return back()->with('status', __('Resource pack status updated. All download links now follow this status.'));
    }

    public function destroy(Request $request, ResourcePack $pack, UploadService $uploads, AuditLogger $audit): RedirectResponse
    {
        $this->authorizePack($request, $pack);

        $audit->log('pack.deleted', $pack, __('Resource pack deleted.'), ['versions' => $pack->versions()->count()]);
        $uploads->deletePack($pack);

        return redirect()->route('packs.index')->with('status', __('Resource pack and all of its versions were deleted. Download links are now invalid.'));
    }

    public function createVersion(Request $request, ResourcePack $pack): View
    {
        $this->authorizePack($request, $pack);

        return view('packs.versions.create', compact('pack'));
    }

    public function storeVersion(Request $request, ResourcePack $pack, UploadService $uploads, AuditLogger $audit): RedirectResponse
    {
        $this->authorizePack($request, $pack);

        $request->validate([
            'file' => ['required', 'file'],
            'description' => ['nullable', 'string', 'max:2000'],
            'allow_duplicate' => ['nullable', 'boolean'],
        ]);

        $version = $uploads->addVersion($request->user(), $pack, $request->file('file'), [
            'description' => $request->input('description', ''),
            'allow_duplicate' => (bool) $request->input('allow_duplicate', false),
        ]);

        $audit->log('pack.version_added', $version, __('New resource pack version uploaded.'), [
            'pack_id' => $pack->id,
            'version_id' => $version->id,
        ]);

        return redirect()->route('packs.show', $pack)->with('status', __('New version uploaded. Previous files were kept unchanged.'));
    }

    public function toggleVersion(Request $request, ResourcePack $pack, ResourcePackVersion $version, AuditLogger $audit): RedirectResponse
    {
        $this->authorizePack($request, $pack);
        abort_unless($version->resource_pack_id === $pack->id, 404);

        $version->download_enabled = ! $version->download_enabled;
        $version->save();
        $audit->log('pack.version_link_toggled', $version, __('Version download link toggled.'), ['enabled' => $version->download_enabled]);

        return back()->with('status', $version->download_enabled ? __('Download link enabled.') : __('Download link disabled. Existing links now return 403.'));
    }

    public function resetToken(Request $request, ResourcePack $pack, ResourcePackVersion $version, DownloadTokenService $tokens, AuditLogger $audit): RedirectResponse
    {
        $this->authorizePack($request, $pack);
        abort_unless($version->resource_pack_id === $pack->id, 404);

        $tokens->reset($version);
        $audit->log('pack.version_token_reset', $version, __('Download token reset. The previous link is invalid.'));

        return back()->with('status', __('Download token reset. The old link no longer works.'));
    }

    public function destroyVersion(Request $request, ResourcePack $pack, ResourcePackVersion $version, UploadService $uploads, AuditLogger $audit): RedirectResponse
    {
        $this->authorizePack($request, $pack);
        abort_unless($version->resource_pack_id === $pack->id, 404);

        $audit->log('pack.version_deleted', $version, __('Resource pack version deleted.'), ['pack_id' => $pack->id]);
        $uploads->deleteVersion($version);

        return back()->with('status', __('Version deleted. Its download link is now invalid.'));
    }

    public function serverProperties(Request $request, ResourcePack $pack, ServerPropertiesGenerator $generator): Response
    {
        $this->authorizePack($request, $pack);

        $require = (bool) $request->boolean('require', true);

        try {
            $content = $generator->generate($pack, $require);
        } catch (\Throwable $e) {
            abort(422, $e->getMessage());
        }

        $filename = Str::slug($pack->name) ?: 'resource-pack';

        return response($content, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="server-'.$filename.'.properties"',
        ]);
    }

    private function authorizePack(Request $request, ResourcePack $pack): void
    {
        if ($pack->user_id !== $request->user()->id && ! $request->user()->isAdmin()) {
            abort(403);
        }
    }
}
