<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ResourcePackVersion;
use App\Models\StorageMigration;
use App\Models\SystemSetting;
use App\Services\Security\AuditLogger;
use App\Services\Storage\StorageManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class StorageController extends Controller
{
    public function index(StorageManager $storage, AuditLogger $audit): View
    {
        $driver = $storage->currentDriver();
        $usage = [
            'files' => ResourcePackVersion::query()->count(),
            'bytes' => (int) ResourcePackVersion::query()->sum('file_size'),
            'local_bytes' => (int) ResourcePackVersion::query()->where('storage_driver', 'local')->sum('file_size'),
            's3_bytes' => (int) ResourcePackVersion::query()->where('storage_driver', 's3')->sum('file_size'),
        ];

        $localFree = null;
        if ($driver === 'local') {
            try {
                $localFree = @disk_free_space($storage->localPath());
            } catch (Throwable) {
            }
        }

        $migrations = StorageMigration::query()->latest()->limit(10)->get();
        $settings = [
            'driver' => $driver,
            'local_path' => $storage->localPath(),
            's3_endpoint' => nr_setting('s3_endpoint', ''),
            's3_region' => nr_setting('s3_region', 'us-east-1'),
            's3_bucket' => nr_setting('s3_bucket', ''),
            's3_access_key' => nr_setting('s3_access_key', ''),
            's3_path_style' => nr_setting_bool('s3_path_style', true),
            's3_prefix' => nr_setting('s3_prefix', ''),
            'secret_set' => nr_setting('s3_secret_key', '') !== '',
        ];

        return view('admin.storage.index', compact('driver', 'usage', 'localFree', 'migrations', 'settings'));
    }

    public function test(Request $request, StorageManager $storage): JsonResponse
    {
        $driver = (string) $request->input('target_driver', $storage->currentDriver());

        if ($driver === 's3') {
            $config = [
                'driver' => 's3',
                'endpoint' => (string) $request->input('s3_endpoint', ''),
                'region' => (string) $request->input('s3_region', 'us-east-1'),
                'bucket' => (string) $request->input('s3_bucket', ''),
                'access_key' => (string) $request->input('s3_access_key', ''),
                'secret_key' => (string) $request->input('s3_secret_key', ''),
                'path_style' => (bool) $request->input('s3_path_style', true),
                'prefix' => (string) $request->input('s3_prefix', ''),
            ];

            if ($config['secret_key'] === '' && nr_setting('s3_secret_key', '') !== '') {
                $config['secret_key'] = $storage->decryptSecret(nr_setting('s3_secret_key', ''));
            }
        } else {
            $config = ['driver' => 'local', 'path' => (string) $request->input('local_path', $storage->localPath())];
        }

        $result = $storage->test($driver, $config);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function plan(Request $request, StorageManager $storage, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'target_driver' => ['required', 'in:local,s3'],
            'local_path' => ['nullable', 'string', 'max:255'],
            's3_endpoint' => ['nullable', 'url', 'max:191'],
            's3_region' => ['nullable', 'string', 'max:64'],
            's3_bucket' => ['nullable', 'string', 'max:191'],
            's3_access_key' => ['nullable', 'string', 'max:191'],
            's3_secret_key' => ['nullable', 'string', 'max:191'],
            's3_prefix' => ['nullable', 'string', 'max:191'],
        ]);

        $target = $data['target_driver'];
        $config = $this->targetConfig($data);

        if ($target === 's3' && empty($data['s3_bucket'])) {
            return back()->withErrors(['s3_bucket' => __('An S3 bucket is required.')]);
        }

        if ($this->targetIsActive($target, $config, $storage)) {
            return back()->withErrors(['target_driver' => __('The target backend is already active.')]);
        }

        if ($target === 's3' && empty($data['s3_secret_key']) && nr_setting('s3_secret_key', '') !== '') {
            $config['secret_key'] = $storage->decryptSecret(nr_setting('s3_secret_key', ''));
        }

        $result = $storage->test($target, $config);
        if (! $result['ok']) {
            return back()->withInput()->withErrors(['storage' => implode(' ', $result['errors'])]);
        }

        $stats = $config;
        if (isset($stats['secret_key'])) {
            $stats['secret_key'] = $storage->encryptSecret((string) $stats['secret_key']);
        }
        $stats['files'] = ResourcePackVersion::query()->count();
        $stats['bytes'] = (int) ResourcePackVersion::query()->sum('file_size');

        $migration = StorageMigration::create([
            'from_driver' => $storage->currentDriver(),
            'to_driver' => $target,
            'status' => 'planned',
            'stats' => $stats,
        ]);

        $audit->log('admin.storage_migration_planned', $migration, __('Storage migration plan created.'), ['to' => $target]);

        return redirect()->route('admin.storage.index')->with('status', __('Migration plan created. Existing download links still use the old backend until you run the migration.'));
    }

    public function run(StorageMigration $migration, StorageManager $storage, AuditLogger $audit): RedirectResponse
    {
        if ($migration->status === 'running' || $migration->status === 'completed') {
            return back()->withErrors(['migration' => __('This migration cannot be run again.')]);
        }

        $stats = $migration->stats ?? [];
        $config = $stats;
        if (isset($config['secret_key'])) {
            $config['secret_key'] = $storage->decryptSecret((string) $config['secret_key']);
        }

        if ($this->targetIsActive($migration->to_driver, $config, $storage)) {
            return back()->withErrors(['migration' => __('The target backend is already active.')]);
        }

        @set_time_limit(0);

        $migration->update(['status' => 'running', 'started_at' => now(), 'error' => null]);

        $targetDriver = $migration->to_driver;
        $target = $storage->adapter($targetDriver, $config);
        $copied = 0;
        $bytes = 0;
        $versions = ResourcePackVersion::query()->get();

        try {
            foreach ($versions as $version) {
                $source = $storage->adapter($version->storage_driver);
                if (! $source->exists($version->storage_path)) {
                    throw new \RuntimeException(__('Source file is missing for version #:id.', ['id' => $version->id]));
                }

                if (! $target->exists($version->storage_path)) {
                    $stream = $source->readStream($version->storage_path);
                    try {
                        $target->putStream($version->storage_path, $stream);
                    } finally {
                        if (is_resource($stream)) {
                            fclose($stream);
                        }
                    }
                }

                $targetSize = $target->size($version->storage_path);
                if ($targetSize !== (int) $version->file_size) {
                    throw new \RuntimeException(__('Size verification failed for version #:id.', ['id' => $version->id]));
                }

                $targetHash = $target->hash('sha1', $version->storage_path);
                if ($targetHash !== $version->sha1) {
                    throw new \RuntimeException(__('SHA-1 verification failed for version #:id.', ['id' => $version->id]));
                }

                $copied++;
                $bytes += (int) $version->file_size;
            }

            DB::transaction(function () use ($versions, $targetDriver, $stats, $migration) {
                foreach ($versions as $version) {
                    $version->storage_driver = $targetDriver;
                    $version->save();
                }

                $this->applyTargetSettings($stats);
                $migration->update([
                    'status' => 'completed',
                    'finished_at' => now(),
                    'stats' => array_merge($migration->stats ?? [], [
                        'copied_files' => $versions->count(),
                        'copied_bytes' => (int) $versions->sum('file_size'),
                    ]),
                ]);
            });

            $audit->log('admin.storage_migration_completed', $migration, __('Storage migration completed.'), ['files' => $copied, 'bytes' => $bytes]);

            return redirect()->route('admin.storage.index')->with('status', __('Storage migration completed. :files files copied and verified.', ['files' => $copied]));
        } catch (Throwable $e) {
            $migration->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error' => $e->getMessage(),
                'stats' => array_merge($stats, ['copied_files' => $copied, 'copied_bytes' => $bytes]),
            ]);
            Log::warning('Storage migration failed: '.$e->getMessage());

            return back()->withErrors(['migration' => __('Migration failed: :message', ['message' => $e->getMessage()])]);
        }
    }

    public function cleanOrphans(StorageManager $storage, AuditLogger $audit): RedirectResponse
    {
        if ($storage->currentDriver() !== 'local') {
            return back()->withErrors(['storage' => __('Orphan cleanup is currently implemented for local storage only.')]);
        }

        $root = $storage->localPath();
        if (! is_dir($root)) {
            return back()->withErrors(['storage' => __('The local storage directory does not exist.')]);
        }

        $known = ResourcePackVersion::query()->where('storage_driver', 'local')->pluck('storage_path')->all();
        $known = array_flip($known);
        $deleted = 0;
        $bytes = 0;

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }
            $relative = ltrim(str_replace($root, '', $file->getPathname()), '/');
            if (in_array($relative, ['.htaccess', 'index.html'], true) || str_contains($relative, '.part-')) {
                continue;
            }
            if (! isset($known[$relative])) {
                $bytes += $file->getSize();
                @unlink($file->getPathname());
                $deleted++;
            }
        }

        $audit->log('admin.orphan_files_cleaned', null, __('Orphan storage files cleaned.'), ['files' => $deleted, 'bytes' => $bytes]);

        return back()->with('status', __('Orphan cleanup complete: :files files (:bytes) removed.', ['files' => $deleted, 'bytes' => nr_bytes($bytes)]));
    }

    private function targetConfig(array $data): array
    {
        if ($data['target_driver'] === 's3') {
            return [
                'driver' => 's3',
                'endpoint' => $data['s3_endpoint'] ?? '',
                'region' => $data['s3_region'] ?? 'us-east-1',
                'bucket' => $data['s3_bucket'] ?? '',
                'access_key' => $data['s3_access_key'] ?? '',
                'secret_key' => $data['s3_secret_key'] ?? '',
                'path_style' => (bool) ($data['s3_path_style'] ?? true),
                'prefix' => $data['s3_prefix'] ?? '',
            ];
        }

        return ['driver' => 'local', 'path' => $data['local_path'] ?: config('nextrespack.storage_root')];
    }

    private function targetIsActive(string $driver, array $config, StorageManager $storage): bool
    {
        if ($driver === 'local') {
            try {
                $target = $storage->resolveLocalPath((string) ($config['path'] ?? config('nextrespack.storage_root')));
            } catch (Throwable) {
                return false;
            }

            return $target === $storage->localPath();
        }

        if ($driver !== $storage->currentDriver()) {
            return false;
        }

        return (string) ($config['bucket'] ?? '') === (string) nr_setting('s3_bucket', '')
            && (string) ($config['prefix'] ?? '') === (string) nr_setting('s3_prefix', '')
            && (string) ($config['endpoint'] ?? '') === (string) nr_setting('s3_endpoint', '');
    }

    private function applyTargetSettings(array $stats): void
    {
        SystemSetting::set('storage_driver', $stats['driver'], 'storage');

        if ($stats['driver'] === 's3') {
            SystemSetting::set('s3_endpoint', $stats['endpoint'] ?? '', 'storage');
            SystemSetting::set('s3_region', $stats['region'] ?? 'us-east-1', 'storage');
            SystemSetting::set('s3_bucket', $stats['bucket'] ?? '', 'storage');
            SystemSetting::set('s3_access_key', $stats['access_key'] ?? '', 'storage');
            SystemSetting::set('s3_secret_key', $stats['secret_key'] ?? '', 'storage');
            SystemSetting::set('s3_path_style', ! empty($stats['path_style']), 'storage', 'bool');
            SystemSetting::set('s3_prefix', $stats['prefix'] ?? '', 'storage');
        } else {
            SystemSetting::set('storage_local_path', $stats['path'] ?? config('nextrespack.storage_root'), 'storage');
        }

        SystemSetting::forgetCache();
    }
}
