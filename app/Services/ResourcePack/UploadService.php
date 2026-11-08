<?php

namespace App\Services\ResourcePack;

use App\Models\ResourcePack;
use App\Models\ResourcePackVersion;
use App\Models\User;
use App\Models\UserQuota;
use App\Services\Download\DownloadTokenService;
use App\Services\Storage\StorageManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class UploadService
{
    public function __construct(
        private readonly ResourcePackInspector $inspector,
        private readonly StorageManager $storage,
        private readonly QuotaService $quota,
        private readonly DownloadTokenService $tokens,
    ) {
    }

    public function createPack(User $user, UploadedFile $file, array $input = []): ResourcePack
    {
        return $this->persist($user, null, $file, $input);
    }

    public function createGuestPack(UploadedFile $file, array $input = []): ResourcePack
    {
        return $this->persist(null, null, $file, $input, true);
    }

    public function addVersion(User $user, ResourcePack $pack, UploadedFile $file, array $input = []): ResourcePackVersion
    {
        if ($pack->user_id !== $user->id) {
            throw ValidationException::withMessages(['file' => __('You do not own this resource pack.')]);
        }

        $result = $this->persist($user, $pack, $file, $input);

        return $result->currentVersion;
    }

    private function persist(?User $user, ?ResourcePack $pack, UploadedFile $file, array $input, bool $guest = false): ResourcePack
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages(['file' => __('The upload failed or was interrupted. Please try again.')]);
        }

        $maxMb = $guest
            ? max(1, (int) config('nextrespack.guest_upload_mb', 200))
            : (int) nr_setting('max_upload_mb', (int) config('nextrespack.max_upload_mb', 512));
        $maxBytes = $maxMb * 1024 * 1024;
        $size = (int) $file->getSize();

        if ($size > $maxBytes) {
            throw ValidationException::withMessages(['file' => __('The file is too large. The maximum allowed size is :max.', ['max' => nr_bytes($maxBytes)])]);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if ($extension !== 'zip') {
            throw ValidationException::withMessages(['file' => __('Only .zip files are accepted.')]);
        }

        $mime = strtolower((string) $file->getMimeType());
        $allowedMimes = ['application/zip', 'application/x-zip', 'application/x-zip-compressed', 'application/octet-stream', 'multipart/x-zip'];

        if ($mime !== '' && ! in_array($mime, $allowedMimes, true)) {
            throw ValidationException::withMessages(['file' => __('The uploaded file does not look like a ZIP archive (detected MIME type: :mime).', ['mime' => $mime])]);
        }

        if ($user !== null && ! $this->quota->check($user, $size)) {
            $usage = $this->quota->usage($user);
            throw ValidationException::withMessages(['file' => __('Storage quota exceeded. Used :used of :limit.', ['used' => nr_bytes($usage['used']), 'limit' => nr_bytes($usage['limit'])])]);
        }

        $result = $this->inspector->inspect($file->getRealPath());

        if ($result->status->isFatal()) {
            throw ValidationException::withMessages([
                'file' => $result->status->label().': '.implode(' ', $result->errors),
            ]);
        }

        $sha1Input = (string) hash_file('sha1', $file->getRealPath());

        if ($user !== null && empty($input['allow_duplicate']) && $this->duplicateExists($user, $sha1Input, $pack?->id)) {
            throw ValidationException::withMessages([
                'file' => __('This exact ZIP file has already been uploaded by your account.'),
            ]);
        }

        $adapter = $this->storage->adapter();
        $storedFilename = (string) Str::uuid7().'.zip';
        $path = $storedFilename;
        $stream = fopen($file->getRealPath(), 'rb');

        if ($stream === false) {
            throw ValidationException::withMessages(['file' => __('Unable to read the uploaded file.')]);
        }

        try {
            $adapter->putStream($path, $stream);
        } finally {
            fclose($stream);
        }

        try {
            $storedSize = $adapter->size($path);
            if ($storedSize !== $size) {
                throw new \RuntimeException('Storage size verification failed.');
            }

            $sha1Final = $adapter->hash('sha1', $path);
            if ($sha1Final !== $sha1Input) {
                throw new \RuntimeException('Storage checksum verification failed.');
            }

            $pack = DB::transaction(function () use ($user, $pack, $file, $input, $result, $size, $sha1Final, $storedFilename, $path, $adapter, $guest) {
                $quota = null;

                if ($user !== null) {
                    $quota = UserQuota::query()->where('user_id', $user->id)->lockForUpdate()->firstOrCreate(
                        ['user_id' => $user->id],
                        ['used_bytes' => 0, 'pack_count' => 0]
                    );

                    $limit = $user->storage_quota ?: (int) config('nextrespack.default_quota_mb', 2048) * 1024 * 1024;
                    if ($quota->used_bytes + $size > $limit) {
                        throw ValidationException::withMessages(['file' => __('Storage quota exceeded.')]);
                    }
                }

                $isNewPack = $pack === null;

                if ($isNewPack) {
                    $originalName = $file->getClientOriginalName();
                    $name = trim((string) ($input['name'] ?? ''));
                    if ($name === '') {
                        $name = preg_replace('/\.zip$/i', '', $originalName) ?: __('Resource pack');
                    }
                    $name = Str::limit($name, 180, '');
                    $description = trim((string) ($input['description'] ?? ''));
                    $desc = $result->details['description'] ?? null;
                    $description = $description !== '' ? $description : (is_string($desc) ? $desc : '');

                    $pack = ResourcePack::create([
                        'public_id' => (string) Str::uuid7(),
                        'user_id' => $user?->id,
                        'name' => $name,
                        'description' => $description,
                        'status' => ResourcePack::STATUS_ACTIVE,
                        'guest_expires_at' => $guest
                            ? now()->addDays(max(1, (int) config('nextrespack.guest_retention_days', 7)))
                            : null,
                    ]);
                }

                $versionNumber = (int) $pack->versions()->max('version_number') + 1;
                $descriptionValue = trim((string) ($input['description'] ?? ''));
                if ($descriptionValue !== '' && $isNewPack === false) {
                    $pack->description = $descriptionValue;
                }

                $version = ResourcePackVersion::create([
                    'public_id' => (string) Str::uuid7(),
                    'resource_pack_id' => $pack->id,
                    'version_number' => $versionNumber,
                    'original_filename' => $this->safeFilename($file->getClientOriginalName()),
                    'stored_filename' => $storedFilename,
                    'storage_driver' => $adapter->driver(),
                    'storage_path' => $path,
                    'file_size' => $size,
                    'sha1' => $sha1Final,
                    'token_generation' => 1,
                    'download_enabled' => true,
                    'validation_status' => $result->status->value,
                    'validation_details' => $result->toArray(),
                    'format_value' => $result->formatValue,
                    'format_major' => $result->formatMajor,
                    'format_minor' => $result->formatMinor,
                    'format_unknown' => $result->formatUnknown,
                    'detected_versions' => $result->detectedVersions,
                    'pack_mcmeta_raw' => $result->rawMcmeta,
                ]);

                $this->tokens->syncHash($version);

                $pack->current_version_id = $version->id;
                $pack->save();

                if ($quota !== null) {
                    $quota->used_bytes += $size;
                    if ($isNewPack) {
                        $quota->pack_count += 1;
                    }
                    $quota->save();
                }

                return $pack->fresh(['currentVersion']);
            });
        } catch (Throwable $e) {
            try {
                $adapter->delete($path);
            } catch (Throwable) {
                Log::warning('Unable to remove a failed upload from storage.', ['path' => $path]);
            }
            throw $e;
        }

        return $pack;
    }

    public function deleteVersion(ResourcePackVersion $version): void
    {
        $pack = $version->pack;
        $adapter = $this->storage->adapter($version->storage_driver);
        $size = (int) $version->file_size;
        $path = $version->storage_path;

        DB::transaction(function () use ($version, $pack, $size) {
            $version->delete();

            if ($pack->user_id !== null) {
                $quota = UserQuota::query()->where('user_id', $pack->user_id)->lockForUpdate()->first();
                if ($quota) {
                    $quota->used_bytes = max(0, $quota->used_bytes - $size);
                    $quota->save();
                }
            }

            if ($pack->current_version_id === $version->id) {
                $next = $pack->versions()->latest('version_number')->first();
                $pack->current_version_id = $next?->id;
                $pack->save();
            }
        });

        try {
            $adapter->delete($path);
        } catch (Throwable $e) {
            Log::warning('Unable to delete a resource pack version file.', ['path' => $path, 'error' => $e->getMessage()]);
        }
    }

    public function deletePack(ResourcePack $pack): void
    {
        $versions = $pack->versions()->get();
        $paths = [];
        $total = 0;

        foreach ($versions as $version) {
            $paths[] = [$version->storage_driver, $version->storage_path];
            $total += (int) $version->file_size;
        }

        DB::transaction(function () use ($pack, $versions, $total) {
            foreach ($versions as $version) {
                $version->delete();
            }

            $pack->delete();

            if ($pack->user_id !== null) {
                $quota = UserQuota::query()->where('user_id', $pack->user_id)->lockForUpdate()->first();
                if ($quota) {
                    $quota->used_bytes = max(0, $quota->used_bytes - $total);
                    $quota->pack_count = max(0, $quota->pack_count - 1);
                    $quota->save();
                }
            }
        });

        foreach ($paths as [$driver, $path]) {
            try {
                $this->storage->adapter($driver)->delete($path);
            } catch (Throwable $e) {
                Log::warning('Unable to delete an orphaned pack file.', ['path' => $path, 'error' => $e->getMessage()]);
            }
        }
    }

    private function duplicateExists(User $user, string $sha1, ?int $exceptPackId): bool
    {
        return ResourcePackVersion::query()
            ->withoutTrashed()
            ->where('sha1', $sha1)
            ->whereHas('pack', function ($q) use ($user, $exceptPackId) {
                $q->withoutTrashed()->where('user_id', $user->id);
                if ($exceptPackId) {
                    $q->where('id', '!=', $exceptPackId);
                }
            })
            ->exists();
    }

    private function safeFilename(string $name): string
    {
        $name = str_replace(['\\', '/', "\0"], '', basename($name));
        $name = preg_replace('/[^\pL\pN ._\-()\[\]]+/u', '_', $name) ?: 'resource-pack.zip';

        return Str::limit($name, 200, '');
    }
}
