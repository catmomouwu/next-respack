<?php

namespace App\Services\Storage;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WebDavStorageAdapter implements StorageAdapterInterface
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $username = '',
        private readonly string $password = '',
        private readonly string $prefix = '',
        private readonly bool $verifySsl = true,
        private readonly int $timeout = 120,
        private readonly int $connectTimeout = 15,
        private readonly string $root = '',
    ) {
    }

    public function driver(): string
    {
        return 'webdav';
    }

    public function putStream(string $path, $stream): void
    {
        $this->ensureCollection($path);

        $response = $this->request()
            ->withBody($stream, 'application/zip')
            ->put($this->fileUrl($path));

        if ($response->status() >= 400) {
            throw new RuntimeException($this->errorMessage(__('Unable to upload the file to WebDAV'), $response->status()));
        }
    }

    public function readStream(string $path, int $start = 0, ?int $length = null)
    {
        $headers = [];

        if ($start > 0 || $length !== null) {
            $end = $length !== null ? $start + $length - 1 : '';
            $headers['Range'] = 'bytes='.$start.'-'.$end;
        }

        $response = $this->request()
            ->withHeaders($headers)
            ->withOptions(['stream' => true])
            ->get($this->fileUrl($path));

        if ($response->status() >= 400) {
            throw new RuntimeException($this->errorMessage(__('Unable to read the file from WebDAV'), $response->status()));
        }

        $body = $response->toPsrResponse()->getBody();
        $stream = $body->detach();

        if (is_resource($stream)) {
            return $stream;
        }

        if (method_exists($body, 'isReadable') && $body->isReadable()) {
            return $body;
        }

        throw new RuntimeException(__('WebDAV returned an unreadable stream.'));
    }

    public function exists(string $path): bool
    {
        $response = $this->request()->head($this->fileUrl($path));

        if (in_array($response->status(), [200, 204], true)) {
            return true;
        }

        if (! in_array($response->status(), [405, 501], true)) {
            return false;
        }

        return $this->request()
            ->withHeaders(['Depth' => '0'])
            ->send('PROPFIND', $this->fileUrl($path))
            ->status() === 207;
    }

    public function size(string $path): int
    {
        $response = $this->request()->head($this->fileUrl($path));

        if ($response->status() < 400) {
            $length = $response->header('Content-Length');

            if ($length !== '' && $length !== null) {
                return (int) $length;
            }
        }

        $propfind = $this->request()
            ->withHeaders(['Depth' => '0'])
            ->send('PROPFIND', $this->fileUrl($path));

        if (preg_match('/<[^>]*getcontentlength[^>]*>\s*(\d+)\s*</i', $propfind->body(), $match)) {
            return (int) $match[1];
        }

        throw new RuntimeException($this->errorMessage(__('Unable to determine the remote file size'), $propfind->status()));
    }

    public function delete(string $path): void
    {
        $response = $this->request()->delete($this->fileUrl($path));

        if ($response->status() >= 400 && $response->status() !== 404) {
            throw new RuntimeException($this->errorMessage(__('Unable to delete the file from WebDAV'), $response->status()));
        }
    }

    public function hash(string $algorithm, string $path): string
    {
        $stream = $this->readStream($path);
        $ctx = hash_init($algorithm);

        try {
            if (is_resource($stream)) {
                hash_update_stream($ctx, $stream);
            } else {
                while (! $stream->eof()) {
                    $chunk = $stream->read(1024 * 256);
                    if ($chunk === '') {
                        break;
                    }
                    hash_update($ctx, $chunk);
                }
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return hash_final($ctx);
    }

    public function accelPath(string $path): ?string
    {
        return null;
    }

    public function test(): array
    {
        $errors = [];
        $probe = 'healthcheck-'.bin2hex(random_bytes(6)).'.txt';
        $payload = 'next-respack';

        try {
            $body = fopen('php://temp', 'r+');
            fwrite($body, $payload);
            rewind($body);
            $this->putStream($probe, $body);

            if (! $this->exists($probe)) {
                $errors[] = __('WebDAV write verification failed.');
            }

            $stream = $this->readStream($probe);
            $content = is_resource($stream) ? (string) stream_get_contents($stream) : (string) $stream->getContents();

            if (is_resource($stream)) {
                fclose($stream);
            }

            if ($content !== $payload) {
                $errors[] = __('WebDAV read verification failed.');
            }

            if ($this->size($probe) !== strlen($payload)) {
                $errors[] = __('WebDAV size verification failed.');
            }
        } catch (\Throwable $e) {
            $errors[] = __('WebDAV connection failed: :message', ['message' => $e->getMessage()]);
        } finally {
            try {
                $this->delete($probe);
            } catch (\Throwable) {
                // Cleanup is best effort; a leftover probe file is harmless.
            }
        }

        return [
            'ok' => $errors === [],
            'errors' => $errors,
            'warnings' => [],
            'url' => $this->base(),
            'prefix' => $this->prefix,
        ];
    }

    private function request(): PendingRequest
    {
        $request = Http::accept('*/*')
            ->withHeaders(['User-Agent' => 'NextRespack/'.config('nextrespack.version')])
            ->withOptions([
                'verify' => $this->verifySsl,
                'connect_timeout' => max(1, $this->connectTimeout),
                'timeout' => max(1, $this->timeout),
            ]);

        if ($this->username !== '' || $this->password !== '') {
            $request = $request->withBasicAuth($this->username, $this->password);
        }

        return $request;
    }

    private function ensureCollection(string $path): void
    {
        $segments = explode('/', $this->key($path));
        array_pop($segments);

        $current = '';
        foreach ($segments as $segment) {
            $current = $current === '' ? $segment : $current.'/'.$segment;
            $response = $this->request()->send('MKCOL', $this->base().'/'.$this->encodePath($current));

            if (! in_array($response->status(), [200, 201, 204, 301, 405], true)) {
                throw new RuntimeException($this->errorMessage(__('Unable to create the WebDAV directory'), $response->status()));
            }
        }
    }

    /**
     * Build the remote object key and enforce the configured directory boundary.
     */
    private function key(string $path): string
    {
        $relative = $this->normalizePath($path);

        if ($relative === '') {
            throw new RuntimeException('Invalid storage path.');
        }

        $prefix = $this->normalizePath($this->prefix);

        if ($prefix === '') {
            return $relative;
        }

        $key = $prefix.'/'.$relative;

        if (! str_starts_with($key, $prefix.'/')) {
            throw new RuntimeException('Invalid storage path.');
        }

        return $key;
    }

    private function fileUrl(string $path): string
    {
        return $this->base().'/'.$this->encodePath($this->key($path));
    }

    private function encodePath(string $path): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $path)));
    }

    private function base(): string
    {
        $base = rtrim(trim($this->baseUrl), '/');
        $root = $this->normalizePath($this->root);

        return $root === '' ? $base : $base.'/'.$this->encodePath($root);
    }

    /**
     * Normalise a user supplied relative path, rejecting traversal sequences.
     */
    private function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));

        if (str_contains($path, "\0")) {
            throw new RuntimeException('Invalid storage path.');
        }

        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                throw new RuntimeException('Invalid storage path.');
            }

            $segments[] = $segment;
        }

        return implode('/', $segments);
    }

    private function errorMessage(string $prefix, int $status): string
    {
        return $prefix.' (HTTP '.$status.')';
    }
}
