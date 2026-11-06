<?php

namespace App\Services\Minecraft;

use App\Models\MinecraftPackFormat;
use App\Models\MinecraftPackFormatHistory;
use App\Models\MinecraftPackFormatSyncLog;
use App\Models\MinecraftPackFormatVersion;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class PackFormatSyncService
{
    public const EDITION = 'java_resource_pack';

    public function __construct(private readonly PackFormatParser $parser)
    {
    }

    public function syncFromWiki(string $trigger = 'manual'): MinecraftPackFormatSyncLog
    {
        try {
            $dataset = $this->parser->fetch();
        } catch (Throwable $e) {
            return $this->failedLog($trigger, $e->getMessage());
        }

        return $this->apply(
            $dataset['records'],
            $trigger,
            $dataset['source_url'],
            $dataset['parser_version'],
            $dataset['raw_payload'],
            $dataset['dataset_version']
        );
    }

    public function apply(
        array $records,
        string $trigger,
        ?string $sourceUrl = null,
        ?string $parserVersion = null,
        ?string $rawPayload = null,
        ?string $datasetVersion = null
    ): MinecraftPackFormatSyncLog {
        $lock = Cache::lock('nextrespack-pack-format-sync', 300);
        if (! $lock->get()) {
            return $this->failedLog($trigger, __('A pack format synchronization is already running.'));
        }

        $log = MinecraftPackFormatSyncLog::create([
            'status' => 'running',
            'trigger' => $trigger,
            'source_url' => $sourceUrl,
            'parser_version' => $parserVersion ?? PackFormatParser::PARSER_VERSION,
            'started_at' => now(),
        ]);

        try {
            $records = $this->parser->validateRecords($records);
            $datasetVersion ??= $this->parser->datasetVersion($records);
            $currentSnapshot = $this->snapshot();
            $previousVersion = $this->currentDatasetVersion();

            if (($currentSnapshot['formats'] ?? []) !== []) {
                MinecraftPackFormatHistory::create([
                    'sync_log_id' => $log->id,
                    'dataset_version' => $previousVersion,
                    'snapshot' => json_encode($currentSnapshot, JSON_UNESCAPED_SLASHES),
                    'note' => __('Snapshot before synchronization').' ('.$trigger.')',
                ]);
            }

            $changes = ['added' => [], 'updated' => [], 'removed' => [], 'manual_conflicts' => []];
            $counts = ['added' => 0, 'updated' => 0, 'unchanged' => 0, 'removed' => 0];

            DB::transaction(function () use ($records, $sourceUrl, $datasetVersion, &$changes, &$counts) {
                $seen = [];
                $existingVersions = MinecraftPackFormatVersion::query()
                    ->where('edition', self::EDITION)
                    ->get()
                    ->keyBy('version_name');

                foreach ($records as $record) {
                    $name = $record['version_name'];
                    $seen[$name] = true;
                    $formatKey = MinecraftPackFormat::makeKey(self::EDITION, (int) $record['format_major'], $record['format_minor'] === null ? null : (int) $record['format_minor']);

                    $format = MinecraftPackFormat::query()->firstOrNew(['format_key' => $formatKey]);

                    if (! $format->exists) {
                        $format->fill([
                            'edition' => self::EDITION,
                            'format_major' => (int) $record['format_major'],
                            'format_minor' => $record['format_minor'] === null ? null : (int) $record['format_minor'],
                            'format_value' => $record['format_value'],
                            'source_url' => $sourceUrl,
                            'source_updated_at' => now(),
                            'dataset_version' => $datasetVersion,
                            'is_manual' => false,
                        ])->save();
                    } elseif (! $format->is_manual && ($format->format_value !== $record['format_value'] || $format->dataset_version !== $datasetVersion)) {
                        $format->fill([
                            'format_major' => (int) $record['format_major'],
                            'format_minor' => $record['format_minor'] === null ? null : (int) $record['format_minor'],
                            'format_value' => $record['format_value'],
                            'source_url' => $sourceUrl,
                            'source_updated_at' => now(),
                            'dataset_version' => $datasetVersion,
                        ])->save();
                    }

                    $type = $record['version_type'];
                    $isSnapshot = $type === 'snapshot';
                    $isPrerelease = $type === 'prerelease';
                    $isRc = $type === 'release_candidate';

                    /** @var MinecraftPackFormatVersion|null $version */
                    $version = $existingVersions->get($name);

                    $attributes = [
                        'minecraft_pack_format_id' => $format->id,
                        'version_type' => $type,
                        'version_channel' => $record['version_channel'] ?? null,
                        'is_snapshot' => $isSnapshot,
                        'is_prerelease' => $isPrerelease,
                        'is_release_candidate' => $isRc,
                        'release_date' => $record['release_date'] ?? null,
                        'source_url' => $sourceUrl,
                        'source_updated_at' => now(),
                    ];

                    if (! $version) {
                        MinecraftPackFormatVersion::create(array_merge($attributes, [
                            'edition' => self::EDITION,
                            'version_name' => $name,
                            'is_manual' => false,
                        ]));
                        $counts['added']++;
                        if (count($changes['added']) < 200) {
                            $changes['added'][] = $name.' → '.$record['format_value'];
                        }
                        continue;
                    }

                    if ($version->is_manual) {
                        $counts['unchanged']++;
                        if (count($changes['manual_conflicts']) < 100) {
                            $changes['manual_conflicts'][] = $name;
                        }
                        continue;
                    }

                    $dirty = false;
                    foreach (['minecraft_pack_format_id', 'version_type', 'version_channel', 'is_snapshot', 'is_prerelease', 'is_release_candidate', 'release_date'] as $key) {
                        $current = $version->getAttribute($key);
                        $new = $attributes[$key] ?? null;
                        if ($key === 'release_date' && $current instanceof \DateTimeInterface) {
                            $current = $current->format('Y-m-d');
                        }
                        if ((string) $current !== (string) $new && ! ($current === null && $new === null)) {
                            $dirty = true;
                            break;
                        }
                    }

                    if ($dirty) {
                        $version->fill($attributes)->save();
                        $counts['updated']++;
                        if (count($changes['updated']) < 200) {
                            $changes['updated'][] = $name.' → '.$record['format_value'];
                        }
                    } else {
                        $counts['unchanged']++;
                    }
                }

                $stale = MinecraftPackFormatVersion::query()
                    ->where('edition', self::EDITION)
                    ->where('is_manual', false)
                    ->get()
                    ->filter(fn ($v) => ! isset($seen[$v->version_name]));

                foreach ($stale as $version) {
                    $version->delete();
                    $counts['removed']++;
                    if (count($changes['removed']) < 200) {
                        $changes['removed'][] = $version->version_name;
                    }
                }

                MinecraftPackFormat::query()
                    ->where('edition', self::EDITION)
                    ->where('is_manual', false)
                    ->whereDoesntHave('versions')
                    ->delete();
            });

            $log->update([
                'status' => 'success',
                'dataset_version' => $datasetVersion,
                'records_added' => $counts['added'],
                'records_updated' => $counts['updated'],
                'records_unchanged' => $counts['unchanged'],
                'records_removed' => $counts['removed'],
                'changes' => $changes,
                'raw_payload' => $rawPayload,
                'finished_at' => now(),
            ]);

            SystemSetting::set('pack_formats_last_synced_at', now()->toIso8601String(), 'minecraft');
            SystemSetting::set('pack_formats_dataset_version', $datasetVersion, 'minecraft');

            $this->pruneHistory();
        } catch (Throwable $e) {
            $log->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
                'finished_at' => now(),
            ]);
            Log::warning('Pack format synchronization failed: '.$e->getMessage());
        } finally {
            $lock->release();
        }

        return $log;
    }

    public function seedBundledIfEmpty(): ?MinecraftPackFormatSyncLog
    {
        if (MinecraftPackFormat::query()->exists()) {
            return null;
        }

        $path = config('nextrespack.pack_formats_bundled');
        if (! is_file($path)) {
            Log::warning('Bundled pack format dataset is missing.', ['path' => $path]);

            return null;
        }

        $json = (string) file_get_contents($path);
        $data = json_decode($json, true);

        if (! is_array($data) || ! isset($data['records']) || ! is_array($data['records'])) {
            Log::warning('Bundled pack format dataset is invalid.');

            return null;
        }

        return $this->apply(
            $data['records'],
            'bundled',
            $data['source_url'] ?? null,
            $data['parser_version'] ?? PackFormatParser::PARSER_VERSION,
            null,
            $data['dataset_version'] ?? null
        );
    }

    public function importJson(string $json, string $trigger = 'import-json'): MinecraftPackFormatSyncLog
    {
        $data = json_decode($json, true);

        if (! is_array($data)) {
            return $this->failedLog($trigger, __('The JSON document could not be decoded.'));
        }

        $records = isset($data['records']) && is_array($data['records']) ? $data['records'] : (array_is_list($data) ? $data : null);

        if (! is_array($records)) {
            return $this->failedLog($trigger, __('The JSON document must contain a "records" array.'));
        }

        return $this->apply(
            $records,
            $trigger,
            $data['source_url'] ?? null,
            $data['parser_version'] ?? 'import',
            $json,
            $data['dataset_version'] ?? null
        );
    }

    public function importCsv(string $csv, string $trigger = 'import-csv'): MinecraftPackFormatSyncLog
    {
        try {
            $lines = preg_split('/\r\n|\r|\n/', trim($csv));
            if (count($lines) < 2) {
                throw new RuntimeException(__('The CSV file is empty.'));
            }

            $delimiter = str_contains($lines[0], ';') && ! str_contains($lines[0], ',') ? ';' : ',';
            $headers = array_map(fn ($h) => strtolower(trim($h, "\"' ")), str_getcsv($lines[0], $delimiter));
            $required = ['version_name'];
            if (! in_array('version_name', $headers, true)) {
                throw new RuntimeException(__('The CSV file must contain a version_name column.'));
            }

            $records = [];
            foreach (array_slice($lines, 1) as $line) {
                if (trim($line) === '') {
                    continue;
                }
                $values = str_getcsv($line, $delimiter);
                $row = [];
                foreach ($headers as $index => $header) {
                    $row[$header] = $values[$index] ?? null;
                }
                $records[] = $row;
            }

            return $this->apply($records, $trigger, null, 'csv-import', null, null);
        } catch (Throwable $e) {
            return $this->failedLog($trigger, $e->getMessage());
        }
    }

    public function rollback(MinecraftPackFormatHistory $history, string $trigger = 'rollback'): MinecraftPackFormatSyncLog
    {
        $lock = Cache::lock('nextrespack-pack-format-sync', 300);
        if (! $lock->get()) {
            return $this->failedLog($trigger, __('A pack format synchronization is already running.'));
        }

        $log = MinecraftPackFormatSyncLog::create([
            'status' => 'running',
            'trigger' => $trigger,
            'source_url' => null,
            'parser_version' => 'rollback',
            'started_at' => now(),
        ]);

        try {
            $snapshot = json_decode((string) $history->snapshot, true);
            if (! is_array($snapshot) || ! isset($snapshot['formats']) || ! is_array($snapshot['formats'])) {
                throw new RuntimeException(__('The selected history snapshot is invalid.'));
            }

            $current = $this->snapshot();
            if (($current['formats'] ?? []) !== []) {
                MinecraftPackFormatHistory::create([
                    'sync_log_id' => $log->id,
                    'dataset_version' => $this->currentDatasetVersion(),
                    'snapshot' => json_encode($current, JSON_UNESCAPED_SLASHES),
                    'note' => __('Snapshot before rollback'),
                ]);
            }

            DB::transaction(function () use ($snapshot) {
                MinecraftPackFormatVersion::query()->delete();
                MinecraftPackFormat::query()->delete();

                foreach ($snapshot['formats'] as $formatData) {
                    $format = MinecraftPackFormat::create([
                        'edition' => self::EDITION,
                        'format_key' => $formatData['format_key'],
                        'format_major' => (int) $formatData['format_major'],
                        'format_minor' => $formatData['format_minor'] === null ? null : (int) $formatData['format_minor'],
                        'format_value' => $formatData['format_value'],
                        'source_url' => $formatData['source_url'] ?? null,
                        'source_updated_at' => $formatData['source_updated_at'] ?? null,
                        'dataset_version' => $formatData['dataset_version'] ?? null,
                        'is_manual' => (bool) ($formatData['is_manual'] ?? false),
                    ]);

                    foreach ($formatData['versions'] ?? [] as $versionData) {
                        MinecraftPackFormatVersion::create([
                            'minecraft_pack_format_id' => $format->id,
                            'edition' => self::EDITION,
                            'version_name' => $versionData['version_name'],
                            'version_type' => $versionData['version_type'],
                            'version_channel' => $versionData['version_channel'] ?? null,
                            'is_snapshot' => (bool) ($versionData['is_snapshot'] ?? false),
                            'is_prerelease' => (bool) ($versionData['is_prerelease'] ?? false),
                            'is_release_candidate' => (bool) ($versionData['is_release_candidate'] ?? false),
                            'release_date' => $versionData['release_date'] ?? null,
                            'source_url' => $versionData['source_url'] ?? null,
                            'source_updated_at' => $versionData['source_updated_at'] ?? null,
                            'is_manual' => (bool) ($versionData['is_manual'] ?? false),
                        ]);
                    }
                }
            });

            $log->update([
                'status' => 'success',
                'dataset_version' => $snapshot['dataset_version'] ?? null,
                'records_unchanged' => count($snapshot['formats']),
                'finished_at' => now(),
                'changes' => ['restored_from_history' => $history->id],
            ]);

            SystemSetting::set('pack_formats_last_synced_at', now()->toIso8601String(), 'minecraft');
            SystemSetting::set('pack_formats_dataset_version', $snapshot['dataset_version'] ?? '', 'minecraft');
        } catch (Throwable $e) {
            $log->update(['status' => 'failed', 'error' => $e->getMessage(), 'finished_at' => now()]);
        } finally {
            $lock->release();
        }

        return $log;
    }

    public function snapshot(): array
    {
        $formats = MinecraftPackFormat::query()
            ->where('edition', self::EDITION)
            ->with('versions')
            ->orderBy('format_major')
            ->orderBy('format_minor')
            ->get();

        return [
            'dataset_version' => $this->currentDatasetVersion(),
            'captured_at' => now()->toIso8601String(),
            'formats' => $formats->map(fn (MinecraftPackFormat $format) => [
                'format_key' => $format->format_key,
                'format_major' => $format->format_major,
                'format_minor' => $format->format_minor,
                'format_value' => $format->format_value,
                'source_url' => $format->source_url,
                'source_updated_at' => $format->source_updated_at?->toIso8601String(),
                'dataset_version' => $format->dataset_version,
                'is_manual' => $format->is_manual,
                'versions' => $format->versions->map(fn (MinecraftPackFormatVersion $v) => [
                    'version_name' => $v->version_name,
                    'version_type' => $v->version_type,
                    'version_channel' => $v->version_channel,
                    'is_snapshot' => $v->is_snapshot,
                    'is_prerelease' => $v->is_prerelease,
                    'is_release_candidate' => $v->is_release_candidate,
                    'release_date' => $v->release_date?->toDateString(),
                    'source_url' => $v->source_url,
                    'source_updated_at' => $v->source_updated_at?->toIso8601String(),
                    'is_manual' => $v->is_manual,
                ])->values()->all(),
            ])->values()->all(),
        ];
    }

    public function currentDatasetVersion(): ?string
    {
        return MinecraftPackFormatSyncLog::query()
            ->where('status', 'success')
            ->whereNotNull('dataset_version')
            ->latest('id')
            ->value('dataset_version');
    }

    public function lastSuccessfulSync(): ?MinecraftPackFormatSyncLog
    {
        return MinecraftPackFormatSyncLog::query()->where('status', 'success')->latest('id')->first();
    }

    public function isDue(): bool
    {
        if (! nr_setting_bool('pack_format_auto_sync', true)) {
            return false;
        }

        $last = $this->lastSuccessfulSync();
        $interval = (int) config('nextrespack.pack_format_sync_interval', 86400);

        return ! $last || $last->finished_at === null || $last->finished_at->lt(now()->subSeconds($interval));
    }

    public function maybeSync(): void
    {
        if (! $this->isDue()) {
            return;
        }

        try {
            $this->syncFromWiki('auto');
        } catch (Throwable $e) {
            Log::warning('Automatic pack format sync failed: '.$e->getMessage());
        }
    }

    private function failedLog(string $trigger, string $error): MinecraftPackFormatSyncLog
    {
        return MinecraftPackFormatSyncLog::create([
            'status' => 'failed',
            'trigger' => $trigger,
            'parser_version' => PackFormatParser::PARSER_VERSION,
            'error' => $error,
            'started_at' => now(),
            'finished_at' => now(),
        ]);
    }

    private function pruneHistory(): void
    {
        $ids = MinecraftPackFormatHistory::query()->latest('id')->skip(50)->limit(100)->pluck('id');
        if ($ids->isNotEmpty()) {
            MinecraftPackFormatHistory::query()->whereIn('id', $ids)->delete();
        }
    }
}
