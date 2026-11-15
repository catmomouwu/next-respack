<?php

namespace Tests\Unit;

use App\Enums\ValidationStatus;
use App\Models\MinecraftPackFormat;
use App\Models\MinecraftPackFormatVersion;
use App\Services\Minecraft\PackFormatLookup;
use App\Services\ResourcePack\PackMetadataParser;
use App\Services\ResourcePack\ResourceValidationResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackMetadataParserTest extends TestCase
{
    use RefreshDatabase;

    private function parse(string $json): ResourceValidationResult
    {
        $result = new ResourceValidationResult;
        $result->status = ValidationStatus::Valid;

        return app(PackMetadataParser::class)->parse($result, $json);
    }

    private function seedFormat(int $major, string $versionName = '1.20.1'): void
    {
        $format = MinecraftPackFormat::create([
            'edition' => 'java_resource_pack',
            'format_key' => MinecraftPackFormat::makeKey('java_resource_pack', $major, null),
            'format_major' => $major,
            'format_minor' => null,
            'format_value' => (string) $major,
        ]);

        MinecraftPackFormatVersion::create([
            'minecraft_pack_format_id' => $format->id,
            'edition' => 'java_resource_pack',
            'version_name' => $versionName,
            'version_type' => 'release',
        ]);
    }

    public function test_valid_pack_format_resolves_versions(): void
    {
        $this->seedFormat(15);

        $result = $this->parse('{"pack":{"pack_format":15,"description":"Hello"}}');

        $this->assertSame(ValidationStatus::Valid, $result->status);
        $this->assertSame('15', $result->formatValue);
        $this->assertSame(15, $result->formatMajor);
        $this->assertFalse($result->formatUnknown);
        $this->assertSame('Hello', $result->details['description']);
        $this->assertSame('1.20.1', $result->detectedVersions[0]['version_name']);
    }

    public function test_decimal_pack_format_is_supported(): void
    {
        $format = MinecraftPackFormat::create([
            'edition' => 'java_resource_pack',
            'format_key' => MinecraftPackFormat::makeKey('java_resource_pack', 69, 1),
            'format_major' => 69,
            'format_minor' => 1,
            'format_value' => '69.1',
        ]);

        MinecraftPackFormatVersion::create([
            'minecraft_pack_format_id' => $format->id,
            'edition' => 'java_resource_pack',
            'version_name' => '1.21.9',
            'version_type' => 'release',
        ]);

        $result = $this->parse('{"pack":{"pack_format":69.1,"description":"Decimal"}}');

        $this->assertSame(ValidationStatus::Valid, $result->status);
        $this->assertSame('69.1', $result->formatValue);
        $this->assertSame(1, $result->formatMinor);
        $this->assertSame($format->id, MinecraftPackFormat::where('format_value', '69.1')->value('id'));
    }

    public function test_unknown_format_is_stored_with_a_warning(): void
    {
        $result = $this->parse('{"pack":{"pack_format":999,"description":"Future"}}');

        $this->assertSame(ValidationStatus::FormatUnknown, $result->status);
        $this->assertTrue($result->status->isStorable());
        $this->assertTrue($result->formatUnknown);
        $this->assertNotEmpty($result->warnings);
    }

    public function test_invalid_json_is_rejected(): void
    {
        $result = $this->parse('{not-json');

        $this->assertSame(ValidationStatus::MetadataInvalid, $result->status);
        $this->assertTrue($result->status->isFatal());
    }

    public function test_missing_description_is_rejected(): void
    {
        $result = $this->parse('{"pack":{"pack_format":15}}');

        $this->assertSame(ValidationStatus::MetadataInvalid, $result->status);
    }

    public function test_range_formats_are_parsed(): void
    {
        $this->seedFormat(15, '1.20.1');
        $this->seedFormat(18, '1.20.2');

        $result = $this->parse('{"pack":{"min_format":15,"max_format":18,"description":"Range"}}');

        $this->assertSame(ValidationStatus::Valid, $result->status);
        $this->assertContains('1.20.2', array_column($result->detectedVersions, 'version_name'));
    }

    public function test_supported_formats_array_is_parsed(): void
    {
        $this->seedFormat(15, '1.20.1');
        $this->seedFormat(18, '1.20.2');

        $result = $this->parse('{"pack":{"pack_format":15,"supported_formats":[15,18],"description":"Supported"}}');

        $this->assertSame(ValidationStatus::Valid, $result->status);
        $this->assertContains('1.20.2', array_column($result->detectedVersions, 'version_name'));
    }

    public function test_lookup_returns_null_for_unknown_values(): void
    {
        $lookup = app(PackFormatLookup::class);

        $this->assertNull($lookup->findByNumber(999));
        $this->assertFalse($lookup->hasData());
    }
}
