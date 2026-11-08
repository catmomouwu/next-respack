<?php

namespace App\Http\Controllers;

use App\Models\ResourcePack;
use App\Models\ResourcePackVersion;
use App\Services\Download\DownloadTokenService;
use App\Services\ResourcePack\GuestPackJanitor;
use App\Services\ResourcePack\UploadService;
use App\Services\Security\AuditLogger;
use App\Services\Security\CaptchaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class GuestUploadController extends Controller
{
    public function create(Request $request, GuestPackJanitor $janitor): View|RedirectResponse
    {
        if ($request->user()) {
            return redirect()->route('packs.create')
                ->with('status', __('You are signed in, so uploads are kept permanently.'));
        }

        $janitor->purgeThrottled();

        return view('guest.upload', [
            'maxMb' => $this->guestMaxMb(),
            'retentionDays' => $this->retentionDays(),
        ]);
    }

    public function store(
        Request $request,
        UploadService $uploads,
        CaptchaService $captcha,
        AuditLogger $audit,
    ): RedirectResponse {
        if ($request->user()) {
            return redirect()->route('packs.create');
        }

        $request->validate([
            'file' => ['required', 'file'],
            'name' => ['nullable', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'captcha' => ['required', 'string', 'max:10'],
        ]);

        if (! $captcha->verify($request->input('captcha'))) {
            throw ValidationException::withMessages([
                'captcha' => __('The captcha answer is incorrect. Please try again.'),
            ]);
        }

        $pack = $uploads->createGuestPack($request->file('file'), [
            'name' => (string) $request->input('name', ''),
            'description' => (string) $request->input('description', ''),
        ]);

        $audit->log('guest.pack_created', $pack, __('Guest resource pack uploaded.'), [
            'version_id' => $pack->current_version_id,
            'size' => $pack->currentVersion?->file_size,
            'expires_at' => $pack->guest_expires_at?->toIso8601String(),
        ]);

        return redirect()->to(URL::temporarySignedRoute(
            'guest.upload.show',
            $pack->guest_expires_at ?? now()->addDays($this->retentionDays()),
            ['pack' => $pack->public_id],
        ))->with('status', __('Guest upload completed.'));
    }

    public function show(Request $request, string $pack, DownloadTokenService $tokens): View
    {
        $model = ResourcePack::query()
            ->where('public_id', $pack)
            ->whereNull('user_id')
            ->firstOrFail();

        if ($model->isExpired()) {
            abort(410, __('This guest upload has expired and was deleted.'));
        }

        $model->load(['versions' => fn ($q) => $q->latest('version_number'), 'currentVersion']);

        $versions = $model->versions->map(fn (ResourcePackVersion $version) => [
            'model' => $version,
            'url' => $tokens->url($version),
        ]);

        return view('guest.show', [
            'pack' => $model,
            'versions' => $versions,
            'retentionDays' => $this->retentionDays(),
        ]);
    }

    private function guestMaxMb(): int
    {
        return max(1, (int) config('nextrespack.guest_upload_mb', 200));
    }

    private function retentionDays(): int
    {
        return max(1, (int) config('nextrespack.guest_retention_days', 7));
    }
}
