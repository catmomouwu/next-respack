<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ResourcePackVersion extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'public_id', 'resource_pack_id', 'version_number', 'original_filename', 'stored_filename',
        'storage_driver', 'storage_path', 'file_size', 'sha1', 'token_hash', 'token_generation',
        'download_enabled', 'downloads_count', 'validation_status', 'validation_details',
        'format_value', 'format_major', 'format_minor', 'format_unknown', 'detected_versions',
        'pack_mcmeta_raw',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'version_number' => 'integer',
            'token_generation' => 'integer',
            'download_enabled' => 'boolean',
            'downloads_count' => 'integer',
            'format_major' => 'integer',
            'format_minor' => 'integer',
            'format_unknown' => 'boolean',
            'validation_details' => 'array',
            'detected_versions' => 'array',
        ];
    }

    public function pack(): BelongsTo
    {
        return $this->belongsTo(ResourcePack::class, 'resource_pack_id');
    }

    public function downloads(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DownloadLog::class);
    }

    public function displayName(): string
    {
        return $this->original_filename;
    }

    public function formatLabel(): string
    {
        if ($this->format_value) {
            return $this->format_value;
        }

        return $this->format_unknown ? __('Unknown') : '—';
    }

    /**
     * Recommended Minecraft version for this pack: newest stable release
     * preferred, falling back to the newest entry of any channel.
     */
    public function recommendedVersionName(): ?string
    {
        $versions = is_array($this->detected_versions) ? $this->detected_versions : [];
        $fallback = null;

        foreach ($versions as $item) {
            $name = is_array($item) ? (string) ($item['version_name'] ?? '') : (string) $item;

            if ($name === '') {
                continue;
            }

            $fallback ??= $name;

            if (is_array($item) && ($item['version_type'] ?? null) === 'release') {
                return $name;
            }
        }

        return $fallback;
    }
}
