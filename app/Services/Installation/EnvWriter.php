<?php

namespace App\Services\Installation;

use RuntimeException;

class EnvWriter
{
    public function path(): string
    {
        return (string) config('nextrespack.env_file');
    }

    public function isWritable(): bool
    {
        $path = $this->path();

        if (is_file($path)) {
            return is_writable($path);
        }

        return is_writable(dirname($path));
    }

    /**
     * Write a complete .env file atomically. Sensitive values are never logged.
     */
    public function write(array $values): void
    {
        $path = $this->path();

        if (! $this->isWritable()) {
            throw new RuntimeException('The .env file is not writable. Please grant write permission to the project root or the .env file.');
        }

        if (is_file($path)) {
            @copy($path, $path.'.backup-'.date('YmdHis'));
        }

        $content = $this->render($values);
        $tmp = tempnam(dirname($path), '.nrenv');

        if ($tmp === false) {
            throw new RuntimeException('Unable to create a temporary environment file.');
        }

        if (file_put_contents($tmp, $content) === false) {
            @unlink($tmp);
            throw new RuntimeException('Unable to write the environment file.');
        }

        @chmod($tmp, 0600);

        if (! @rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException('Unable to replace the .env file.');
        }

        @chmod($path, 0600);
    }

    public function render(array $values): string
    {
        $defaults = [
            'APP_NAME' => 'Next Respack',
            'APP_ENV' => 'production',
            'APP_KEY' => '',
            'APP_DEBUG' => 'false',
            'APP_URL' => 'http://localhost',
            'APP_INSTALLED' => 'false',
            'APP_LOCALE' => 'zh_CN',
            'APP_FALLBACK_LOCALE' => 'en',
            'APP_FAKER_LOCALE' => 'en_US',
            'APP_MAINTENANCE_DRIVER' => 'file',
            'BCRYPT_ROUNDS' => '12',
            'LOG_CHANNEL' => 'stack',
            'LOG_STACK' => 'single',
            'LOG_DEPRECATIONS_CHANNEL' => 'null',
            'LOG_LEVEL' => 'warning',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => '127.0.0.1',
            'DB_PORT' => '3306',
            'DB_DATABASE' => '',
            'DB_USERNAME' => '',
            'DB_PASSWORD' => '',
            'DB_CHARSET' => 'utf8mb4',
            'DB_COLLATION' => 'utf8mb4_unicode_ci',
            'SESSION_DRIVER' => 'file',
            'SESSION_LIFETIME' => '120',
            'SESSION_ENCRYPT' => 'false',
            'SESSION_SECURE_COOKIE' => 'null',
            'BROADCAST_CONNECTION' => 'log',
            'FILESYSTEM_DISK' => 'local',
            'QUEUE_CONNECTION' => 'sync',
            'CACHE_STORE' => 'file',
            'CACHE_PREFIX' => 'nextrespack',
            'MAIL_MAILER' => 'log',
            'MAIL_HOST' => '127.0.0.1',
            'MAIL_PORT' => '2525',
            'MAIL_USERNAME' => '',
            'MAIL_PASSWORD' => '',
            'MAIL_ENCRYPTION' => 'null',
            'MAIL_FROM_ADDRESS' => 'no-reply@example.com',
            'MAIL_FROM_NAME' => '${APP_NAME}',
            'VITE_APP_NAME' => '${APP_NAME}',
        ];

        $merged = array_merge($defaults, $values);

        $sections = [
            'APP' => ['APP_NAME', 'APP_ENV', 'APP_KEY', 'APP_DEBUG', 'APP_URL', 'APP_INSTALLED', 'APP_LOCALE', 'APP_FALLBACK_LOCALE', 'APP_FAKER_LOCALE', 'APP_MAINTENANCE_DRIVER', 'BCRYPT_ROUNDS'],
            'LOG' => ['LOG_CHANNEL', 'LOG_STACK', 'LOG_DEPRECATIONS_CHANNEL', 'LOG_LEVEL'],
            'DATABASE' => ['DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'DB_CHARSET', 'DB_COLLATION'],
            'SESSION' => ['SESSION_DRIVER', 'SESSION_LIFETIME', 'SESSION_ENCRYPT', 'SESSION_SECURE_COOKIE'],
            'FILESYSTEM' => ['FILESYSTEM_DISK'],
            'BROADCAST' => ['BROADCAST_CONNECTION'],
            'QUEUE' => ['QUEUE_CONNECTION'],
            'CACHE' => ['CACHE_STORE', 'CACHE_PREFIX'],
            'MAIL' => ['MAIL_MAILER', 'MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_ENCRYPTION', 'MAIL_FROM_ADDRESS', 'MAIL_FROM_NAME'],
            'VITE' => ['VITE_APP_NAME'],
        ];

        $out = '# Next Respack environment configuration.'."\n"
            .'# This file contains secrets. Never expose it or commit it to a repository.'."\n\n";

        foreach ($sections as $title => $keys) {
            $out .= '### '.$title.' ###'."\n";

            foreach ($keys as $key) {
                $out .= $key.'='.$this->escape((string) ($merged[$key] ?? ''))."\n";
            }

            $out .= "\n";
        }

        return $out;
    }

    public function escape(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (preg_match('/^[A-Za-z0-9_\.\-\/:@$%+*\(\)\[\]{}<>,;=!?|~^`\\\\]+$/', $value) && ! str_contains($value, '#')) {
            return $value;
        }

        return '"'.str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', ''], $value).'"';
    }
}
