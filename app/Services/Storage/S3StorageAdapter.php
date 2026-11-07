<?php

namespace App\Services\Storage;

use Aws\S3\S3Client;
use RuntimeException;

class S3StorageAdapter implements StorageAdapterInterface
{
    public function __construct(
        private readonly S3Client $client,
        private readonly string $bucket,
        private readonly string $prefix = '',
    ) {
    }

    public function driver(): string
    {
        return 's3';
    }

    public function putStream(string $path, $stream): void
    {
        $this->client->putObject([
            'Bucket' => $this->bucket,
            'Key' => $this->key($path),
            'Body' => $stream,
            'ContentType' => 'application/zip',
        ]);
    }

    public function readStream(string $path, int $start = 0, ?int $length = null)
    {
        $params = ['Bucket' => $this->bucket, 'Key' => $this->key($path)];

        if ($start > 0 || $length !== null) {
            $end = $length !== null ? $start + $length - 1 : '';
            $params['Range'] = 'bytes='.$start.'-'.$end;
        }

        $result = $this->client->getObject($params);

        return $result['Body'];
    }

    public function exists(string $path): bool
    {
        try {
            $this->client->headObject(['Bucket' => $this->bucket, 'Key' => $this->key($path)]);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function size(string $path): int
    {
        $result = $this->client->headObject(['Bucket' => $this->bucket, 'Key' => $this->key($path)]);

        return (int) ($result['ContentLength'] ?? 0);
    }

    public function delete(string $path): void
    {
        $this->client->deleteObject(['Bucket' => $this->bucket, 'Key' => $this->key($path)]);
    }

    public function hash(string $algorithm, string $path): string
    {
        $body = $this->readStream($path);
        $ctx = hash_init($algorithm);

        if (is_resource($body)) {
            hash_update_stream($ctx, $body);
            fclose($body);
        } else {
            while (! $body->eof()) {
                $chunk = $body->read(1024 * 256);
                if ($chunk === '') {
                    break;
                }
                hash_update($ctx, $chunk);
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
        $probe = $this->key('healthcheck-'.bin2hex(random_bytes(6)).'.txt');

        try {
            $this->client->putObject(['Bucket' => $this->bucket, 'Key' => $probe, 'Body' => 'next-respack']);
            $head = $this->client->headObject(['Bucket' => $this->bucket, 'Key' => $probe]);
            if ((int) ($head['ContentLength'] ?? 0) === 0) {
                $errors[] = __('Object storage size verification failed.');
            }
            $object = $this->client->getObject(['Bucket' => $this->bucket, 'Key' => $probe]);
            $content = (string) $object['Body'];
            if ($content !== 'next-respack') {
                $errors[] = __('Object storage read verification failed.');
            }
            $this->client->deleteObject(['Bucket' => $this->bucket, 'Key' => $probe]);
        } catch (\Throwable $e) {
            $errors[] = __('Object storage connection failed: :message', ['message' => $e->getMessage()]);
        }

        return [
            'ok' => $errors === [],
            'errors' => $errors,
            'warnings' => [],
            'bucket' => $this->bucket,
            'prefix' => $this->prefix,
        ];
    }

    private function key(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');

        if (str_contains($path, '..')) {
            throw new RuntimeException('Invalid storage path.');
        }

        return ($this->prefix !== '' ? rtrim($this->prefix, '/').'/' : '').$path;
    }
}
