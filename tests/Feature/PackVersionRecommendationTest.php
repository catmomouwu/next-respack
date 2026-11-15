<?php

namespace Tests\Feature;

use App\Models\MinecraftPackFormat;
use App\Models\ResourcePack;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Minecraft\PackFormatSyncService;
use App\Services\ResourcePack\UploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PackVersionRecommendationTest extends TestCase
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

    /** @return array<int, array<string, mixed>> */
    private function records(?string $format15Version = null): array
    {
        $records = [];

        for ($i = 1; $i <= 30; $i++) {
            $name = $i === 15 && $format15Version !== null ? $format15Version : '1.'.$i;

            $records[] = [
                'version_name' => $name,
                'version_type' => 'release',
                'format_value' => (string) $i,
                'format_major' => $i,
                'format_minor' => null,
            ];
        }

        return $records;
    }

    private function sync(?string $format15Version = null): void
    {
        $log = app(PackFormatSyncService::class)->apply($this->records($format15Version), 'test');

        $this->assertSame('success', $log->status, $log->error ?? '');
    }

    private function upload(User $user, int $format = 15): ResourcePack
    {
        $zip = $this->makeValidPack([
            'pack' => ['pack_format' => $format, 'description' => 'Recommendation test'],
        ]);

        return app(UploadService::class)->createPack(
            $user,
            new UploadedFile($zip, 'pack.zip', 'application/zip', null, true),
            ['name' => 'Recommendation pack']
        );
    }

    public function test_upload_persists_the_recommended_version_and_displays_it(): void
    {
        $this->sync();
        $user = User::factory()->create();

        $pack = $this->upload($user);
        $version = $pack->currentVersion;

        $this->assertSame('1.15', $version->recommended_game_version);
        $this->assertSame('release', $version->recommended_version_type);

        $this->actingAs($user)
            ->get('/packs/'.$pack->id)
            ->assertOk()
            ->assertSee('建议使用的版本：1.15')
            ->assertSee('版本类型')
            ->assertSee('Resource Pack Format')
            ->assertSee('格式兼容不代表');
    }

    public function test_unknown_format_stores_no_recommendation_and_shows_the_fallback(): void
    {
        $this->sync();
        $user = User::factory()->create();

        $pack = $this->upload($user, 999);
        $version = $pack->currentVersion;

        $this->assertNull($version->recommended_game_version);
        $this->assertNull($version->recommended_version_type);

        $this->actingAs($user)
            ->get('/packs/'.$pack->id)
            ->assertOk()
            ->assertSee('暂无法确定建议版本');
    }

    public function test_recommendation_is_recomputed_after_a_pack_format_sync(): void
    {
        $this->sync();
        $user = User::factory()->create();

        $pack = $this->upload($user);
        $version = $pack->currentVersion;

        $this->assertSame('1.15', $version->recommended_game_version);

        $this->sync('2.0');

        $version->refresh();

        $this->assertSame('2.0', $version->recommended_game_version);
        $this->assertSame('release', $version->recommended_version_type);
        $this->assertSame('2.0', MinecraftPackFormat::query()->where('format_major', 15)->first()?->versions()->value('version_name'));
    }

    public function test_failed_sync_preserves_the_previous_valid_dataset(): void
    {
        $this->sync();

        $this->assertSame(30, MinecraftPackFormat::count());

        $log = app(PackFormatSyncService::class)->apply(array_slice($this->records(), 0, 5), 'test');

        $this->assertSame('failed', $log->status);
        $this->assertSame(30, MinecraftPackFormat::count());
        $this->assertDatabaseHas('minecraft_pack_format_versions', [
            'version_name' => '1.15',
            'version_type' => 'release',
        ]);
    }
}
