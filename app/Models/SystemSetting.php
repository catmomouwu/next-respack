<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value', 'group', 'type'];

    private const CACHE_KEY = 'nextrespack.settings';

    public static function allCached(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return self::query()->pluck('value', 'key')->all();
        });
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $settings = self::allCached();

        return array_key_exists($key, $settings) ? $settings[$key] : $default;
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $value = self::get($key);

        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public static function getInt(string $key, int $default = 0): int
    {
        $value = self::get($key);

        return $value === null || $value === '' ? $default : (int) $value;
    }

    public static function set(string $key, mixed $value, string $group = 'general', string $type = 'string'): void
    {
        self::query()->updateOrCreate(['key' => $key], [
            'value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value,
            'group' => $group,
            'type' => $type,
        ]);

        Cache::forget(self::CACHE_KEY);
    }

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
