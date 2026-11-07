<?php

namespace App\Services\Installation;

use App\Enums\UserRole;
use App\Models\Installation;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Minecraft\PackFormatSyncService;
use App\Services\Storage\StorageManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class Installer
{
    public function __construct(
        private readonly InstallState $state,
        private readonly EnvWriter $env,
        private readonly DatabaseTester $database,
        private readonly StorageManager $storage,
        private readonly PackFormatSyncService $packFormats,
    ) {
    }

    public function installed(): bool
    {
        return $this->state->installed();
    }

    /**
     * @return array{admin_email:string, admin_username:string, url:string, version:string}
     */
    public function install(array $input): array
    {
        if ($this->installed()) {
            throw new RuntimeException(__('Next Respack is already installed. The installer has been disabled.'));
        }

        $lockPath = storage_path('framework/install.lock');
        if (! is_dir(dirname($lockPath))) {
            @mkdir(dirname($lockPath), 0775, true);
        }

        $lock = fopen($lockPath, 'c');
        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException(__('Another installation is already running. Please wait and try again.'));
        }

        try {
            if ($this->installed()) {
                throw new RuntimeException(__('Next Respack is already installed.'));
            }

            $db = $input['database'] ?? [];
            $storage = $input['storage'] ?? [];
            $site = $input['site'] ?? [];
            $admin = $input['admin'] ?? [];

            $this->validateInput($db, $storage, $site, $admin);

            $dbTest = $this->database->test($db);
            if (! $dbTest['ok']) {
                throw new RuntimeException(implode(' ', $dbTest['errors']));
            }

            $storageTest = $this->storage->test($storage['driver'] ?? 'local', $this->storageTestConfig($storage));
            if (! $storageTest['ok']) {
                throw new RuntimeException(implode(' ', $storageTest['errors']));
            }

            $this->state->saveState([
                'status' => 'running',
                'step' => 'environment',
                'message' => null,
                'started_at' => now()->toIso8601String(),
            ]);

            @unlink(base_path('bootstrap/cache/config.php'));
            @unlink(base_path('bootstrap/cache/routes-v7.php'));

            $key = (string) config('app.key');
            if ($key === '') {
                $key = 'base64:'.base64_encode(random_bytes(32));
            }
            config(['app.key' => $key, 'app.url' => $site['url'], 'app.name' => $site['name']]);
            config(['session.driver' => 'file', 'cache.default' => 'file']);
            config(['app.locale' => $site['locale'] ?? 'zh_CN', 'app.fallback_locale' => 'en']);

            $envValues = $this->envValues($db, $site, $key, false);
            $this->env->write($envValues);

            $this->configureDatabase($db);
            config(['app.key' => $key]);

            $this->state->saveState(['status' => 'running', 'step' => 'database', 'message' => null]);

            $exitCode = Artisan::call('migrate', ['--force' => true]);
            if ($exitCode !== 0) {
                throw new RuntimeException(trim(Artisan::output()) ?: __('Database migration failed.'));
            }

            $this->state->saveState(['status' => 'running', 'step' => 'admin', 'message' => null]);

            $adminUser = $this->createAdmin($admin);
            $this->saveSiteSettings($site, $adminUser);

            $this->state->saveState(['status' => 'running', 'step' => 'storage', 'message' => null]);
            $this->saveStorageSettings($storage);
            $this->initializeStorage($storage);

            $this->state->saveState(['status' => 'running', 'step' => 'pack_formats', 'message' => null]);
            try {
                $this->packFormats->seedBundledIfEmpty();
            } catch (Throwable $e) {
                Log::channel('single')->warning('Bundled pack format import failed during installation: '.$e->getMessage());
            }

            $this->state->saveState(['status' => 'running', 'step' => 'finalize', 'message' => null]);

            Installation::create([
                'version' => (string) config('nextrespack.version'),
                'installed_at' => now(),
                'admin_user_id' => $adminUser->id,
                'meta' => [
                    'database_driver' => $db['driver'],
                    'storage_driver' => $storage['driver'],
                    'locale' => $site['locale'] ?? 'zh_CN',
                ],
            ]);

            $this->env->write($this->envValues($db, $site, $key, true));
            $this->state->writeLock([
                'product' => 'Next Respack',
                'version' => (string) config('nextrespack.version'),
                'installed_at' => now()->toIso8601String(),
                'admin_email' => $admin['email'],
            ]);
            $this->state->clearState();

            $installerKey = (string) config('nextrespack.installer_key');
            if (is_file($installerKey)) {
                @unlink($installerKey);
            }

            try {
                Artisan::call('config:clear');
                Artisan::call('cache:clear');
            } catch (Throwable) {
                // Cache clearing is best effort during installation.
            }

            return [
                'admin_email' => $admin['email'],
                'admin_username' => $admin['username'],
                'url' => rtrim($site['url'], '/'),
                'version' => (string) config('nextrespack.version'),
            ];
        } catch (Throwable $e) {
            $this->state->saveState([
                'status' => 'failed',
                'step' => $this->state->state()['step'] ?? 'unknown',
                'message' => $e->getMessage(),
                'failed_at' => now()->toIso8601String(),
            ]);
            Log::channel('single')->error('Installation failed: '.$e->getMessage());

            throw $e;
        } finally {
            if (isset($lock) && is_resource($lock)) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }

    private function validateInput(array $db, array $storage, array $site, array $admin): void
    {
        if (! in_array($db['driver'] ?? null, ['mysql', 'mariadb', 'sqlite'], true)) {
            throw new RuntimeException(__('Choose a supported database driver.'));
        }

        if (($db['driver'] ?? '') !== 'sqlite') {
            foreach (['host', 'database', 'username'] as $field) {
                if (trim((string) ($db[$field] ?? '')) === '') {
                    throw new RuntimeException(__('The database field ":field" is required.', ['field' => $field]));
                }
            }
        }

        if (! in_array($storage['driver'] ?? 'local', ['local', 's3'], true)) {
            throw new RuntimeException(__('Choose a supported storage backend.'));
        }

        if (($storage['driver'] ?? 'local') === 's3') {
            foreach (['bucket', 'access_key', 'secret_key'] as $field) {
                if (trim((string) ($storage[$field] ?? '')) === '') {
                    throw new RuntimeException(__('The object storage field ":field" is required.', ['field' => $field]));
                }
            }
        }

        if (trim((string) ($site['name'] ?? '')) === '') {
            throw new RuntimeException(__('Site name is required.'));
        }

        $url = rtrim(trim((string) ($site['url'] ?? '')), '/');
        if (! filter_var($url, FILTER_VALIDATE_URL) || ! preg_match('#^https?://#i', $url)) {
            throw new RuntimeException(__('Site URL must be a valid http:// or https:// URL.'));
        }

        if (trim((string) ($admin['username'] ?? '')) === '') {
            throw new RuntimeException(__('Administrator username is required.'));
        }
        if (! preg_match('/^[A-Za-z0-9_\-\.]{3,64}$/', (string) $admin['username'])) {
            throw new RuntimeException(__('Administrator username must be 3-64 characters using letters, numbers, underscores, dots or dashes.'));
        }
        if (! filter_var($admin['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException(__('Administrator email is invalid.'));
        }
        if (strlen((string) ($admin['password'] ?? '')) < 10) {
            throw new RuntimeException(__('Administrator password must be at least 10 characters.'));
        }
    }

    private function envValues(array $db, array $site, string $key, bool $installed): array
    {
        $driver = $db['driver'] === 'mariadb' ? 'mysql' : $db['driver'];

        $values = [
            'APP_NAME' => $site['name'],
            'APP_ENV' => 'production',
            'APP_KEY' => $key,
            'APP_DEBUG' => 'false',
            'APP_URL' => rtrim($site['url'], '/'),
            'APP_INSTALLED' => $installed ? 'true' : 'false',
            'APP_LOCALE' => $site['locale'] ?? 'zh_CN',
            'APP_FALLBACK_LOCALE' => 'en',
            'DB_CONNECTION' => $driver,
            'DB_HOST' => $driver === 'sqlite' ? '' : ($db['host'] ?? '127.0.0.1'),
            'DB_PORT' => $driver === 'sqlite' ? '' : (string) ($db['port'] ?? 3306),
            'DB_DATABASE' => $driver === 'sqlite' ? $this->database->sqlitePath($db) : ($db['database'] ?? ''),
            'DB_USERNAME' => $driver === 'sqlite' ? '' : ($db['username'] ?? ''),
            'DB_PASSWORD' => $driver === 'sqlite' ? '' : ($db['password'] ?? ''),
            'DB_CHARSET' => $db['charset'] ?? 'utf8mb4',
            'DB_COLLATION' => $db['collation'] ?? 'utf8mb4_unicode_ci',
            'SESSION_DRIVER' => 'file',
            'SESSION_SECURE_COOKIE' => str_starts_with((string) $site['url'], 'https://') ? 'true' : '',
            'CACHE_STORE' => 'file',
            'QUEUE_CONNECTION' => 'sync',
            'MAIL_MAILER' => 'log',
            'MAIL_FROM_ADDRESS' => $site['mail_from'] ?? ('no-reply@'.(parse_url($site['url'], PHP_URL_HOST) ?: 'example.com')),
        ];

        return $values;
    }

    private function configureDatabase(array $db): void
    {
        $driver = $db['driver'] === 'mariadb' ? 'mysql' : $db['driver'];

        if ($driver === 'sqlite') {
            $database = $this->database->sqlitePath($db);
            config([
                'database.default' => 'sqlite',
                'database.connections.sqlite.database' => $database,
                'database.connections.sqlite.foreign_key_constraints' => true,
            ]);
            DB::purge('sqlite');
            DB::reconnect('sqlite');

            return;
        }

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.host' => $db['host'],
            'database.connections.mysql.port' => (int) $db['port'],
            'database.connections.mysql.database' => $db['database'],
            'database.connections.mysql.username' => $db['username'],
            'database.connections.mysql.password' => $db['password'],
            'database.connections.mysql.charset' => $db['charset'] ?? 'utf8mb4',
            'database.connections.mysql.collation' => $db['collation'] ?? 'utf8mb4_unicode_ci',
            'database.connections.mysql.prefix' => '',
        ]);

        DB::purge('mysql');
        DB::reconnect('mysql');

        if ($db['driver'] === 'mariadb') {
            config(['database.default' => 'mariadb']);
            config(['database.connections.mariadb' => config('database.connections.mysql')]);
            DB::purge('mariadb');
            DB::reconnect('mariadb');
        }
    }

    private function createAdmin(array $admin): User
    {
        $user = User::withTrashed()->where('email', $admin['email'])->first();

        if ($user) {
            if ($user->trashed()) {
                $user->restore();
            }
            $user->fill([
                'username' => $admin['username'],
                'password' => Hash::make($admin['password']),
                'role' => UserRole::SuperAdmin,
                'is_active' => true,
                'email_verified_at' => now(),
                'storage_quota' => (int) (nr_setting('default_quota_mb', config('nextrespack.default_quota_mb', 2048))) * 1024 * 1024,
            ])->save();
        } else {
            $user = User::create([
                'username' => $admin['username'],
                'email' => $admin['email'],
                'password' => Hash::make($admin['password']),
                'role' => UserRole::SuperAdmin,
                'is_active' => true,
                'email_verified_at' => now(),
                'storage_quota' => (int) config('nextrespack.default_quota_mb', 2048) * 1024 * 1024,
            ]);
        }

        $user->ensureQuota();

        return $user;
    }

    private function saveSiteSettings(array $site, User $admin): void
    {
        $quotaMb = max(64, (int) ($site['default_quota_mb'] ?? config('nextrespack.default_quota_mb', 2048)));

        $settings = [
            'site_name' => [$site['name'], 'general'],
            'site_description' => [$site['description'] ?? '', 'general'],
            'site_url' => [rtrim($site['url'], '/'), 'general'],
            'default_locale' => [$site['locale'] ?? 'zh_CN', 'general'],
            'timezone' => [$site['timezone'] ?? 'Asia/Shanghai', 'general'],
            'allow_registration' => [! empty($site['allow_registration']), 'registration'],
            'email_verification' => [! empty($site['email_verification']), 'registration'],
            'admin_email' => [$admin->email, 'general'],
            'max_upload_mb' => [max(1, (int) ($site['max_upload_mb'] ?? config('nextrespack.max_upload_mb', 512))), 'uploads'],
            'default_quota_mb' => [$quotaMb, 'uploads'],
            'registration_quota_mb' => [max(64, (int) ($site['registration_quota_mb'] ?? 1024)), 'uploads'],
            'download_mode' => ['php', 'downloads'],
            'download_ip_logging' => [true, 'downloads'],
            'pack_format_auto_sync' => [true, 'minecraft'],
            'maintenance_message' => ['', 'general'],
        ];

        foreach ($settings as $key => [$value, $group]) {
            SystemSetting::set($key, is_bool($value) ? ($value ? '1' : '0') : $value, $group, is_bool($value) ? 'bool' : (is_int($value) ? 'int' : 'string'));
        }

        $admin->storage_quota = $quotaMb * 1024 * 1024;
        $admin->save();
    }

    private function saveStorageSettings(array $storage): void
    {
        SystemSetting::set('storage_driver', $storage['driver'], 'storage');
        SystemSetting::set('storage_local_path', $storage['local_path'] ?? config('nextrespack.storage_root'), 'storage');

        if ($storage['driver'] === 's3') {
            SystemSetting::set('s3_endpoint', $storage['endpoint'] ?? '', 'storage');
            SystemSetting::set('s3_region', $storage['region'] ?? 'us-east-1', 'storage');
            SystemSetting::set('s3_bucket', $storage['bucket'] ?? '', 'storage');
            SystemSetting::set('s3_access_key', $storage['access_key'] ?? '', 'storage');
            SystemSetting::set('s3_secret_key', $this->storage->encryptSecret($storage['secret_key'] ?? ''), 'storage');
            SystemSetting::set('s3_path_style', ! empty($storage['path_style']), 'storage', 'bool');
            SystemSetting::set('s3_prefix', $storage['prefix'] ?? '', 'storage');
        }

        SystemSetting::forgetCache();
    }

    private function initializeStorage(array $storage): void
    {
        if ($storage['driver'] === 'local') {
            $manager = $this->storage;
            $adapter = $manager->adapter('local', ['path' => $storage['local_path'] ?? config('nextrespack.storage_root')]);
            $result = $adapter->test();
            if (! $result['ok']) {
                throw new RuntimeException(implode(' ', $result['errors']));
            }

            $root = $manager->resolveLocalPath($storage['local_path'] ?? config('nextrespack.storage_root'));
            $this->writeStorageProtectionFiles($root);

            return;
        }

        $result = $this->storage->adapter('s3', $this->storageTestConfig($storage))->test();
        if (! $result['ok']) {
            throw new RuntimeException(implode(' ', $result['errors']));
        }
    }

    private function writeStorageProtectionFiles(string $root): void
    {
        if (! is_dir($root)) {
            @mkdir($root, 0775, true);
        }

        @file_put_contents($root.'/.htaccess', "<Directory>\n  Require all denied\n</Directory>\nDeny from all\n");
        @file_put_contents($root.'/index.html', '<!doctype html><title>403</title><h1>403</h1>');
    }

    private function storageTestConfig(array $storage): array
    {
        if (($storage['driver'] ?? 'local') === 's3') {
            return [
                'driver' => 's3',
                'endpoint' => $storage['endpoint'] ?? '',
                'region' => $storage['region'] ?? 'us-east-1',
                'bucket' => $storage['bucket'] ?? '',
                'access_key' => $storage['access_key'] ?? '',
                'secret_key' => $storage['secret_key'] ?? '',
                'path_style' => ! empty($storage['path_style']),
                'prefix' => $storage['prefix'] ?? '',
            ];
        }

        return ['driver' => 'local', 'path' => $storage['local_path'] ?? config('nextrespack.storage_root')];
    }
}
