<?php

use App\Models\SystemSetting;

if (! function_exists('nr_bytes')) {
    function nr_bytes(int|float|null $bytes, int $precision = 2): string
    {
        $bytes = (float) $bytes;

        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);

        return round($bytes / (1024 ** $power), $precision).' '.$units[$power];
    }
}

if (! function_exists('nr_setting')) {
    function nr_setting(string $key, mixed $default = null): mixed
    {
        try {
            return SystemSetting::get($key, $default);
        } catch (\Throwable) {
            return $default;
        }
    }
}

if (! function_exists('nr_setting_bool')) {
    function nr_setting_bool(string $key, bool $default = false): bool
    {
        try {
            return SystemSetting::getBool($key, $default);
        } catch (\Throwable) {
            return $default;
        }
    }
}

if (! function_exists('nr_setting_int')) {
    function nr_setting_int(string $key, int $default = 0): int
    {
        try {
            return SystemSetting::getInt($key, $default);
        } catch (\Throwable) {
            return $default;
        }
    }
}

if (! function_exists('nr_http_secure')) {
    function nr_http_secure(): bool
    {
        return request()->isSecure() || str_starts_with((string) config('app.url'), 'https://');
    }
}
