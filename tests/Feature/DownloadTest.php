<?php

namespace Tests\Feature;

use App\Models\ResourcePack;
use App\Models\ResourcePackVersion;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Download\DownloadTokenService;
use App\Services\ResourcePack\UploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class DownloadTest extends TestCase
{
    use RefreshDatabase;

    private string $storagePath;

    private string $zipPath;

    private ResourcePack $pack;

    private ResourcePackVersion $version;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storagePath = $this->temporaryDirectory().'/packs';
        SystemSetting::set('storage_driver', 'local', 'storage');
        SystemSetting::set('storage_local_path', $this->storagePath, 'storage');
        SystemSetting::set('download_mode', 'php', 'downloads');
        SystemSetting::set('download_ip_logging', true, 'downloads', 'bool');
        SystemSetting::forgetCache();

        $user = User::factory()->create();
        $this->zipPath = $this->makeValidPack();

        $this->pack = app(UploadService::class)->createPack($user, new UploadedFile(
            $this->zipPath,
            'download-test.zip',
            'application/zip',
            null,
            true
        ), ['name' => 'Download Test']);

        $this->version = $this->pack->currentVersion;
        $this->token = app(DownloadTokenService::class)->tokenFor($this->version);
    }

    public function test_get_returns_the_zip_with_integrity_headers(): void
    {
        $response = $this->get('/dl/'.$this->token);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/zip');
        $response->assertHeader('ETag', '"'.$this->version->sha1.'"');
        $response->assertHeader('Accept-Ranges', 'bytes');

        $this->assertSame(hash_file('sha1', $this->zipPath), $this->version->sha1);
        $this->assertSame(file_get_contents($this->zipPath), $response->streamedContent());

        $this->assertSame(1, $this->version->fresh()->downloads_count);
        $this->assertDatabaseHas('download_logs', [
            'resource_pack_version_id' => $this->version->id,
            'method' => 'GET',
            'status_code' => 200,
            'is_range' => false,
        ]);
    }

    public function test_head_returns_metadata_without_a_body(): void
    {
        $response = $this->head('/dl/'.$this->token);

        $response->assertOk();
        $response->assertHeader('Content-Length', (string) $this->version->file_size);
        $this->assertSame('', $response->getContent());

        // HEAD requests are not counted as downloads.
        $this->assertSame(0, $this->version->fresh()->downloads_count);
        $this->assertDatabaseCount('download_logs', 0);
    }

    public function test_range_request_returns_partial_content(): void
    {
        $response = $this->get('/dl/'.$this->token, ['Range' => 'bytes=0-9']);

        $response->assertStatus(206);
        $response->assertHeader('Content-Range', 'bytes 0-9/'.$this->version->file_size);
        $response->assertHeader('Content-Length', '10');

        $body = $response->streamedContent();
        $this->assertSame(10, strlen($body));
        $this->assertSame(substr(file_get_contents($this->zipPath), 0, 10), $body);

        $this->assertDatabaseHas('download_logs', [
            'resource_pack_version_id' => $this->version->id,
            'status_code' => 206,
            'is_range' => true,
        ]);
    }

    public function test_unsatisfiable_range_returns_416(): void
    {
        $response = $this->get('/dl/'.$this->token, ['Range' => 'bytes=999999-1000000']);

        $response->assertStatus(416);
        $response->assertHeader('Content-Range', 'bytes */'.$this->version->file_size);
    }

    public function test_conditional_request_returns_304(): void
    {
        $response = $this->get('/dl/'.$this->token, ['If-None-Match' => '"'.$this->version->sha1.'"']);

        $response->assertStatus(304);
        $this->assertDatabaseCount('download_logs', 0);
    }

    public function test_x_accel_mode_returns_an_internal_redirect_header(): void
    {
        SystemSetting::set('download_mode', 'x-accel', 'downloads');
        SystemSetting::set('x_accel_prefix', '/_protected/respack', 'downloads');
        SystemSetting::forgetCache();

        $response = $this->get('/dl/'.$this->token);

        $response->assertOk();
        $response->assertHeader('X-Accel-Redirect', '/_protected/respack/'.$this->version->storage_path);
        $this->assertSame(1, $this->version->fresh()->downloads_count);
    }

    public function test_unknown_token_returns_404(): void
    {
        $this->get('/dl/not-a-real-token')->assertNotFound();
    }

    public function test_token_reset_invalidates_the_old_link(): void
    {
        $newToken = app(DownloadTokenService::class)->reset($this->version);

        $this->get('/dl/'.$this->token)->assertNotFound();
        $this->get('/dl/'.$newToken)->assertOk();
    }

    public function test_disabled_version_returns_403(): void
    {
        $this->version->download_enabled = false;
        $this->version->save();

        $this->get('/dl/'.$this->token)->assertForbidden();
    }

    public function test_disabled_pack_returns_403(): void
    {
        $this->pack->status = ResourcePack::STATUS_DISABLED;
        $this->pack->save();

        $this->get('/dl/'.$this->token)->assertForbidden();
    }

    public function test_missing_stored_file_returns_404(): void
    {
        @unlink($this->storagePath.'/'.$this->version->storage_path);

        $this->get('/dl/'.$this->token)->assertNotFound();
    }
}
