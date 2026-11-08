<?php

namespace App\Services\Storage;

use Aws\S3\S3Client;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

class StorageManager
{
    public function currentDriver(): string
    {
        $driver = (string) nr_setting('storage_driver', env('STORAGE_DRIVER', 'local'));

        return in_array($driver, ['local', 's3', 'webdav'], true) ? $driver : 'local';
    }

    public function localPath(): string
    {
        $path = (string) nr_setting('storage_local_path', config('nextrespack.storage_root'));

        return $this->resolveLocalPath($path);
    }

    public function configFor(string $driver): array
    {
        if ($driver === 's3') {
            return [
                'driver' => 's3',
                'endpoint' => nr_setting('s3_endpoint', ''),
                'region' => nr_setting('s3_region', 'us-east-1'),
                'bucket' => nr_setting('s3_bucket', ''),
                'access_key' => nr_setting('s3_access_key', ''),
                'secret_key' => $this->decryptSecret(nr_setting('s3_secret_key', '')),
                'path_style' => nr_setting_bool('s3_path_style', true),
                'prefix' => nr_setting('s3_prefix', ''),
            ];
        }

        if ($driver === 'webdav') {
            return [
                'driver' => 'webdav',
                'url' => nr_setting('webdav_url', ''),
                'username' => nr_setting('webdav_username', ''),
                'password' => $this->decryptSecret(nr_setting('webdav_password', '')),
                'prefix' => nr_setting('webdav_prefix', ''),
                'verify_ssl' => nr_setting_bool('webdav_verify_ssl', true),
            ];
        }

        return ['driver' => 'local', 'path' => $this->localPath()];
    }

    public function adapter(?string $driver = null, ?array $override = null): StorageAdapterInterface
    {
        $driver ??= $this->currentDriver();
        $config = $override ?? $this->configFor($driver);

        if ($driver === 's3') {
            return $this->makeS3($config);
        }

        if ($driver === 'webdav') {
            return $this->makeWebDav($config);
        }

        return new LocalStorageAdapter($this->resolveLocalPath($config['path'] ?? $this->localPath()));
    }

    public function test(string $driver, array $config): array
    {
        if ($driver === 's3') {
            try {
                return $this->makeS3($config)->test();
            } catch (\Throwable $e) {
                return ['ok' => false, 'errors' => [__('Object storage configuration is invalid: :message', ['message' => $e->getMessage()])], 'warnings' => []];
            }
        }

        if ($driver === 'webdav') {
            try {
                return $this->makeWebDav($config)->test();
            } catch (\Throwable $e) {
                return ['ok' => false, 'errors' => [__('WebDAV configuration is invalid: :message', ['message' => $e->getMessage()])], 'warnings' => []];
            }
        }

        return (new LocalStorageAdapter($this->resolveLocalPath($config['path'] ?? $this->localPath())))->test();
    }

    public function makeS3(array $config): S3StorageAdapter
    {
        $bucket = trim((string) ($config['bucket'] ?? ''));
        if ($bucket === '') {
            throw new RuntimeException('S3 bucket is required.');
        }

        $s3config = [
            'version' => 'latest',
            'region' => (string) ($config['region'] ?? 'us-east-1') ?: 'us-east-1',
            'use_path_style_endpoint' => (bool) ($config['path_style'] ?? false),
            'credentials' => [
                'key' => (string) ($config['access_key'] ?? ''),
                'secret' => (string) ($config['secret_key'] ?? ''),
            ],
        ];

        if (! empty($config['endpoint'])) {
            $s3config['endpoint'] = (string) $config['endpoint'];
        }

        $client = new S3Client($s3config);

        return new S3StorageAdapter($client, $bucket, (string) ($config['prefix'] ?? ''));
    }

    public function makeWebDav(array $config): WebDavStorageAdapter
    {
        $url = trim((string) ($config['url'] ?? ''));

        if ($url === '') {
            throw new RuntimeException('A WebDAV URL is required.');
        }

        if (! preg_match('#^https?://#i', $url)) {
            throw new RuntimeException('The WebDAV URL must start with http:// or https://.');
        }

        return new WebDavStorageAdapter(
            baseUrl: $url,
            username: (string) ($config['username'] ?? ''),
            password: (string) ($config['password'] ?? ''),
            prefix: (string) ($config['prefix'] ?? ''),
            verifySsl: (bool) ($config['verify_ssl'] ?? true),
        );
    }

    public function encryptSecret(string $value): string
    {
        return $value === '' ? '' : Crypt::encryptString($value);
    }

    public function decryptSecret(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            return '';
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return '';
        }
    }

    public function resolveLocalPath(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            $path = (string) config('nextrespack.storage_root');
        }

        if (! str_starts_with($path, DIRECTORY_SEPARATOR)) {
            $path = base_path($path);
        }

        $public = realpath(public_path());
        if ($public !== false) {
            $normalized = str_replace('\\', '/', $path);
            if (str_starts_with($normalized, str_replace('\\', '/', $public).'/')) {
                throw new RuntimeException('Private resource pack storage cannot be located inside the public web root.');
            }
        }

        return rtrim($path, '/');
    }
}
