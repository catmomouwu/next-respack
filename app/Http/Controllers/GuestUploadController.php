<?php

namespace App\Http\Controllers;

use App\Models\ResourcePack;
use App\Services\Download\DownloadTokenService;
use App\Services\Download\ServerPropertiesGenerator;
use App\Services\ResourcePack\GuestPackJanitor;
use App\Services\ResourcePack\GuestQuotaService;
use App\Services\ResourcePack\UploadService;
use App\Services\Security\AuditLogger;
use App\Services\Security\CaptchaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class GuestUploadController extends Controller
{
    public function create(Request $request, GuestPackJanitor $janitor, GuestQuotaService $quota): View|RedirectResponse
    {
        if ($request->user()) {
            return redirect()->route('packs.create')
                ->with('status', __('You are signed in, so uploads are kept permanently.'));
        }

        abort_unless($this->guestEnabled(), 404);

        $janitor->purgeThrottled();

        return view('guest.upload', [
            'maxMb' => $this->guestMaxMb(),
            'retentionDays' => $this->retentionDays(),
            'captchaEnabled' => $this->captchaEnabled(),
            'guestQuota' => $quota->usage(),
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

        abort_unless($this->guestEnabled(), 404);

        $captchaEnabled = $this->captchaEnabled();

        $request->validate([
            'file' => ['required', 'file'],
            'name' => ['nullable', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'captcha' => [$captchaEnabled ? 'required' : 'nullable', 'string', 'max:10'],
        ]);

        if ($captchaEnabled && ! $captcha->verify($request->input('captcha'))) {
            throw ValidationException::withMessages([
                'captcha' => __('The captcha answer is incorrect. Please try again.'),
            ]);
        }

        $managementToken = bin2hex(random_bytes(32));
        $ipHash = substr(hash('sha256', $request->ip().'|'.config('app.key')), 0, 64);

        $lock = Cache::lock('guest-upload:'.$ipHash, 300);

        if (! $lock->get()) {
            throw ValidationException::withMessages([
                'file' => __('Another upload from your network is still in progress. Please wait for it to finish.'),
            ]);
        }

        try {
            $pack = $uploads->createGuestPack($request->file('file'), [
                'name' => (string) $request->input('name', ''),
                'description' => (string) $request->input('description', ''),
                'management_token' => $managementToken,
                'ip_hash' => $ipHash,
            ]);
        } finally {
            $lock->release();
        }

        $audit->log('guest.pack_created', $pack, __('Guest resource pack uploaded.'), [
            'version_id' => $pack->current_version_id,
            'size' => $pack->currentVersion?->file_size,
            'expires_at' => $pack->guest_expires_at?->toIso8601String(),
            'ip_hash' => $ipHash,
        ]);

        $expiresAt = $pack->guest_expires_at ?? now()->addDays($this->retentionDays());

        return redirect()->to(URL::temporarySignedRoute(
            'guest.upload.show',
            $expiresAt,
            ['pack' => $pack->public_id, 'manage' => $managementToken],
        ))->with('status', __('Guest upload completed.'));
    }

    public function show(
        Request $request,
        string $pack,
        DownloadTokenService $tokens,
        ServerPropertiesGenerator $properties,
    ): View {
        $model = ResourcePack::query()
            ->where('public_id', $pack)
            ->where('upload_type', ResourcePack::UPLOAD_TYPE_GUEST)
            ->firstOrFail();

        if ($model->isExpired()) {
            abort(410, __('This guest upload has expired and was deleted.'));
        }

        $managementToken = (string) $request->query('manage', '');

        if ($managementToken === '' || ! hash_equals((string) $model->guest_token_hash, hash('sha256', $managementToken))) {
            abort(403);
        }

        $model->load(['versions' => fn ($q) => $q->latest('version_number'), 'currentVersion']);
        $version = $model->currentVersion;
        $downloadUrl = $version ? $tokens->url($version) : null;

        return view('guest.show', [
            'pack' => $model,
            'version' => $version,
            'downloadUrl' => $downloadUrl,
            'managementUrl' => route('guest.manage', ['token' => $managementToken]),
            'serverProperties' => $this->serverProperties($properties, $model),
            'serverPropertiesWarning' => $properties->warning($model),
            'retentionDays' => $this->retentionDays(),
        ]);
    }

    public function manage(string $token, DownloadTokenService $tokens, ServerPropertiesGenerator $properties): View
    {
        $pack = $this->findByToken($token);
        $pack->load(['currentVersion']);
        $version = $pack->currentVersion;

        return view('guest.manage', [
            'pack' => $pack,
            'version' => $version,
            'token' => $token,
            'downloadUrl' => $version ? $tokens->url($version) : null,
            'serverProperties' => $this->serverProperties($properties, $pack),
            'serverPropertiesWarning' => $properties->warning($pack),
            'remainingSeconds' => $pack->remainingSeconds(),
            'retentionDays' => $this->retentionDays(),
        ]);
    }

    public function updateManage(Request $request, string $token, AuditLogger $audit): RedirectResponse
    {
        $pack = $this->findByToken($token);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
        ]);

        $pack->name = $data['name'];
        $pack->save();

        $audit->log('guest.pack_renamed', $pack, __('Guest upload renamed.'), ['name' => $pack->name]);

        return back()->with('status', __('The display name has been updated.'));
    }

    public function destroyManage(string $token, GuestPackJanitor $janitor, AuditLogger $audit): RedirectResponse
    {
        $pack = $this->findByToken($token);

        if (! $janitor->deletePack($pack, 'guest_requested')) {
            $audit->log('guest.pack_delete_failed', $pack, __('Guest upload deletion failed and will be retried.'));

            return back()->withErrors([
                'delete' => __('The stored file could not be deleted yet. The upload is queued for retry; please try again later.'),
            ]);
        }

        return redirect()->route('home')->with('status', __('The guest upload and its download link were deleted.'));
    }

    private function findByToken(string $token): ResourcePack
    {
        if ($token === '' || strlen($token) < 32 || strlen($token) > 128) {
            abort(404);
        }

        $pack = ResourcePack::query()
            ->where('upload_type', ResourcePack::UPLOAD_TYPE_GUEST)
            ->where('guest_token_hash', hash('sha256', $token))
            ->first();

        if (! $pack) {
            abort(404);
        }

        if ($pack->isExpired()) {
            abort(410, __('This guest upload has expired and was deleted.'));
        }

        return $pack;
    }

    private function serverProperties(ServerPropertiesGenerator $properties, ResourcePack $pack): ?string
    {
        try {
            return $properties->generate($pack, true);
        } catch (\Throwable) {
            return null;
        }
    }

    private function guestEnabled(): bool
    {
        return nr_setting_bool('guest_uploads_enabled', true);
    }

    private function captchaEnabled(): bool
    {
        return nr_setting_bool('guest_captcha_enabled', true);
    }

    private function guestMaxMb(): int
    {
        return min(200, max(1, nr_setting_int('guest_upload_mb', (int) config('nextrespack.guest_upload_mb', 200))));
    }

    private function retentionDays(): int
    {
        return max(1, nr_setting_int('guest_retention_days', (int) config('nextrespack.guest_retention_days', 7)));
    }
}
