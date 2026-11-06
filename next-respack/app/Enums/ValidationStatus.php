<?php

namespace App\Enums;

enum ValidationStatus: string
{
    case ZipInvalid = 'ZIP_INVALID';
    case PackInvalid = 'PACK_INVALID';
    case MetadataInvalid = 'METADATA_INVALID';
    case FormatUnknown = 'FORMAT_UNKNOWN';
    case Valid = 'VALID';
    case ValidWithWarning = 'VALID_WITH_WARNING';

    public function isStorable(): bool
    {
        return ! in_array($this, [self::ZipInvalid, self::PackInvalid, self::MetadataInvalid], true);
    }

    public function isFatal(): bool
    {
        return ! $this->isStorable();
    }

    public function label(): string
    {
        return match ($this) {
            self::ZipInvalid => __('Invalid ZIP file'),
            self::PackInvalid => __('Not a Minecraft resource pack'),
            self::MetadataInvalid => __('Invalid pack.mcmeta'),
            self::FormatUnknown => __('Unknown pack format'),
            self::Valid => __('Valid'),
            self::ValidWithWarning => __('Valid with warnings'),
        };
    }
}
