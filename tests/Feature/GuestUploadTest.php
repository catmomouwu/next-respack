<?php

namespace Tests\Feature;

use App\Models\ResourcePack;
use App\Models\ResourcePackVersion;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Download\DownloadTokenService;
use App\Services\ResourcePack\UploadService;
use App\Services\Security\CaptchaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class GuestUploadTest extends TestCase
{
    use RefreshDatabase;

    private string $storagePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storagePath = $this->temporaryDirectory().'/packs';

        SystemSetting::set('storage_driver', 'local', 'storage');
        SystemSetting::set('storage_local_path', $this->storagePath, 'storage');
        SystemSetting::set('max_upload_mb', 32, 'uploads', 'int');
        SystemSetting::set('guest_upload_mb', 200, 'guest', 'int');
        SystemSetting::set('guest_retention_days', 7, 'guest', 'int');
        SystemSetting::set('guest_global_quota_mb', 10240, 'guest', 'int');
        SystemSetting::set('guest_uploads_enabled', true, 'guest', 'bool');
        SystemSetting::set('guest_captcha_enabled', true, 'guest', 'bool');
        SystemSetting::forgetCache();
    }

    public function test_guest_can_open_the_upload_page_with_retention_notice(): void
    {
        $response = $this->get('/upload');

        $response->assertOk();
        $response->assertSee('游客上传');
        $response->assertSee('200 MB');
        $response->assertSee('7 天');
        $response->assertSee('永久保存');
    }

    public function test_captcha_endpoint_returns_an_svg_and_stores_the_answer_in_the_session(): void
    {
        $response = $this->get('/captcha');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/svg+xml; charset=UTF-8');
        $this->assertStringContainsString('<svg', (string) $response->getContent());

        $payload = session(CaptchaService::SESSION_KEY);
        $this->assertIsArray($payload);
        $this->assertSame(5, strlen((string) $payload['code']));
    }

    public function test_wrong_captcha_is_rejected(): void
    {
        $zipPath = $this->makeValidPack();

        $this->withCaptcha('ABCDE')->post('/upload', [
            'name' => 'Guest Pack',
            'captcha' => 'ZZZZZ',
            'file' => new UploadedFile($zipPath, 'guest.zip', 'application/zip', null, true),
        ])->assertSessionHasErrors('captcha');

        $this->assertDatabaseCount('resource_packs', 0);
        $this->assertDatabaseCount('resource_pack_versions', 0);
    }

    public function test_guest_can_upload_without_login_and_receives_a_signed_result_page(): void
    {
        $zipPath = $this->makeValidPack();

        $response = $this->withCaptcha('abcde')->post('/upload', [
            'name' => 'Guest Pack',
            'captcha' => 'abcde',
            'file' => new UploadedFile($zipPath, 'guest.zip', 'application/zip', null, true),
        ]);

        $response->assertRedirect();

        $pack = ResourcePack::query()->whereNull('user_id')->firstOrFail();
        $version = $pack->currentVersion;

        $this->assertSame(ResourcePack::UPLOAD_TYPE_GUEST, $pack->upload_type);
        $this->assertNotNull($version);
        $this->assertSame('Guest Pack', $pack->name);
        $this->assertNotNull($pack->guest_expires_at);
        $this->assertTrue($pack->guest_expires_at->between(now()->addDays(6), now()->addDays(8)));
        $this->assertFileExists($this->storagePath.'/'.$version->storage_path);
        $this->assertDatabaseCount('user_quotas', 0);

        // The management token is stored as a hash only and is not in the download URL.
        $this->assertNotNull($pack->guest_token_hash);
        $this->assertNotNull($pack->guest_ip_hash);
        $this->assertSame(64, strlen($pack->guest_ip_hash));

        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('/upload/'.$pack->public_id, $location);
        $this->assertStringContainsString('signature=', $location);
        $this->assertStringContainsString('manage=', $location);

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $managementToken = (string) ($query['manage'] ?? '');
        $this->assertSame(hash('sha256', $managementToken), $pack->guest_token_hash);
        $this->assertStringNotContainsString($managementToken, route('download', ['token' => 'placeholder']));

        $this->get($location)
            ->assertOk()
            ->assertSee('Guest Pack')
            ->assertSee('管理链接')
            ->assertSee($version->sha1)
            ->assertSee('永久保存');

        $downloadToken = app(DownloadTokenService::class)->tokenFor($version);
        $this->assertStringNotContainsString($managementToken, route('download', ['token' => $downloadToken]));
        $this->get('/dl/'.$downloadToken)->assertOk();
    }

    public function test_guest_result_page_requires_a_valid_signature(): void
    {
        [$pack] = $this->makeGuestPack();

        $this->get('/upload/'.$pack->public_id)->assertForbidden();
    }

    public function test_expired_guest_download_and_pages_return_gone(): void
    {
        [$pack, $token] = $this->makeGuestPack();
        $version = $pack->currentVersion;

        $pack->forceFill(['guest_expires_at' => now()->subMinute()])->save();

        $this->get('/dl/'.app(DownloadTokenService::class)->tokenFor($version))->assertStatus(410);
        $this->get(route('guest.manage', ['token' => $token]))->assertStatus(410);

        $signed = URL::temporarySignedRoute('guest.upload.show', now()->addDay(), [
            'pack' => $pack->public_id,
            'manage' => $token,
        ]);
        $this->get($signed)->assertStatus(410);
    }

    public function test_guest_uploads_are_limited_to_200_mb(): void
    {
        $this->assertSame(200, (int) config('nextrespack.guest_upload_mb'));

        $path = $this->temporaryDirectory().'/oversized.zip';
        $handle = fopen($path, 'wb');
        ftruncate($handle, 201 * 1024 * 1024);
        fclose($handle);

        $this->withCaptcha('ABCDE')->post('/upload', [
            'captcha' => 'ABCDE',
            'file' => new UploadedFile($path, 'oversized.zip', 'application/zip', null, true),
        ])->assertSessionHasErrors('file');

        $this->assertDatabaseCount('resource_packs', 0);
    }

    public function test_site_wide_guest_quota_is_enforced(): void
    {
        $pack = ResourcePack::create([
            'public_id' => (string) Str::uuid7(),
            'user_id' => null,
            'upload_type' => ResourcePack::UPLOAD_TYPE_GUEST,
            'name' => 'Existing guest pack',
            'status' => ResourcePack::STATUS_ACTIVE,
            'guest_expires_at' => now()->addDays(3),
        ]);

        ResourcePackVersion::create([
            'public_id' => (string) Str::uuid7(),
            'resource_pack_id' => $pack->id,
            'version_number' => 1,
            'original_filename' => 'existing.zip',
            'stored_filename' => 'existing.zip',
            'storage_driver' => 'local',
            'storage_path' => 'existing.zip',
            'file_size' => 2 * 1024 * 1024,
        ]);

        SystemSetting::set('guest_global_quota_mb', 1, 'guest', 'int');
        SystemSetting::forgetCache();

        $zipPath = $this->makeValidPack();

        $this->withCaptcha('ABCDE')->post('/upload', [
            'captcha' => 'ABCDE',
            'file' => new UploadedFile($zipPath, 'guest.zip', 'application/zip', null, true),
        ])->assertSessionHasErrors('file');

        $this->assertSame(1, ResourcePack::query()->where('upload_type', ResourcePack::UPLOAD_TYPE_GUEST)->count());
    }

    public function test_management_token_can_rename_and_delete_a_guest_upload(): void
    {
        [$pack, $token] = $this->makeGuestPack();
        $version = $pack->currentVersion;

        $this->get(route('guest.manage', ['token' => $token]))
            ->assertOk()
            ->assertSee('Guest Pack')
            ->assertSee($version->sha1);

        $this->patch(route('guest.manage.update', ['token' => $token]), ['name' => 'Renamed Guest Pack'])
            ->assertRedirect();

        $this->assertSame('Renamed Guest Pack', $pack->fresh()->name);

        $this->delete(route('guest.manage.destroy', ['token' => $token]))->assertRedirect('/');

        $this->assertDatabaseMissing('resource_packs', ['id' => $pack->id]);
        $this->assertDatabaseMissing('resource_pack_versions', ['id' => $version->id]);
        $this->assertFileDoesNotExist($this->storagePath.'/'.$version->storage_path);
    }

    public function test_management_page_requires_the_secret_token(): void
    {
        [$pack] = $this->makeGuestPack();

        $this->get(route('guest.manage', ['token' => str_repeat('a', 64)]))->assertNotFound();
        $this->assertNotNull($pack->guest_token_hash);
        $this->assertStringNotContainsString(str_repeat('a', 64), (string) $pack->guest_token_hash);
    }

    public function test_cleanup_command_deletes_expired_guest_uploads_only(): void
    {
        [$expired, $expiredToken] = $this->makeGuestPack();
        [$active] = $this->makeGuestPack();

        $expired->forceFill(['guest_expires_at' => now()->subDay()])->save();

        $this->artisan('guests:purge')->assertSuccessful();

        $this->assertDatabaseMissing('resource_packs', ['id' => $expired->id]);
        $this->assertFileDoesNotExist($this->storagePath.'/'.$expired->currentVersion->storage_path);
        $this->assertDatabaseHas('resource_packs', ['id' => $active->id]);
        $this->assertFileExists($this->storagePath.'/'.$active->currentVersion->storage_path);
        $this->assertNotNull($expiredToken);
    }

    public function test_failed_deletions_are_recorded_and_can_be_retried(): void
    {
        [$pack] = $this->makeGuestPack();
        $version = $pack->currentVersion;

        // Force a remote adapter that cannot be constructed, so file deletion fails.
        $version->forceFill(['storage_driver' => 'webdav'])->save();
        $pack->forceFill(['guest_expires_at' => now()->subHour()])->save();

        $this->artisan('guests:purge')->assertExitCode(1);

        $failed = $pack->fresh();
        $this->assertNotNull($failed);
        $this->assertSame(ResourcePack::STATUS_DELETION_FAILED, $failed->status);
        $this->assertSame(1, $failed->delete_attempts);
        $this->assertNotNull($failed->delete_error);
        $this->assertNotNull($failed->delete_attempted_at);
        $this->assertFileExists($this->storagePath.'/'.$version->storage_path);

        // Fix the adapter and retry: the next run deletes the pack and the file.
        $version->forceFill(['storage_driver' => 'local'])->save();

        $this->artisan('guests:purge')->assertSuccessful();

        $this->assertDatabaseMissing('resource_packs', ['id' => $pack->id]);
        $this->assertFileDoesNotExist($this->storagePath.'/'.$version->storage_path);
    }

    public function test_request_triggered_janitor_removes_expired_uploads(): void
    {
        [$pack] = $this->makeGuestPack();
        $pack->forceFill(['guest_expires_at' => now()->subMinute()])->save();

        $this->get('/upload')->assertOk();

        $this->assertDatabaseMissing('resource_packs', ['id' => $pack->id]);
    }

    public function test_admin_can_manage_and_clean_guest_uploads(): void
    {
        [$pack] = $this->makeGuestPack();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/guests')
            ->assertOk()
            ->assertSee('Guest Pack');

        $this->actingAs($admin)->post('/admin/guests/cleanup')->assertRedirect();

        $this->actingAs($admin)->delete('/admin/guests/'.$pack->id)->assertRedirect();
        $this->assertDatabaseMissing('resource_packs', ['id' => $pack->id]);
    }

    public function test_guest_uploads_can_be_disabled(): void
    {
        SystemSetting::set('guest_uploads_enabled', false, 'guest', 'bool');
        SystemSetting::forgetCache();

        $this->get('/upload')->assertNotFound();
    }

    public function test_signed_in_users_are_redirected_to_the_permanent_upload_form(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/upload')->assertRedirect('/packs/create');
    }

    private function withCaptcha(string $code): static
    {
        return $this->withSession([
            CaptchaService::SESSION_KEY => [
                'code' => strtoupper($code),
                'expires_at' => now()->addMinutes(10)->getTimestamp(),
            ],
        ]);
    }

    /** @return array{0:ResourcePack,1:string} */
    private function makeGuestPack(): array
    {
        $zipPath = $this->makeValidPack();
        $token = bin2hex(random_bytes(32));

        $pack = app(UploadService::class)->createGuestPack(
            new UploadedFile($zipPath, 'guest.zip', 'application/zip', null, true),
            ['name' => 'Guest Pack', 'management_token' => $token, 'ip_hash' => str_repeat('a', 64)],
        );

        return [$pack, $token];
    }
}
