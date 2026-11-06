<?php

namespace App\Services\ResourcePack;

use App\Services\Minecraft\PackFormatLookup;
use App\Enums\ValidationStatus;

class PackMetadataParser
{
    public function __construct(private readonly PackFormatLookup $lookup)
    {
    }

    public function parse(ResourceValidationResult $result, string $raw): ResourceValidationResult
    {
        $result->rawMcmeta = $raw;

        try {
            $decoded = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $result->status = ValidationStatus::MetadataInvalid;
            $result->addError(__('pack.mcmeta is not valid JSON: :message', ['message' => $e->getMessage()]));

            return $result;
        }

        if (! is_array($decoded) || array_is_list($decoded)) {
            $result->status = ValidationStatus::MetadataInvalid;
            $result->addError(__('pack.mcmeta must contain a JSON object.'));

            return $result;
        }

        $pack = $decoded['pack'] ?? null;
        if (! is_array($pack) || array_is_list($pack)) {
            $result->status = ValidationStatus::MetadataInvalid;
            $result->addError(__('pack.mcmeta must contain a valid "pack" object.'));

            return $result;
        }

        if (! array_key_exists('description', $pack)) {
            $result->status = ValidationStatus::MetadataInvalid;
            $result->addError(__('The "pack" object is missing a description.'));
        } elseif (! is_string($pack['description']) && ! is_array($pack['description'])) {
            $result->status = ValidationStatus::MetadataInvalid;
            $result->addError(__('The pack description must be a string or a JSON text component.'));
        }

        $packFormat = $pack['pack_format'] ?? null;
        $minFormat = $pack['min_format'] ?? null;
        $maxFormat = $pack['max_format'] ?? null;
        $supported = $pack['supported_formats'] ?? null;

        $hasPackFormat = array_key_exists('pack_format', $pack);
        $hasMinMax = array_key_exists('min_format', $pack) || array_key_exists('max_format', $pack);

        if ($hasPackFormat && ! $this->isNumber($packFormat)) {
            $result->status = ValidationStatus::MetadataInvalid;
            $result->addError(__('"pack_format" must be a JSON number.'));
        }

        if ($hasMinMax) {
            if (! array_key_exists('min_format', $pack) || ! array_key_exists('max_format', $pack)) {
                $result->status = ValidationStatus::MetadataInvalid;
                $result->addError(__('Both "min_format" and "max_format" are required when range formats are used.'));
            } elseif (! $this->isNumber($minFormat) || ! $this->isNumber($maxFormat)) {
                $result->status = ValidationStatus::MetadataInvalid;
                $result->addError(__('"min_format" and "max_format" must be JSON numbers.'));
            } elseif ($this->numberValue($minFormat) > $this->numberValue($maxFormat)) {
                $result->status = ValidationStatus::MetadataInvalid;
                $result->addError(__('"min_format" cannot be greater than "max_format".'));
            }
        }

        if ($hasPackFormat && $hasMinMax) {
            $result->addWarning(__('Both "pack_format" and "min_format"/"max_format" are present. pack_format is deprecated in modern Minecraft versions.'));
        }

        if (! $hasPackFormat && ! $hasMinMax) {
            $result->status = ValidationStatus::MetadataInvalid;
            $result->addError(__('pack.mcmeta must define "pack_format" or "min_format" + "max_format".'));
        }

        $ranges = [];

        if ($hasPackFormat && $this->isNumber($packFormat)) {
            $ranges[] = [$this->numberValue($packFormat), $this->numberValue($packFormat), 'pack_format'];
        }

        if ($hasMinMax && $this->isNumber($minFormat) && $this->isNumber($maxFormat)) {
            $ranges[] = [$this->numberValue($minFormat), $this->numberValue($maxFormat), 'min_format/max_format'];
        }

        if ($supported !== null) {
            $supportedRanges = $this->parseSupportedFormats($supported, $result);
            foreach ($supportedRanges as $range) {
                $ranges[] = [$range[0], $range[1], 'supported_formats'];
            }
        }

        if ($result->status === ValidationStatus::MetadataInvalid) {
            return $result;
        }

        if ($ranges === []) {
            $result->status = ValidationStatus::FormatUnknown;
            $result->formatUnknown = true;
            $result->addWarning(__('No usable pack format was found in pack.mcmeta.'));

            return $result;
        }

        return $this->resolveFormats($result, $decoded, $ranges, $pack);
    }

