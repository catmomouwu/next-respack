<?php

namespace Tests\Feature;

use App\Models\ResourcePack;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Download\DownloadTokenService;
use App\Services\ResourcePack\UploadService;
use App\Services\Security\CaptchaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
        SystemSetting::forgetCache();
    }

    public function test_guest_can_open_the_upload_page_with_retention_notice(): void
    {
        $response = $this->get('/upload');

        $response->assertOk();
        $response->assertSee('游客上传');
        $response->assertSee('7 天后自动删除');
        $response->assertSee('注册后永久保存');
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

    public function test_guest_can_upload_and_receives_a_signed_result_page_with_a_download_link(): void
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

        $this->assertNotNull($version);
        $this->assertSame('Guest Pack', $pack->name);
        $this->assertNotNull($pack->guest_expires_at);
        $this->assertTrue($pack->guest_expires_at->between(now()->addDays(6), now()->addDays(8)));
        $this->assertFileExists($this->storagePath.'/'.$version->storage_path);
        $this->assertDatabaseCount('user_quotas', 0);

        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('/upload/'.$pack->public_id, $location);
        $this->assertStringContainsString('signature=', $location);

        $this->get($location)
            ->assertOk()
            ->assertSee('Guest Pack')
            ->assertSee('注册后永久保存');

        $downloadToken = app(DownloadTokenService::class)->tokenFor($version);
        $this->get('/dl/'.$downloadToken)->assertOk();
    }

    public function test_guest_result_page_requires_a_valid_signature(): void
    {
        $pack = $this->makeGuestPack();

        $this->get('/upload/'.$pack->public_id)->assertForbidden();
    }

    public function test_expired_guest_download_returns_gone(): void
    {
        $pack = $this->makeGuestPack();
        $version = $pack->currentVersion;

        $pack->forceFill(['guest_expires_at' => now()->subMinute()])->save();

        $this->get('/dl/'.app(DownloadTokenService::class)->tokenFor($version))
            ->assertStatus(410);
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

    public function test_purge_command_deletes_expired_guest_packs_and_files(): void
    {
        $pack = $this->makeGuestPack();
        $version = $pack->currentVersion;

        $pack->forceFill(['guest_expires_at' => now()->subDay()])->save();

        $this->artisan('guests:purge')->assertSuccessful();

        $this->assertDatabaseMissing('resource_packs', ['id' => $pack->id]);
        $this->assertDatabaseMissing('resource_pack_versions', ['id' => $version->id]);
        $this->assertFileDoesNotExist($this->storagePath.'/'.$version->storage_path);
    }

    public function test_purge_command_keeps_guest_uploads_that_have_not_expired(): void
    {
        $pack = $this->makeGuestPack();

        $this->artisan('guests:purge')->assertSuccessful();

        $this->assertDatabaseHas('resource_packs', ['id' => $pack->id]);
        $this->assertFileExists($this->storagePath.'/'.$pack->currentVersion->storage_path);
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

    private function makeGuestPack(): ResourcePack
    {
        $zipPath = $this->makeValidPack();

        return app(UploadService::class)->createGuestPack(
            new UploadedFile($zipPath, 'guest.zip', 'application/zip', null, true),
            ['name' => 'Guest Pack'],
        );
    }
}
