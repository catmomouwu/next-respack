<?php

namespace Tests\Feature;

use App\Models\MinecraftPackFormat;
use App\Models\MinecraftPackFormatVersion;
use App\Models\ResourcePack;
use App\Models\ResourcePackVersion;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserQuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ResourcePackUploadTest extends TestCase
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

        $format = MinecraftPackFormat::create([
            'edition' => 'java_resource_pack',
            'format_key' => MinecraftPackFormat::makeKey('java_resource_pack', 15, null),
            'format_major' => 15,
            'format_minor' => null,
            'format_value' => '15',
        ]);

        MinecraftPackFormatVersion::create([
            'minecraft_pack_format_id' => $format->id,
            'edition' => 'java_resource_pack',
            'version_name' => '1.20.1',
            'version_type' => 'release',
        ]);
    }

    public function test_user_can_upload_a_valid_resource_pack(): void
    {
        $user = User::factory()->create();
        $zipPath = $this->makeValidPack();

        $response = $this->actingAs($user)->post('/packs', [
            'name' => 'My Pack',
            'file' => new UploadedFile($zipPath, 'my-pack.zip', 'application/zip', null, true),
        ]);

        $response->assertRedirect();

        $pack = ResourcePack::where('user_id', $user->id)->firstOrFail();
        $version = $pack->currentVersion;
        $this->assertNotNull($version);

        $this->assertSame('My Pack', $pack->name);
        $this->assertSame('VALID', $version->validation_status);
        $this->assertSame(hash_file('sha1', $zipPath), $version->sha1);
        $this->assertSame(filesize($zipPath), $version->file_size);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}\.zip$/', $version->stored_filename);
        $this->assertFileExists($this->storagePath.'/'.$version->storage_path);

        // SHA-1 is computed from the bytes that were actually stored.
        $this->assertSame($version->sha1, hash_file('sha1', $this->storagePath.'/'.$version->storage_path));

        $quota = UserQuota::where('user_id', $user->id)->firstOrFail();
        $this->assertSame($version->file_size, $quota->used_bytes);
        $this->assertSame(1, $quota->pack_count);
    }

    public function test_zip_without_pack_mcmeta_is_rejected(): void
    {
        $user = User::factory()->create();

        $zipPath = $this->makeZip([
            'assets/minecraft/pack.txt' => 'no metadata',
        ]);

        $this->actingAs($user)->post('/packs', [
            'file' => new UploadedFile($zipPath, 'broken.zip', 'application/zip', null, true),
        ])->assertSessionHasErrors('file');

        $this->assertDatabaseCount('resource_packs', 0);
        $this->assertDatabaseCount('resource_pack_versions', 0);
        $this->assertDirectoryDoesNotExist($this->storagePath);
    }

    public function test_invalid_json_metadata_is_rejected(): void
    {
        $user = User::factory()->create();

        $zipPath = $this->makeZip([
            'pack.mcmeta' => '{"pack": ',
            'pack.png' => 'png',
        ]);

        $this->actingAs($user)->post('/packs', [
            'file' => new UploadedFile($zipPath, 'bad-json.zip', 'application/zip', null, true),
        ])->assertSessionHasErrors('file');
    }

    public function test_non_zip_extension_is_rejected(): void
    {
        $user = User::factory()->create();

        $file = UploadedFile::fake()->createWithContent('notes.txt', 'not a zip');

        $this->actingAs($user)->post('/packs', ['file' => $file])
            ->assertSessionHasErrors('file');
    }

    public function test_duplicate_upload_is_rejected_unless_allowed(): void
    {
        $user = User::factory()->create();
        $zipPath = $this->makeValidPack();

        $this->actingAs($user)->post('/packs', [
            'file' => new UploadedFile($zipPath, 'pack.zip', 'application/zip', null, true),
        ])->assertRedirect();

        $this->actingAs($user)->post('/packs', [
            'file' => new UploadedFile($zipPath, 'pack.zip', 'application/zip', null, true),
        ])->assertSessionHasErrors('file');

        $this->actingAs($user)->post('/packs', [
            'file' => new UploadedFile($zipPath, 'pack.zip', 'application/zip', null, true),
            'allow_duplicate' => '1',
        ])->assertRedirect();

        $this->assertSame(2, ResourcePackVersion::whereHas('pack', fn ($q) => $q->where('user_id', $user->id))->count());
    }

    public function test_quota_is_enforced(): void
    {
        $user = User::factory()->create(['storage_quota' => 100]);
        $user->ensureQuota();

        $zipPath = $this->makeValidPack();

        $this->actingAs($user)->post('/packs', [
            'file' => new UploadedFile($zipPath, 'pack.zip', 'application/zip', null, true),
        ])->assertSessionHasErrors('file');

        $this->assertDatabaseCount('resource_pack_versions', 0);
    }

    public function test_user_can_add_a_version_and_only_the_owner_can_manage_it(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $zipPath = $this->makeValidPack();

        $this->actingAs($owner)->post('/packs', [
            'name' => 'Owner Pack',
            'file' => new UploadedFile($zipPath, 'pack.zip', 'application/zip', null, true),
        ]);

        $pack = ResourcePack::where('user_id', $owner->id)->firstOrFail();

        $second = $this->makeValidPack(['pack' => ['pack_format' => 22, 'description' => 'Second version']]);
        $this->actingAs($owner)->post('/packs/'.$pack->id.'/versions', [
            'file' => new UploadedFile($second, 'second.zip', 'application/zip', null, true),
        ])->assertRedirect();

        $this->assertSame(2, $pack->versions()->count());
        $this->assertNotSame($pack->versions()->latest('version_number')->first()->sha1, $pack->currentVersion->sha1);
        $this->assertSame(2, $pack->fresh()->currentVersion->version_number);

        $this->actingAs($other)->get('/packs/'.$pack->id)->assertForbidden();
        $this->actingAs($other)->post('/packs/'.$pack->id.'/versions', [
            'file' => new UploadedFile($zipPath, 'pack.zip', 'application/zip', null, true),
        ])->assertForbidden();
    }

    public function test_deleting_a_pack_releases_quota_and_storage(): void
    {
        $user = User::factory()->create();
        $zipPath = $this->makeValidPack();

        $this->actingAs($user)->post('/packs', [
            'file' => new UploadedFile($zipPath, 'pack.zip', 'application/zip', null, true),
        ]);

        $pack = ResourcePack::where('user_id', $user->id)->firstOrFail();
        $stored = $pack->currentVersion->storage_path;

        $this->actingAs($user)->delete('/packs/'.$pack->id)->assertRedirect('/packs');

        $this->assertSoftDeleted('resource_packs', ['id' => $pack->id]);
        $this->assertFileDoesNotExist($this->storagePath.'/'.$stored);

        $quota = UserQuota::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(0, $quota->used_bytes);
        $this->assertSame(0, $quota->pack_count);
    }
}
