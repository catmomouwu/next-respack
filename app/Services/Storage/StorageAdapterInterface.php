<?php

namespace App\Services\Storage;

interface StorageAdapterInterface
{
    public function driver(): string;

    public function putStream(string $path, $stream): void;

    public function readStream(string $path, int $start = 0, ?int $length = null);

    public function exists(string $path): bool;

    public function size(string $path): int;

    public function delete(string $path): void;

    public function hash(string $algorithm, string $path): string;

    /** Absolute filesystem path usable with X-Accel-Redirect, or null. */
    public function accelPath(string $path): ?string;

    public function test(): array;
}
