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
        'recommended_game_version', 'recommended_version_type',
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

    /**
     * Pick a single recommended Minecraft version from the persisted
     * recommendation, falling back to detected candidates for records that
     * predate the recommender. Official releases win; pre-releases and
     * snapshots are only a fallback.
     *
     * @return array{version_name:string, version_type:string, format_value:?string}|null
     */
    public function suggestedVersion(): ?array
    {
        if ($this->recommended_game_version !== null && $this->recommended_game_version !== '') {
            return [
                'version_name' => (string) $this->recommended_game_version,
                'version_type' => (string) ($this->recommended_version_type ?: 'unknown'),
                'format_value' => $this->format_value,
            ];
        }

        $candidates = is_array($this->detected_versions) ? $this->detected_versions : [];

        $candidates = array_values(array_filter($candidates, fn ($entry) => is_array($entry)
            && ! empty($entry['version_name'])));

        foreach (['release', 'release_candidate', 'prerelease', 'snapshot', 'experimental'] as $type) {
            foreach ($candidates as $entry) {
                if ((string) ($entry['version_type'] ?? '') === $type) {
                    return [
                        'version_name' => (string) $entry['version_name'],
                        'version_type' => $type,
                        'format_value' => $entry['format'] ?? $this->format_value,
                    ];
                }
            }
        }

        return null;
    }

    public static function versionTypeLabel(?string $type): string
    {
        return match ($type) {
            'release' => __('Release'),
            'release_candidate' => __('Release candidate'),
            'prerelease' => __('Pre-release'),
            'snapshot' => __('Snapshot'),
            'experimental' => __('Experimental'),
            default => __('Unknown'),
        };
    }

    public function formatLabel(): string
    {
        if ($this->format_value) {
            return $this->format_value;
        }

        return $this->format_unknown ? __('Unknown') : '—';
    }
}
