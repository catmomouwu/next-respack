<?php

namespace App\Services\Storage;

use RuntimeException;

class LocalStorageAdapter implements StorageAdapterInterface
{
    public function __construct(private readonly string $root)
    {
    }

    public function driver(): string
    {
        return 'local';
    }

    public function root(): string
    {
        return $this->root;
    }

    public function putStream(string $path, $stream): void
    {
        $target = $this->absolute($path);
        $this->ensureDirectory(dirname($target));

        $tmp = $target.'.part-'.bin2hex(random_bytes(6));

        $out = @fopen($tmp, 'xb');
        if ($out === false) {
            throw new RuntimeException('Unable to create a temporary storage file.');
        }

        try {
            while (! feof($stream)) {
                $chunk = fread($stream, 1024 * 256);
                if ($chunk === false || fwrite($out, $chunk) === false) {
                    throw new RuntimeException('Unable to write the resource pack to storage.');
                }
            }
        } finally {
            fclose($out);
        }

        if (! @rename($tmp, $target)) {
            @unlink($tmp);
            throw new RuntimeException('Unable to move the resource pack into storage.');
        }
    }

    public function readStream(string $path, int $start = 0, ?int $length = null)
    {
        $target = $this->absolute($path);

        if (! is_file($target)) {
            throw new RuntimeException('Stored file is missing.');
        }

        $handle = @fopen($target, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Unable to open the stored file.');
        }

        if ($start > 0) {
            fseek($handle, $start);
        }

        return $handle;
    }

    public function exists(string $path): bool
    {
        return is_file($this->absolute($path));
    }

    public function size(string $path): int
    {
        $target = $this->absolute($path);

        if (! is_file($target)) {
            throw new RuntimeException('Stored file is missing.');
        }

        return (int) filesize($target);
    }

    public function delete(string $path): void
    {
        $target = $this->absolute($path);

        if (is_file($target)) {
            @unlink($target);
        }
    }

    public function hash(string $algorithm, string $path): string
    {
        $target = $this->absolute($path);

        if (! is_file($target)) {
            throw new RuntimeException('Stored file is missing.');
        }

        return (string) hash_file($algorithm, $target);
    }

    public function accelPath(string $path): ?string
    {
        return $this->absolute($path);
    }

    public function test(): array
    {
        $errors = [];

        if (! is_dir($this->root)) {
            if (! @mkdir($this->root, 0775, true) && ! is_dir($this->root)) {
                return ['ok' => false, 'errors' => [__('Unable to create the storage directory: :path', ['path' => $this->root])]];
            }
        }

        if (! is_writable($this->root)) {
            return ['ok' => false, 'errors' => [__('The storage directory is not writable: :path', ['path' => $this->root])]];
        }

        $public = realpath(public_path());
        $rootReal = realpath($this->root);
        if ($public && $rootReal && str_starts_with($rootReal, $public.DIRECTORY_SEPARATOR)) {
            $errors[] = __('The storage directory must not be inside the public web root.');
        }

        $probe = 'healthcheck-'.bin2hex(random_bytes(6)).'.txt';
        try {
            $this->putStream($probe, fopen('php://memory', 'rb'));
            // php://memory may be empty; write a real payload instead.
            $this->delete($probe);
            file_put_contents($this->absolute($probe), 'next-respack');
            $content = file_get_contents($this->absolute($probe));
            $this->delete($probe);
            if ($content !== 'next-respack') {
                $errors[] = __('Storage read verification failed.');
            }
        } catch (\Throwable $e) {
            $errors[] = $e->getMessage();
            $this->delete($probe);
        }

        $free = @disk_free_space($this->root);

        return [
            'ok' => $errors === [],
            'errors' => $errors,
            'warnings' => [],
            'free_space' => $free !== false ? $free : null,
            'path' => $this->root,
        ];
    }

    private function absolute(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');

        if (str_contains($path, '..')) {
            throw new RuntimeException('Invalid storage path.');
        }

        return rtrim($this->root, '/').'/'.$path;
    }

    private function ensureDirectory(string $dir): void
    {
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException('Unable to create the storage directory.');
        }
    }
}
