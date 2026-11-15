<?php

namespace Tests\Feature;

use App\Models\MinecraftPackFormat;
use App\Models\MinecraftPackFormatHistory;
use App\Models\MinecraftPackFormatVersion;
use App\Models\User;
use App\Services\Minecraft\PackFormatSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackFormatAdminTest extends TestCase
{
    use RefreshDatabase;

    private function records(int $count = 30, int $offset = 0): array
    {
        $records = [];

        for ($i = 1; $i <= $count; $i++) {
            $major = $i + $offset;
            $records[] = [
                'version_name' => '1.'.$major,
                'version_type' => 'release',
                'format_value' => (string) $major,
                'format_major' => $major,
                'format_minor' => null,
            ];
        }

        return $records;
    }

    private function seedFormats(int $offset = 0): void
    {
        $service = app(PackFormatSyncService::class);
        $log = $service->apply($this->records(30, $offset), 'test');

        $this->assertSame('success', $log->status, $log->error ?? '');
    }

    public function test_public_api_lists_versions_and_looks_up_a_version(): void
    {
        $this->seedFormats();

        $this->getJson('/api/pack-formats')
            ->assertOk()
            ->assertJsonPath('data.0.version', '1.30');

        $this->getJson('/api/pack-formats?version=1.15')
            ->assertOk()
            ->assertJsonPath('data.version', '1.15')
            ->assertJsonPath('data.resource_pack_format', '15');

        $this->getJson('/api/pack-formats?format=15')
            ->assertOk()
            ->assertJsonPath('data.resource_pack_format', '15');

        $this->getJson('/api/pack-formats?version=9.99')->assertNotFound();
    }

    public function test_admin_can_import_json_data(): void
    {
        $admin = User::factory()->admin()->create();

        $payload = json_encode(['records' => $this->records(30, 10)], JSON_THROW_ON_ERROR);

        $this->actingAs($admin)
            ->from('/admin/pack-formats')
            ->post('/admin/pack-formats/import', [
                'format_type' => 'json',
                'payload' => $payload,
            ])
            ->assertRedirect('/admin/pack-formats')
            ->assertSessionHasNoErrors();

        $this->assertSame(30, MinecraftPackFormat::count());
        $this->assertDatabaseHas('minecraft_pack_format_sync_logs', ['status' => 'success', 'trigger' => 'import-json']);
    }

    public function test_admin_can_mark_a_version_as_a_manual_correction(): void
    {
        $this->seedFormats();
        $admin = User::factory()->admin()->create();

        $version = MinecraftPackFormatVersion::where('version_name', '1.15')->firstOrFail();

        $this->actingAs($admin)->patch('/admin/pack-formats/versions/'.$version->id, [
            'version_name' => '1.15-corrected',
            'version_type' => 'release',
            'format_value' => '15.1',
        ])->assertRedirect();

        $version->refresh();
        $this->assertTrue($version->is_manual);
        $this->assertSame('1.15-corrected', $version->version_name);
        $this->assertSame('15.1', $version->format?->format_value);

        // Synchronization keeps the manual record untouched.
        app(PackFormatSyncService::class)->apply($this->records(30), 'test');

        $this->assertDatabaseHas('minecraft_pack_format_versions', [
            'version_name' => '1.15-corrected',
            'is_manual' => true,
        ]);
    }

    public function test_rollback_restores_the_previous_snapshot(): void
    {
        $this->seedFormats();
        $this->seedFormats(5);

        $history = MinecraftPackFormatHistory::latest('id')->firstOrFail();

        $log = app(PackFormatSyncService::class)->rollback($history);

        $this->assertSame('success', $log->status, $log->error ?? '');
        $this->assertSame(30, MinecraftPackFormat::count());
        $this->assertTrue(MinecraftPackFormat::where('format_major', 1)->exists());
    }

    public function test_regular_user_cannot_access_pack_format_admin(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/pack-formats')->assertForbidden();
        $this->actingAs($user)->post('/admin/pack-formats/import', [
            'format_type' => 'json',
            'payload' => json_encode(['records' => $this->records()]),
        ])->assertForbidden();
    }

    public function test_pack_format_public_page_renders(): void
    {
        $this->seedFormats();

        $this->get('/minecraft/pack-formats')->assertOk()->assertSee('1.30');
    }
}
