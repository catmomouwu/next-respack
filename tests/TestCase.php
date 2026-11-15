<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;
use ZipArchive;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Feature tests assume the application is already installed. The
        // installer test explicitly turns this off.
        config(['nextrespack.force_installed' => true]);
        config(['nextrespack.storage_root' => $this->temporaryDirectory().'/storage']);
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryDirectories() as $directory) {
            $this->removeDirectory($directory);
        }

        parent::tearDown();
    }

    /** @var array<int, string> */
    private array $temporaryPaths = [];

    protected function temporaryDirectory(): string
    {
        if ($this->temporaryPaths === []) {
            $path = sys_get_temp_dir().'/next-respack-test-'.Str::uuid();
            mkdir($path, 0775, true);
            $this->temporaryPaths[] = $path;
        }

        return $this->temporaryPaths[0];
    }

    /** @return array<int, string> */
    private function temporaryDirectories(): array
    {
        return $this->temporaryPaths;
    }

    /**
     * Create a real ZIP archive on disk.
     *
     * @param  array<string, string>  $files
     */
    protected function makeZip(array $files, ?string $path = null): string
    {
        $path ??= $this->temporaryDirectory().'/pack-'.Str::random(8).'.zip';
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Unable to create a test ZIP archive.');
        }

        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }

        $zip->close();

        return $path;
    }

    protected function makeValidPack(array $mcmeta = []): string
    {
        $metadata = $mcmeta ?: ['pack' => ['pack_format' => 15, 'description' => 'Test pack']];

        return $this->makeZip([
            'pack.mcmeta' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'pack.png' => 'png',
            'assets/minecraft/pack.txt' => 'hello',
        ]);
    }

    protected function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($directory);
    }
}