    private function resolveFormats(ResourceValidationResult $result, array $metadata, array $ranges, array $pack): ResourceValidationResult
    {
        $result->metadata = $metadata;
        $primary = $ranges[0];
        $primaryFormats = $this->lookup->formatsBetween($primary[0], $primary[1]);
        $primaryFormat = $this->lookup->findByNumber($primary[0]);

        $result->formatMajor = is_int($primary[0]) ? $primary[0] : (int) floor($primary[0]);
        $result->formatMinor = $this->deriveMinor($primary[0]);
        $result->formatValue = $this->formatNumberValue($primary[0]);

        $allFormats = collect();
        $unknownValues = [];

        foreach ($ranges as $range) {
            $formats = $this->lookup->formatsBetween($range[0], $range[1]);
            if ($formats->isEmpty()) {
                $unknownValues[] = $this->formatNumberValue($range[0]).($range[0] != $range[1] ? '–'.$this->formatNumberValue($range[1]) : '');
            }
            $allFormats = $allFormats->merge($formats);
        }
        $allFormats = $allFormats->unique('id')->values();

        if ($primaryFormat === null && $allFormats->isEmpty()) {
            $result->status = ValidationStatus::FormatUnknown;
            $result->formatUnknown = true;
            $result->addWarning(__('Resource pack format :format is not present in the pack format database.', ['format' => $result->formatValue]));

            return $result;
        }

        if ($unknownValues) {
            $result->addWarning(__('Some declared format ranges are unknown: :formats', ['formats' => implode(', ', array_unique($unknownValues))]));
        }

        $versions = $this->lookup->versionsForFormats($allFormats);
        $detected = [];

        foreach ($versions as $version) {
            $format = $allFormats->firstWhere('id', $version->minecraft_pack_format_id);
            $detected[] = [
                'version_name' => $version->version_name,
                'version_type' => $version->version_type,
                'format' => $format?->format_value ?? '',
            ];
        }

        $result->detectedVersions = $detected;

        if ($primaryFormat === null) {
            $result->formatUnknown = true;
            $result->status = ValidationStatus::FormatUnknown;
            $result->addWarning(__('The main pack format is not in the database, but some supported formats were recognised.'));
        } else {
            $result->formatMajor = $primaryFormat->format_major;
            $result->formatMinor = $primaryFormat->format_minor;
            $result->formatValue = $primaryFormat->format_value;
            $result->status = $result->warnings === [] ? ValidationStatus::Valid : ValidationStatus::ValidWithWarning;
        }

        if ($versions->isEmpty()) {
            $result->addWarning(__('The pack format exists, but no Minecraft versions are mapped to it yet.'));
            $result->status = ValidationStatus::ValidWithWarning;
        }

        $description = $pack['description'] ?? null;
        if (is_array($description) && ! array_is_list($description) && isset($description['text']) && is_string($description['text'])) {
            $result->addDetail('description', $description['text']);
        } elseif (is_array($description) && array_is_list($description)) {
            $result->addDetail('description', $this->flattenTextComponent($description));
        } else {
            $result->addDetail('description', $description);
        }

        return $result;
    }

    private function parseSupportedFormats(mixed $value, ResourceValidationResult $result): array
    {
        $ranges = [];

        if ($this->isNumber($value)) {
            $n = $this->numberValue($value);

            return [[$n, $n]];
        }

        if (is_array($value) && ! array_is_list($value) && isset($value['min_inclusive'], $value['max_inclusive']) && $this->isNumber($value['min_inclusive']) && $this->isNumber($value['max_inclusive'])) {
            return [[$this->numberValue($value['min_inclusive']), $this->numberValue($value['max_inclusive'])]];

        }

        if (is_array($value)) {
            if (array_is_list($value) && count($value) === 2 && $this->isNumber($value[0]) && $this->isNumber($value[1])) {
                return [[$this->numberValue($value[0]), $this->numberValue($value[1])]];
            }

            $ok = true;
            foreach ($value as $item) {
                if ($this->isNumber($item)) {
                    $n = $this->numberValue($item);
                    $ranges[] = [$n, $n];
                } elseif (is_array($item) && ! array_is_list($item) && isset($item['min_inclusive'], $item['max_inclusive']) && $this->isNumber($item['min_inclusive']) && $this->isNumber($item['max_inclusive'])) {
                    $ranges[] = [$this->numberValue($item['min_inclusive']), $this->numberValue($item['max_inclusive'])];
                } elseif (is_array($item) && array_is_list($item) && count($item) === 2 && $this->isNumber($item[0]) && $this->isNumber($item[1])) {
                    $ranges[] = [$this->numberValue($item[0]), $this->numberValue($item[1])];
                } else {
                    $ok = false;
                    break;
                }
            }

            if ($ok && $ranges !== []) {
                return $ranges;
            }
        }

        $result->status = ValidationStatus::MetadataInvalid;
        $result->addError(__('"supported_formats" must be a number, a [min, max] array, an array of ranges, or an object with "min_inclusive" and "max_inclusive".'));

        return [];
    }

    private function isNumber(mixed $value): bool
    {
        return is_int($value) || is_float($value);
    }

    private function numberValue(mixed $value): int|float
    {
        return is_int($value) ? $value : (float) $value;
    }

    private function deriveMinor(int|float $value): ?int
    {
        if (is_int($value) || $value == (int) $value) {
            return null;
        }

        $string = rtrim(rtrim(sprintf('%.6f', $value), '0'), '.');

        return isset(explode('.', $string, 2)[1]) ? (int) explode('.', $string, 2)[1] : null;
    }

    private function formatNumberValue(int|float $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        if (floor($value) == $value) {
            return number_format($value, 1, '.', '');
        }

        return rtrim(rtrim(sprintf('%.6f', $value), '0'), '.');
    }

    private function flattenTextComponent(array $component): string
    {
        $text = '';
        foreach ($component as $part) {
            if (is_string($part)) {
                $text .= $part;
            } elseif (is_array($part)) {
                $text .= $part['text'] ?? (isset($part['extra']) && is_array($part['extra']) ? $this->flattenTextComponent($part['extra']) : '');
            }
        }

        return trim($text);
    }
}
