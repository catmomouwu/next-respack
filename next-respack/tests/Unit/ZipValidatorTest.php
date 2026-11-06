<?php

namespace Tests\Unit;

use App\Enums\ValidationStatus;
use App\Services\ResourcePack\ZipValidator;
use Tests\TestCase;

class ZipValidatorTest extends TestCase
{
    public function test_valid_pack_is_accepted(): void
    {
        $path = $this->makeValidPack();

        $result = app(ZipValidator::class)->validate($path);

        $this->assertSame(ValidationStatus::Valid, $result->status);
        $this->assertSame([], $result->errors);
        $this->assertNotNull($result->rawMcmeta);
        $this->assertTrue($result->details['has_pack_png'] ?? false);
    }

    public function test_missing_root_pack_mcmeta_is_rejected(): void
    {
        $path = $this->makeZip([
            'assets/minecraft/lang/en_us.json' => '{}',
            'pack.png' => 'png',
        ]);

        $result = app(ZipValidator::class)->validate($path);

        $this->assertSame(ValidationStatus::PackInvalid, $result->status);
        $this->assertTrue($result->status->isFatal());
        $this->assertNotEmpty($result->errors);
    }

    public function test_nested_pack_mcmeta_is_not_accepted_as_root(): void
    {
        $path = $this->makeZip([
            'sub/pack.mcmeta' => json_encode(['pack' => ['pack_format' => 15, 'description' => 'x']]),
        ]);

        $result = app(ZipValidator::class)->validate($path);

        $this->assertSame(ValidationStatus::PackInvalid, $result->status);
    }

    public function test_non_zip_file_is_rejected_by_signature(): void
    {
        $path = $this->temporaryDirectory().'/fake.zip';
        file_put_contents($path, 'this is definitely not a zip file');

        $result = app(ZipValidator::class)->validate($path);

        $this->assertSame(ValidationStatus::ZipInvalid, $result->status);
        $this->assertTrue($result->status->isFatal());
    }

    public function test_empty_archive_is_rejected(): void
    {
        $path = $this->temporaryDirectory().'/empty.zip';
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE);
        $zip->close();

        $result = app(ZipValidator::class)->validate($path);

        $this->assertSame(ValidationStatus::ZipInvalid, $result->status);
    }

    public function test_path_traversal_entries_are_rejected(): void
    {
        $path = $this->makeZip([
            'pack.mcmeta' => json_encode(['pack' => ['pack_format' => 15, 'description' => 'x']]),
            '../evil.txt' => 'bad',
        ]);

        $result = app(ZipValidator::class)->validate($path);

        $this->assertSame(ValidationStatus::ZipInvalid, $result->status);
        $this->assertNotEmpty($result->errors);
    }

    public function test_missing_file_is_reported(): void
    {
        $result = app(ZipValidator::class)->validate('/tmp/does-not-exist-next-respack.zip');

        $this->assertSame(ValidationStatus::ZipInvalid, $result->status);
    }
}
