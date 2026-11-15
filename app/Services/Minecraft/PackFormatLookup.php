<?php

namespace App\Services\Minecraft;

use App\Models\MinecraftPackFormat;
use App\Models\MinecraftPackFormatVersion;
use Illuminate\Support\Collection;

class PackFormatLookup
{
    public const EDITION = 'java_resource_pack';

    public function findByMajorMinor(int $major, ?int $minor): ?MinecraftPackFormat
    {
        try {
            $keys = [MinecraftPackFormat::makeKey(self::EDITION, $major, $minor)];

            if ($minor === null) {
                $keys[] = MinecraftPackFormat::makeKey(self::EDITION, $major, 0);
            } elseif ($minor === 0) {
                $keys[] = MinecraftPackFormat::makeKey(self::EDITION, $major, null);
            }

            return MinecraftPackFormat::query()->whereIn('format_key', $keys)->first();
        } catch (\Throwable) {
            return null;
        }
    }

    public function findByNumber(int|float $value): ?MinecraftPackFormat
    {
        if (is_int($value) || $value == (int) $value) {
            return $this->findByMajorMinor((int) $value, null)
                ?? $this->findByMajorMinor((int) $value, 0);
        }

        $string = rtrim(rtrim(sprintf('%.6f', $value), '0'), '.');
        [$major, $minor] = array_pad(explode('.', $string, 2), 2, null);

        return $this->findByMajorMinor((int) $major, $minor === null ? null : (int) $minor);
    }

    public function findByValueString(string $value): ?MinecraftPackFormat
    {
        $value = trim($value);
        if (! is_numeric($value)) {
            return null;
        }

        return $this->findByNumber($value + 0);
    }

    /** @return Collection<int, MinecraftPackFormat> */
    public function formatsBetween(int|float $min, int|float $max): Collection
    {
        try {
            $formats = MinecraftPackFormat::query()->where('edition', self::EDITION)->get();
        } catch (\Throwable) {
            return collect();
        }

        return $formats->filter(fn (MinecraftPackFormat $f) => $this->compareFormat($f, $min) >= 0 && $this->compareFormat($f, $max) <= 0)->values();
    }

    public function compareFormat(MinecraftPackFormat $format, int|float $value): int
    {
        $formatNumber = (float) $format->format_major;
        if ($format->format_minor !== null) {
            $formatNumber += $format->format_minor / 10;
        }

        return $formatNumber <=> (float) $value;
    }

    public function formatNumber(int $major, ?int $minor): string
    {
        return (string) $major.($minor !== null ? '.'.$minor : '');
    }

    /** @return Collection<int, MinecraftPackFormatVersion> */
    public function versionsForFormats(Collection $formats): Collection
    {
        if ($formats->isEmpty()) {
            return collect();
        }

        try {
            return MinecraftPackFormatVersion::query()
                ->whereIn('minecraft_pack_format_id', $formats->pluck('id'))
                ->orderByDesc('id')
                ->get();
        } catch (\Throwable) {
            return collect();
        }
    }

    public function latestFormats(int $limit = 24): Collection
    {
        try {
            return MinecraftPackFormat::query()
                ->where('edition', self::EDITION)
                ->orderByDesc('format_major')
                ->orderByDesc('format_minor')
                ->limit($limit)
                ->get();
        } catch (\Throwable) {
            return collect();
        }
    }

    public function hasData(): bool
    {
        try {
            return MinecraftPackFormat::query()->exists();
        } catch (\Throwable) {
            return false;
        }
    }
}
