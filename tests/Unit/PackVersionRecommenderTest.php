<?php

namespace Tests\Unit;

use App\Models\MinecraftPackFormat;
use App\Models\MinecraftPackFormatVersion;
use App\Services\Minecraft\PackVersionRecommender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackVersionRecommenderTest extends TestCase
{
    use RefreshDatabase;

    private function seedVersion(
        int $major,
        ?int $minor,
        string $formatValue,
        string $name,
        string $type = 'release',
        ?string $releaseDate = null
    ): void {
        $format = MinecraftPackFormat::query()->firstOrCreate(
            ['format_key' => MinecraftPackFormat::makeKey('java_resource_pack', $major, $minor)],
            [
                'edition' => 'java_resource_pack',
                'format_major' => $major,
                'format_minor' => $minor,
                'format_value' => $formatValue,
            ]
        );

        MinecraftPackFormatVersion::create([
            'minecraft_pack_format_id' => $format->id,
            'edition' => 'java_resource_pack',
            'version_name' => $name,
            'version_type' => $type,
            'is_snapshot' => $type === 'snapshot',
            'is_prerelease' => $type === 'prerelease',
            'is_release_candidate' => $type === 'release_candidate',
            'release_date' => $releaseDate,
        ]);
    }

    private function recommender(): PackVersionRecommender
    {
        return app(PackVersionRecommender::class);
    }

    public function test_stable_release_beats_prerelease_release_candidate_and_snapshot(): void
    {
        $this->seedVersion(69, null, '69', '1.21.8', 'release');
        $this->seedVersion(69, null, '69', '1.21.9-rc1', 'release_candidate');
        $this->seedVersion(69, null, '69', '1.21.9-pre2', 'prerelease');
        $this->seedVersion(69, null, '69', '25w31a', 'snapshot');

        $result = $this->recommender()->recommendFromPack(['pack_format' => 69]);

        $this->assertNotNull($result);
        $this->assertSame('1.21.8', $result['version_name']);
        $this->assertSame('release', $result['version_type']);
        $this->assertSame('69', $result['format_value']);
        $this->assertContains('1.21.9-rc1', array_column($result['compatible_versions'], 'version_name'));
    }

    public function test_latest_stable_release_is_selected(): void
    {
        $this->seedVersion(69, null, '69', '1.21.8', 'release', '2025-07-17');
        $this->seedVersion(69, null, '69', '1.21.9', 'release', '2025-09-30');
        $this->seedVersion(69, null, '69', '1.21.10', 'release', '2025-10-02');

        $result = $this->recommender()->recommendFromPack(['pack_format' => 69]);

        $this->assertNotNull($result);
        $this->assertSame('1.21.10', $result['version_name']);
    }

    public function test_latest_release_uses_numeric_version_order_without_dates(): void
    {
        $this->seedVersion(70, null, '70', '1.21.9', 'release');
        $this->seedVersion(70, null, '70', '1.21.10', 'release');
        $this->seedVersion(70, null, '70', '1.21.8', 'release');

        $result = $this->recommender()->recommendFromPack(['pack_format' => 70]);

        $this->assertNotNull($result);
        $this->assertSame('1.21.10', $result['version_name']);
    }

    public function test_prerelease_and_snapshot_are_used_when_no_release_exists(): void
    {
        $this->seedVersion(16, null, '16', '25w31a', 'snapshot');
        $this->seedVersion(16, null, '16', '1.20-pre1', 'prerelease');

        $result = $this->recommender()->recommendFromPack(['pack_format' => 16]);

        $this->assertNotNull($result);
        $this->assertSame('1.20-pre1', $result['version_name']);
        $this->assertSame('prerelease', $result['version_type']);

        MinecraftPackFormatVersion::query()->where('version_name', '1.20-pre1')->delete();

        $snapshot = $this->recommender()->recommendFromPack(['pack_format' => 16]);

        $this->assertNotNull($snapshot);
        $this->assertSame('25w31a', $snapshot['version_name']);
        $this->assertSame('snapshot', $snapshot['version_type']);
    }

    public function test_min_and_max_format_ranges_are_supported(): void
    {
        $this->seedVersion(15, null, '15', '1.20.1');
        $this->seedVersion(18, null, '18', '1.20.2');
        $this->seedVersion(22, null, '22', '1.20.3');

        $result = $this->recommender()->recommendFromPack(['min_format' => 15, 'max_format' => 18]);

        $this->assertNotNull($result);
        $this->assertSame('1.20.2', $result['version_name']);
        $compatible = array_column($result['compatible_versions'], 'version_name');
        $this->assertContains('1.20.1', $compatible);
        $this->assertNotContains('1.20.3', $compatible);
    }

    public function test_supported_formats_ranges_are_supported(): void
    {
        $this->seedVersion(15, null, '15', '1.20.1');
        $this->seedVersion(18, null, '18', '1.20.2');
        $this->seedVersion(22, null, '22', '1.20.3');

        $result = $this->recommender()->recommendFromPack([
            'pack_format' => 15,
            'supported_formats' => [18, 22],
        ]);

        $this->assertNotNull($result);
        $this->assertSame('1.20.3', $result['version_name']);
        $compatible = array_column($result['compatible_versions'], 'version_name');
        $this->assertContains('1.20.2', $compatible);
        $this->assertContains('1.20.1', $compatible);
    }

    public function test_decimal_formats_are_supported(): void
    {
        $this->seedVersion(69, 1, '69.1', '1.21.9');

        $result = $this->recommender()->recommendFromPack(['pack_format' => 69.1]);

        $this->assertNotNull($result);
        $this->assertSame('1.21.9', $result['version_name']);
        $this->assertSame('69.1', $result['format_value']);
    }

    public function test_unknown_format_returns_null_instead_of_guessing(): void
    {
        $this->seedVersion(15, null, '15', '1.20.1');

        $this->assertNull($this->recommender()->recommendFromPack(['pack_format' => 999]));
        $this->assertNull($this->recommender()->recommendFromPack([]));
        $this->assertNull($this->recommender()->recommendFromPack(['min_format' => 999, 'max_format' => 1000]));
    }

    public function test_format_without_mapped_versions_returns_null(): void
    {
        MinecraftPackFormat::create([
            'edition' => 'java_resource_pack',
            'format_key' => MinecraftPackFormat::makeKey('java_resource_pack', 15, null),
            'format_major' => 15,
            'format_minor' => null,
            'format_value' => '15',
        ]);

        $this->assertNull($this->recommender()->recommendFromPack(['pack_format' => 15]));
    }
}
