<?php

namespace App\Services\Minecraft;

use App\Models\ResourcePackVersion;
use App\Services\ResourcePack\ResourceValidationResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class PackVersionRecommender
{
    /**
     * Order of preference: stable release, then previews, then snapshots.
     * Experimental mappings are only used when nothing else is available.
     */
    public const TYPE_PRIORITY = [
        'release' => 0,
        'release_candidate' => 1,
        'prerelease' => 2,
        'snapshot' => 3,
        'experimental' => 4,
    ];

    private const MAX_COMPATIBLE_VERSIONS = 50;

    public function __construct(private readonly PackFormatLookup $lookup)
    {
    }

    /**
     * @return array{version_name:string,version_type:string,format_value:?string,compatible_versions:array<int,array{version_name:string,version_type:string,format_value:?string}>}|null
     */
    public function recommendForResult(ResourceValidationResult $result): ?array
    {
        $metadata = is_array($result->metadata) ? ($result->metadata['pack'] ?? null) : null;

        if (is_array($metadata)) {
            return $this->recommendFromPack($metadata);
        }

        if ($result->formatValue !== null) {
            return $this->recommendFromPack(['pack_format' => $result->formatValue]);
        }

        return null;
    }

    /**
     * @return array{version_name:string,version_type:string,format_value:?string,compatible_versions:array<int,array{version_name:string,version_type:string,format_value:?string}>}|null
     */
    public function recommendForVersion(ResourcePackVersion $version): ?array
    {
        $pack = $this->packFromMcmeta($version->pack_mcmeta_raw);

        if ($pack === null && $version->format_value !== null) {
            $pack = ['pack_format' => $version->format_value];
        }

        return $pack === null ? null : $this->recommendFromPack($pack);
    }

    /**
     * @return array{version_name:string,version_type:string,format_value:?string,compatible_versions:array<int,array{version_name:string,version_type:string,format_value:?string}>}|null
     */
    public function recommendFromPack(array $pack): ?array
    {
        $ranges = [];

        $packFormat = $this->toNumber($pack['pack_format'] ?? null);
        if ($packFormat !== null) {
            $ranges[] = [$packFormat, $packFormat];
        }

        $minFormat = $this->toNumber($pack['min_format'] ?? null);
        $maxFormat = $this->toNumber($pack['max_format'] ?? null);
        if ($minFormat !== null && $maxFormat !== null && $minFormat <= $maxFormat) {
            $ranges[] = [$minFormat, $maxFormat];
        }

        foreach ($this->parseSupportedFormats($pack['supported_formats'] ?? null) as $range) {
            $ranges[] = $range;
        }

        if ($ranges === []) {
            return null;
        }

        return $this->recommendRanges($ranges);
    }

    /**
     * @param  array<int, array{0:int|float,1:int|float}>  $ranges
     * @return array{version_name:string,version_type:string,format_value:?string,compatible_versions:array<int,array{version_name:string,version_type:string,format_value:?string}>}|null
     */
    public function recommendRanges(array $ranges): ?array
    {
        $formats = collect();

        foreach ($ranges as [$min, $max]) {
            $formats = $formats->merge($this->lookup->formatsBetween($min, $max));
        }

        /** @var Collection<int, \App\Models\MinecraftPackFormat> $formats */
        $formats = $formats->unique('id')->values();

        if ($formats->isEmpty()) {
            return null;
        }

        $versions = $this->lookup->versionsForFormats($formats);

        if ($versions->isEmpty()) {
            return null;
        }

        $formatsById = $formats->keyBy('id');
        $candidates = [];

        foreach ($versions as $version) {
            $format = $formatsById->get($version->minecraft_pack_format_id);

            $candidates[] = [
                'version_name' => (string) $version->version_name,
                'version_type' => (string) ($version->version_type ?: 'unknown'),
                'format_value' => $format?->format_value,
                'release_date' => $version->release_date?->toDateString(),
            ];
        }

        usort($candidates, $this->compareCandidates(...));

        $primary = array_shift($candidates);

        if ($primary === null) {
            return null;
        }

        $compatible = array_map(fn (array $candidate) => [
            'version_name' => $candidate['version_name'],
            'version_type' => $candidate['version_type'],
            'format_value' => $candidate['format_value'],
        ], array_slice($candidates, 0, self::MAX_COMPATIBLE_VERSIONS));

        return [
            'version_name' => $primary['version_name'],
            'version_type' => $primary['version_type'],
            'format_value' => $primary['format_value'],
            'compatible_versions' => $compatible,
        ];
    }

    /**
     * Recompute the persisted recommendation for every stored version after
     * the pack format dataset changes. Failures never break the sync itself.
     */
    public function refreshStoredVersions(): int
    {
        $updated = 0;

        try {
            ResourcePackVersion::query()
                ->withoutTrashed()
                ->orderBy('id')
                ->chunkById(200, function ($versions) use (&$updated) {
                    /** @var ResourcePackVersion $version */
                    foreach ($versions as $version) {
                        $recommendation = $this->recommendForVersion($version);
                        $name = $recommendation['version_name'] ?? null;
                        $type = $recommendation['version_type'] ?? null;

                        if ($version->recommended_game_version === $name && $version->recommended_version_type === $type) {
                            continue;
                        }

                        $version->forceFill([
                            'recommended_game_version' => $name,
                            'recommended_version_type' => $type,
                        ])->save();

                        $updated++;
                    }
                });
        } catch (Throwable $e) {
            Log::warning('Unable to refresh recommended Minecraft versions: '.$e->getMessage());
        }

        return $updated;
    }

    private function packFromMcmeta(?string $raw): ?array
    {
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded) || ! is_array($decoded['pack'] ?? null)) {
            return null;
        }

        return $decoded['pack'];
    }

    /** @return array<int, array{0:int|float,1:int|float}> */
    private function parseSupportedFormats(mixed $value): array
    {
        $single = $this->toNumber($value);
        if ($single !== null) {
            return [[$single, $single]];
        }

        if (! is_array($value)) {
            return [];
        }

        if (! array_is_list($value)) {
            $min = $this->toNumber($value['min_inclusive'] ?? null);
            $max = $this->toNumber($value['max_inclusive'] ?? null);

            return $min !== null && $max !== null && $min <= $max ? [[$min, $max]] : [];
        }

        if (count($value) === 2) {
            $min = $this->toNumber($value[0] ?? null);
            $max = $this->toNumber($value[1] ?? null);

            if ($min !== null && $max !== null) {
                return [[$min, $max]];
            }
        }

        $ranges = [];

        foreach ($value as $item) {
            $number = $this->toNumber($item);

            if ($number !== null) {
                $ranges[] = [$number, $number];
                continue;
            }

            if (is_array($item) && ! array_is_list($item)) {
                $min = $this->toNumber($item['min_inclusive'] ?? null);
                $max = $this->toNumber($item['max_inclusive'] ?? null);

                if ($min !== null && $max !== null && $min <= $max) {
                    $ranges[] = [$min, $max];
                    continue;
                }

                return [];
            }

            if (is_array($item) && count($item) === 2) {
                $min = $this->toNumber($item[0] ?? null);
                $max = $this->toNumber($item[1] ?? null);

                if ($min !== null && $max !== null) {
                    $ranges[] = [$min, $max];
                    continue;
                }
            }

            return [];
        }

        return $ranges;
    }

    private function toNumber(mixed $value): int|float|null
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric(trim($value))) {
            return trim($value) + 0;
        }

        return null;
    }

    private function compareCandidates(array $a, array $b): int
    {
        $priorityA = self::TYPE_PRIORITY[$a['version_type']] ?? 99;
        $priorityB = self::TYPE_PRIORITY[$b['version_type']] ?? 99;

        if ($priorityA !== $priorityB) {
            return $priorityA <=> $priorityB;
        }

        $dateA = $a['release_date'];
        $dateB = $b['release_date'];

        if ($dateA !== null && $dateB !== null && $dateA !== $dateB) {
            return strcmp($dateB, $dateA);
        }

        return $this->compareVersionNames($b['version_name'], $a['version_name']);
    }

    private function compareVersionNames(string $a, string $b): int
    {
        $partsA = $this->versionSortKey($a);
        $partsB = $this->versionSortKey($b);

        $length = max(count($partsA), count($partsB));

        for ($i = 0; $i < $length; $i++) {
            $left = $partsA[$i] ?? 0;
            $right = $partsB[$i] ?? 0;

            if ($left === $right) {
                continue;
            }

            return $left <=> $right;
        }

        return strnatcasecmp($a, $b);
    }

    /** @return array<int, int> */
    private function versionSortKey(string $name): array
    {
        $name = trim($name);

        if (preg_match('/^(\d+)w(\d+)([a-z])$/i', $name, $matches)) {
            return [3, (int) $matches[1], (int) $matches[2], ord(strtolower($matches[3]))];
        }

        if (preg_match('/^(\d+(?:\.\d+)*)\s+Snapshot\s+(\d+)$/i', $name, $matches)) {
            return array_merge(
                [4],
                array_map('intval', explode('.', $matches[1])),
                [(int) $matches[2]]
            );
        }

        if (preg_match('/^(\d+(?:\.\d+)*)/', $name, $matches)) {
            return array_merge([2], array_map('intval', explode('.', $matches[1])));
        }

        return [1];
    }
}
