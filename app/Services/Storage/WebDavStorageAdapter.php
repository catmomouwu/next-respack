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

        $stream = $response->toPsrResponse()->getBody()->detach();

        if (! is_resource($stream)) {
            throw new RuntimeException(__('WebDAV returned an unreadable stream.'));
        }

        return $stream;
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
            hash_update_stream($ctx, $stream);
        } finally {
            fclose($stream);
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

        try {
            $this->ensureCollection($probe);

            $body = fopen('php://temp', 'r+');
            fwrite($body, 'next-respack');
            rewind($body);
            $this->putStream($probe, $body);

            if (! $this->exists($probe)) {
                $errors[] = __('WebDAV write verification failed.');
            }

            $stream = $this->readStream($probe);
            $content = (string) stream_get_contents($stream);
            fclose($stream);

            if ($content !== 'next-respack') {
                $errors[] = __('WebDAV read verification failed.');
            }

            $this->delete($probe);
        } catch (\Throwable $e) {
            $errors[] = __('WebDAV connection failed: :message', ['message' => $e->getMessage()]);
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
                'connect_timeout' => 15,
                'timeout' => $this->timeout,
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

    private function key(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');

        if ($path === '' || str_contains($path, '..')) {
            throw new RuntimeException('Invalid storage path.');
        }

        return ($this->prefix !== '' ? trim($this->prefix, '/').'/' : '').$path;
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
        return rtrim($this->baseUrl, '/');
    }

    private function errorMessage(string $prefix, int $status): string
    {
        return $prefix.' (HTTP '.$status.')';
    }
}
