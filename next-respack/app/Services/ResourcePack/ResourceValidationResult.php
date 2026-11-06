<?php

namespace App\Services\ResourcePack;

use App\Enums\ValidationStatus;

class ResourceValidationResult
{
    public ValidationStatus $status = ValidationStatus::ZipInvalid;

    public array $errors = [];

    public array $warnings = [];

    public array $details = [];

    public ?string $rawMcmeta = null;

    public ?array $metadata = null;

    public ?int $formatMajor = null;

    public ?int $formatMinor = null;

    public ?string $formatValue = null;

    public bool $formatUnknown = false;

    public array $detectedVersions = [];

    public function addError(string $error): void
    {
        $this->errors[] = $error;
    }

    public function addWarning(string $warning): void
    {
        $this->warnings[] = $warning;
    }

    public function addDetail(string $key, mixed $value): void
    {
        $this->details[$key] = $value;
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'details' => $this->details,
            'format' => [
                'major' => $this->formatMajor,
                'minor' => $this->formatMinor,
                'value' => $this->formatValue,
                'unknown' => $this->formatUnknown,
            ],
            'detected_versions' => $this->detectedVersions,
        ];
    }
}
