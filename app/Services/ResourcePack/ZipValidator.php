<?php

namespace App\Services\ResourcePack;

use App\Enums\ValidationStatus;
use ZipArchive;

class ZipValidator
{
    public function validate(string $path): ResourceValidationResult
    {
        $result = new ResourceValidationResult;
        $result->status = ValidationStatus::ZipInvalid;

        if (! is_file($path)) {
            $result->addError(__('The uploaded file could not be found.'));

            return $result;
        }

        $size = filesize($path);
        $result->addDetail('archive_size', $size);

        if ($size === 0) {
            $result->addError(__('The uploaded file is empty.'));

            return $result;
        }

        $handle = @fopen($path, 'rb');
        $signature = $handle ? fread($handle, 4) : '';
        if ($handle) {
            fclose($handle);
        }

        if (! in_array($signature, ["PK\x03\x04", "PK\x05\x06"], true)) {
            $result->addError(__('The file is not a valid ZIP archive (invalid signature).'));

            return $result;
        }

        $zip = new ZipArchive;
        $openResult = $zip->open($path, ZipArchive::CHECKCONS);

        if ($openResult !== true) {
            $result->addError($this->openError($openResult));

            return $result;
        }

        $maxEntries = (int) config('nextrespack.max_zip_entries', 10000);
        $maxUncompressed = (int) config('nextrespack.max_uncompressed_bytes', 2 * 1024 ** 3);
        $maxRatio = (float) config('nextrespack.max_compression_ratio', 1000);

        try {
            if ($zip->numFiles <= 0) {
                $result->addError(__('The ZIP archive is empty.'));

                return $result;
            }

            if ($zip->numFiles > $maxEntries) {
                $result->addError(__('The ZIP archive contains too many files (:count, maximum :max).', ['count' => $zip->numFiles, 'max' => $maxEntries]));

                return $result;
            }

            $totalUncompressed = 0;
            $seen = [];
            $packMcmeta = null;
            $hasFiles = false;

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if ($stat === false) {
                    $result->addError(__('Unable to read a ZIP entry. The archive is corrupt.'));

                    return $result;
                }

                $name = (string) $stat['name'];
                $isDirectory = str_ends_with($name, '/');

                if ($this->unsafeName($name)) {
                    $result->addError(__('Unsafe path detected in the ZIP archive: :name', ['name' => $name]));

                    return $result;
                }

                if ($this->isSymlink($zip, $i)) {
                    $result->addError(__('Symbolic links are not allowed in resource packs: :name', ['name' => $name]));

                    return $result;
                }

                if (isset($stat['encryption_method']) && (int) $stat['encryption_method'] !== 0) {
                    $result->addError(__('Encrypted ZIP entries are not supported.'));

                    return $result;
                }

                if (isset($seen[$name])) {
                    $result->addError(__('Duplicate ZIP entry: :name', ['name' => $name]));

                    return $result;
                }
                $seen[$name] = true;

                if ($isDirectory) {
                    continue;
                }

                $hasFiles = true;
                $uncompressed = (int) ($stat['size'] ?? 0);
                $compressed = (int) ($stat['comp_size'] ?? 0);
                $totalUncompressed += $uncompressed;

                if ($uncompressed > $maxUncompressed) {
                    $result->addError(__('The unpacked size exceeds the safety limit.'));

                    return $result;
                }

                if ($uncompressed > 1024 * 1024 && $compressed > 0 && ($uncompressed / $compressed) > $maxRatio) {
                    $result->addError(__('A ZIP entry has a suspicious compression ratio and may be a ZIP bomb.'));

                    return $result;
                }

                if ($name === 'pack.mcmeta') {
                    $packMcmeta = $i;
                }
            }

            if (! $hasFiles) {
                $result->addError(__('The ZIP archive does not contain any files.'));

                return $result;
            }

            if ($totalUncompressed > $maxUncompressed) {
                $result->addError(__('The unpacked resource pack exceeds the safety limit.'));

                return $result;
            }

            if ($size > 0 && ($totalUncompressed / $size) > $maxRatio) {
                $result->addError(__('The archive compression ratio is suspicious and may be a ZIP bomb.'));

                return $result;
            }

            $result->addDetail('entry_count', $zip->numFiles);
            $result->addDetail('uncompressed_size', $totalUncompressed);

            if ($packMcmeta === null) {
                $result->status = ValidationStatus::PackInvalid;
                $result->addError(__('pack.mcmeta was not found at the ZIP root. A Minecraft resource pack must contain pack.mcmeta in the root directory.'));

                return $result;
            }

            $maxMeta = (int) config('nextrespack.max_mcmeta_bytes', 1024 * 1024);
            $metaStat = $zip->statIndex($packMcmeta);
            if ((int) ($metaStat['size'] ?? 0) > $maxMeta) {
                $result->status = ValidationStatus::MetadataInvalid;
                $result->addError(__('pack.mcmeta is unreasonably large.'));

                return $result;
            }

            $raw = $zip->getFromIndex($packMcmeta);
            if ($raw === false) {
                $result->status = ValidationStatus::MetadataInvalid;
                $result->addError(__('Unable to read pack.mcmeta. The ZIP entry may be corrupt or encrypted.'));

                return $result;
            }

            $result->rawMcmeta = $raw;

            if ($zip->locateName('pack.png') !== false) {
                $result->addDetail('has_pack_png', true);
            }

            $result->status = ValidationStatus::Valid;
        } finally {
            $zip->close();
        }

        return $result;
    }

    private function unsafeName(string $name): bool
    {
        if ($name === '' || strlen($name) > 255) {
            return true;
        }

        if (str_contains($name, "\0")) {
            return true;
        }

        $normalized = str_replace('\\', '/', $name);

        if (str_starts_with($normalized, '/') || preg_match('/^[A-Za-z]:/', $normalized)) {
            return true;
        }

        $parts = explode('/', $normalized);
        foreach ($parts as $part) {
            if ($part === '..') {
                return true;
            }
            if (preg_match('/[\x00-\x1F\x7F]/', $part)) {
                return true;
            }
        }

        return false;
    }

    private function isSymlink(ZipArchive $zip, int $index): bool
    {
        if (! method_exists($zip, 'getExternalAttributesIndex')) {
            return false;
        }

        $opsys = 0;
        $attr = 0;
        if (! $zip->getExternalAttributesIndex($index, $opsys, $attr)) {
            return false;
        }

        // Unix file type bits: 0120000 = symbolic link.
        return (($attr >> 16) & 0170000) === 0120000;
    }

    private function openError(int $code): string
    {
        return match ($code) {
            ZipArchive::ER_NOZIP => __('The file is not a ZIP archive.'),
            ZipArchive::ER_INCONS => __('The ZIP archive is inconsistent or corrupt (CRC check failed).'),
            ZipArchive::ER_CRC => __('The ZIP archive failed its CRC check.'),
            ZipArchive::ER_ENCRYPTED => __('Encrypted ZIP archives are not supported.'),
            ZipArchive::ER_MEMORY => __('Not enough memory to inspect the ZIP archive.'),
            ZipArchive::ER_READ, ZipArchive::ER_OPEN => __('Unable to read the uploaded ZIP file.'),
            ZipArchive::ER_NOENT => __('The uploaded ZIP file does not exist.'),
            default => __('Invalid ZIP archive (error code :code).', ['code' => $code]),
        };
    }
}
