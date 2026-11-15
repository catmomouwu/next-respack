<?php

namespace Tests\Feature;

use App\Models\ResourcePack;
use App\Models\StorageMigration;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\ResourcePack\UploadService;
use App\Services\Storage\StorageManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StorageMigrationTest extends TestCase
{
    use RefreshDatabase;

    private string $sourcePath;

    private string $targetPath;

    private User $admin;

    private ResourcePack $pack;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sourcePath = $this->temporaryDirectory().'/source';
        $this->targetPath = $this->temporaryDirectory().'/target';

        SystemSetting::set('storage_driver', 'local', 'storage');
        SystemSetting::set('storage_local_path', $this->sourcePath, 'storage');
        SystemSetting::forgetCache();

        $this->admin = User::factory()->superAdmin()->create();

        $this->pack = app(UploadService::class)->createPack(
            $this->admin,
            new UploadedFile($this->makeValidPack(), 'pack.zip', 'application/zip', null, true),
            ['name' => 'Migration Pack']
        );
    }

    public function test_storage_can_be_migrated_to_another_local_directory(): void
    {
        $version = $this->pack->currentVersion;

        $this->actingAs($this->admin)->post('/admin/storage/plan', [
            'target_driver' => 'local',
            'local_path' => $this->targetPath,
        ])->assertRedirect('/admin/storage');

        $migration = StorageMigration::latest('id')->firstOrFail();
        $this->assertSame('planned', $migration->status);

        $this->actingAs($this->admin)
            ->post('/admin/storage/migrations/'.$migration->id.'/run')
            ->assertRedirect('/admin/storage')
            ->assertSessionHasNoErrors();

        $migration->refresh();
        $this->assertSame('completed', $migration->status);
        $this->assertSame(1, $migration->stats['copied_files']);

        $this->assertFileExists($this->targetPath.'/'.$version->storage_path);
        $this->assertSame($version->sha1, hash_file('sha1', $this->targetPath.'/'.$version->storage_path));

        $this->assertSame('local', $version->fresh()->storage_driver);
        $this->assertSame($this->targetPath, nr_setting('storage_local_path'));
    }

    public function test_orphan_cleanup_removes_unknown_files_only(): void
    {
        $version = $this->pack->currentVersion;
        $orphan = $this->sourcePath.'/orphan-uuid.zip';
        file_put_contents($orphan, 'orphan');

        $this->actingAs($this->admin)
            ->post('/admin/storage/orphans')
            ->assertRedirect();

        $this->assertFileDoesNotExist($orphan);
        $this->assertFileExists($this->sourcePath.'/'.$version->storage_path);
    }

    public function test_regular_admin_cannot_run_storage_migration(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/storage/plan', [
            'target_driver' => 'local',
            'local_path' => $this->targetPath,
        ])->assertForbidden();
    }

    public function test_storage_can_be_migrated_from_local_to_webdav_and_the_password_is_encrypted(): void
    {
        $files = [];

        Http::fake(function ($request) use (&$files) {
            $url = $request->url();

            return match ($request->method()) {
                'MKCOL' => Http::response('', 201),
                'PUT' => $this->storeFakeObject($files, $url, (string) $request->body()),
                'HEAD' => isset($files[$url])
                    ? Http::response('', 200, ['Content-Length' => (string) strlen($files[$url])])
                    : Http::response('', 404),
                'GET' => isset($files[$url]) ? Http::response($files[$url], 200) : Http::response('', 404),
                'DELETE' => Http::response('', 204),
                default => Http::response('', 201),
            };
        });

        $this->actingAs($this->admin)->post('/admin/storage/plan', [
            'target_driver' => 'webdav',
            'webdav_url' => 'https://dav.example.com/files/user',
            'webdav_username' => 'alice',
            'webdav_password' => 'top-secret',
            'webdav_prefix' => 'next-respack',
            'webdav_verify_ssl' => '1',
        ])->assertRedirect('/admin/storage')->assertSessionHasNoErrors();

        $migration = StorageMigration::latest('id')->firstOrFail();
        $this->assertSame('webdav', $migration->to_driver);
        $this->assertSame('next-respack', $migration->stats['prefix']);
        $this->assertNotSame('top-secret', $migration->stats['password']);

        $manager = app(StorageManager::class);
        $this->assertSame('top-secret', $manager->decryptSecret($migration->stats['password']));

        $version = $this->pack->currentVersion;

        $this->actingAs($this->admin)
            ->post('/admin/storage/migrations/'.$migration->id.'/run')
            ->assertRedirect('/admin/storage')
            ->assertSessionHasNoErrors();

        $migration->refresh();
        $this->assertSame('completed', $migration->status);
        $this->assertSame(1, $migration->stats['copied_files']);

        $this->assertSame('webdav', $version->fresh()->storage_driver);
        $this->assertSame('webdav', nr_setting('storage_driver'));
        $this->assertSame('top-secret', $manager->decryptSecret(nr_setting('webdav_password')));
        $this->assertSame(sha1_file($this->sourcePath.'/'.$version->storage_path), sha1($files['https://dav.example.com/files/user/next-respack/'.$version->storage_path]));

        // The source file must never be removed before the migration is fully verified.
        $this->assertFileExists($this->sourcePath.'/'.$version->storage_path);
    }

    public function test_webdav_storage_plan_requires_a_url(): void
    {
        $this->actingAs($this->admin)->post('/admin/storage/plan', [
            'target_driver' => 'webdav',
            'webdav_url' => '',
        ])->assertSessionHasErrors('webdav_url');
    }

    public function test_storage_can_be_migrated_from_webdav_to_local(): void
    {
        $version = $this->pack->currentVersion;
        $content = (string) file_get_contents($this->sourcePath.'/'.$version->storage_path);
        $url = 'https://dav.example.com/files/user/next-respack/'.$version->storage_path;

        Http::fake(function ($request) use ($url, $content) {
            if ($request->url() !== $url) {
                return Http::response('', 404);
            }

            return match ($request->method()) {
                'HEAD' => Http::response('', 200, ['Content-Length' => (string) strlen($content)]),
                'GET' => Http::response($content, 200),
                default => Http::response('', 204),
            };
        });

        $manager = app(StorageManager::class);
        SystemSetting::set('storage_driver', 'webdav', 'storage');
        SystemSetting::set('webdav_url', 'https://dav.example.com/files/user', 'storage');
        SystemSetting::set('webdav_username', 'alice', 'storage');
        SystemSetting::set('webdav_password', $manager->encryptSecret('top-secret'), 'storage');
        SystemSetting::set('webdav_prefix', 'next-respack', 'storage');
        SystemSetting::forgetCache();

        $version->storage_driver = 'webdav';
        $version->save();

        $this->actingAs($this->admin)->post('/admin/storage/plan', [
            'target_driver' => 'local',
            'local_path' => $this->targetPath,
        ])->assertRedirect('/admin/storage')->assertSessionHasNoErrors();

        $migration = StorageMigration::latest('id')->firstOrFail();
        $this->assertSame('webdav', $migration->from_driver);

        $this->actingAs($this->admin)
            ->post('/admin/storage/migrations/'.$migration->id.'/run')
            ->assertRedirect('/admin/storage')
            ->assertSessionHasNoErrors();

        $migration->refresh();
        $this->assertSame('completed', $migration->status);
        $this->assertSame('local', $version->fresh()->storage_driver);
        $this->assertFileExists($this->targetPath.'/'.$version->storage_path);
        $this->assertSame($version->sha1, hash_file('sha1', $this->targetPath.'/'.$version->storage_path));
    }

    public function test_admin_storage_page_shows_webdav_without_leaking_the_password(): void
    {
        SystemSetting::set('storage_driver', 'webdav', 'storage');
        SystemSetting::set('webdav_url', 'https://dav.example.com/files/user', 'storage');
        SystemSetting::set('webdav_username', 'alice', 'storage');
        SystemSetting::set('webdav_password', app(StorageManager::class)->encryptSecret('top-secret'), 'storage');
        SystemSetting::forgetCache();

        $this->actingAs($this->admin)->get('/admin/storage')
            ->assertOk()
            ->assertSee('WebDAV')
            ->assertSee('https://dav.example.com/files/user')
            ->assertDontSee('top-secret');
    }

    private function storeFakeObject(array &$files, string $url, string $body)
    {
        $files[$url] = $body;

        return Http::response('', 201);
    }
}
