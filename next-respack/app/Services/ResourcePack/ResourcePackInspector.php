<?php

namespace App\Services\ResourcePack;

use App\Enums\ValidationStatus;

class ResourcePackInspector
{
    public function __construct(
        private readonly ZipValidator $zipValidator,
        private readonly PackMetadataParser $metadataParser,
    ) {
    }

    public function inspect(string $path): ResourceValidationResult
    {
        $result = $this->zipValidator->validate($path);

        if ($result->status->isFatal() || $result->rawMcmeta === null) {
            return $result;
        }

        $result = $this->metadataParser->parse($result, $result->rawMcmeta);

        if ($result->status === ValidationStatus::FormatUnknown) {
            $result->formatUnknown = true;
        }

        return $result;
    }
}
