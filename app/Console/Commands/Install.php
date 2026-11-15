<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Installation\DatabaseTester;
use App\Services\Installation\Installer;
use App\Services\Installation\InstallState;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

class Install extends Command
{
    protected $signature = 'nr:install
        {--admin-name= : Username of the first super administrator}
        {--admin-email= : E-mail address of the first super administrator}
        {--admin-password= : Password of the first super administrator (minimum 10 characters)}
        {--site-name= : Public site name}
        {--site-url= : Public site URL (http:// or https://)}
        {--force : Re-run the installation without deleting existing data}';

    protected $description = 'Install Next Respack using the database connection configured in .env';

    public function handle(Installer $installer, InstallState $state, DatabaseTester $database): int
    {
        $force = (bool) $this->option('force');

        if (! $force && $state->installed()) {
            $this->error(__('Next Respack is already installed. Run `php artisan nr:install --force` to re-initialize without deleting existing data.'));

            return self::FAILURE;
        }

        try {
            $db = $this->databaseConfig();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $connection = $database->test($db);
        if (! $connection['ok']) {
            $this->error(__('Database connection failed: :errors', ['errors' => implode(' ', $connection['errors'])]));

            return self::FAILURE;
        }

        $siteName = trim((string) ($this->option('site-name') ?: config('app.name', 'Next Respack')));
        $siteUrl = rtrim(trim((string) ($this->option('site-url') ?: config('app.url', 'http://localhost'))), '/');
        $adminName = trim((string) ($this->option('admin-name') ?: 'admin'));
        $adminEmail = trim((string) $this->option('admin-email'));
        $adminPassword = (string) $this->option('admin-password');

        if ($adminEmail === '' && $this->input->isInteractive()) {
            $adminEmail = trim((string) $this->ask(__('Administrator e-mail address')));
        }

        if ($adminPassword === '' && $this->input->isInteractive()) {
            $adminPassword = (string) $this->secret(__('Administrator password (input is hidden)'));
        }

        if ($adminEmail === '') {
            $this->error(__('The administrator e-mail address is required. Pass --admin-email or run the command interactively.'));

            return self::FAILURE;
        }

        if ($adminPassword === '') {
            $this->error(__('The administrator password is required. Pass --admin-password or run the command interactively.'));

            return self::FAILURE;
        }

        if ($siteName === '') {
            $this->error(__('Site name is required.'));

            return self::FAILURE;
        }

        if (! filter_var($siteUrl, FILTER_VALIDATE_URL) || ! preg_match('#^https?://#i', $siteUrl)) {
            $this->error(__('Site URL must be a valid http:// or https:// URL.'));

            return self::FAILURE;
        }

        if (! preg_match('/^[A-Za-z0-9_\-\.]{3,64}$/', $adminName)) {
            $this->error(__('Administrator username must be 3-64 characters using letters, numbers, underscores, dots or dashes.'));

            return self::FAILURE;
        }

        if (! filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            $this->error(__('Administrator email is invalid.'));

            return self::FAILURE;
        }

        if (strlen($adminPassword) < 10) {
            $this->error(__('Administrator password must be at least 10 characters.'));

            return self::FAILURE;
        }

        $conflict = $this->administratorConflict($adminEmail, $adminName, $force);
        if ($conflict !== null) {
            $this->error($conflict);

            return self::FAILURE;
        }

        $payload = [
            'database' => $db,
            'storage' => [
                'driver' => 'local',
                'local_path' => (string) config('nextrespack.storage_root'),
            ],
            'site' => [
                'name' => $siteName,
                'description' => '',
                'url' => $siteUrl,
                'locale' => (string) config('app.locale', 'zh_CN'),
                'timezone' => (string) config('app.timezone', 'Asia/Shanghai'),
                'allow_registration' => true,
                'email_verification' => false,
                'max_upload_mb' => (int) config('nextrespack.max_upload_mb', 512),
                'default_quota_mb' => (int) config('nextrespack.default_quota_mb', 2048),
                'guest_uploads_enabled' => true,
            ],
            'admin' => [
                'username' => $adminName,
                'email' => $adminEmail,
                'password' => $adminPassword,
            ],
        ];

        $this->info(__('Installing Next Respack…'));

        try {
            $result = $installer->install($payload, $force);
        } catch (Throwable $e) {
            $this->error(__('Installation failed: :message', ['message' => $e->getMessage()]));

            return self::FAILURE;
        }

        $this->info(__('Next Respack was installed successfully.'));
        $this->line(__('Site URL: :url', ['url' => $result['url']]));
        $this->line(__('Super administrator: :email', ['email' => $result['admin_email']]));
        $this->line(__('The password is never displayed. Store it in your password manager.'));
        $this->line(__('Run `php artisan nr:update` after deploying future releases.'));

        return self::SUCCESS;
    }

    /**
     * Build the installer database payload from the configured connection.
     *
     * @return array<string, mixed>
     */
    private function databaseConfig(): array
    {
        $connection = (string) config('database.default');
        $config = (array) config("database.connections.{$connection}", []);
        $driver = (string) ($config['driver'] ?? $connection);

        if (! in_array($driver, ['mysql', 'mariadb', 'sqlite'], true)) {
            throw new RuntimeException(__('The configured database driver is not supported. Set DB_CONNECTION to mysql, mariadb or sqlite in .env.'));
        }

        if ($driver === 'sqlite') {
            $path = trim((string) ($config['database'] ?? ''));

            if ($path === '' || $path === ':memory:') {
                throw new RuntimeException(__('SQLite requires a persistent database file. Set DB_DATABASE in .env to a file path.'));
            }

            if (! str_starts_with($path, DIRECTORY_SEPARATOR)) {
                $path = base_path($path);
            }

            return ['driver' => 'sqlite', 'database' => $path, 'sqlite_path' => $path];
        }

        $database = trim((string) ($config['database'] ?? ''));

        if ($database === '') {
            throw new RuntimeException(__('The database is not configured. Set DB_CONNECTION, DB_HOST, DB_DATABASE and DB_USERNAME in .env before installing.'));
        }

        return [
            'driver' => $driver,
            'host' => (string) ($config['host'] ?? '127.0.0.1'),
            'port' => (int) ($config['port'] ?? 3306),
            'database' => $database,
            'username' => (string) ($config['username'] ?? ''),
            'password' => (string) ($config['password'] ?? ''),
            'charset' => (string) ($config['charset'] ?? 'utf8mb4'),
            'collation' => (string) ($config['collation'] ?? 'utf8mb4_unicode_ci'),
        ];
    }

    private function administratorConflict(string $email, string $username, bool $force): ?string
    {
        try {
            $byEmail = User::withTrashed()->where('email', $email)->first();
            $byName = User::withTrashed()->where('username', $username)->first();

            if ($force) {
                if ($byEmail !== null && $byName !== null && ! $byEmail->is($byName)) {
                    return __('The username :username belongs to another account. Choose a different --admin-name.', ['username' => $username]);
                }

                return null;
            }

            if ($byEmail !== null) {
                return __('The e-mail address :email is already registered. Choose a different --admin-email.', ['email' => $email]);
            }

            if ($byName !== null) {
                return __('The username :username is already taken. Choose a different --admin-name.', ['username' => $username]);
            }
        } catch (Throwable) {
            // The database has not been migrated yet, so there is nothing to conflict with.
        }

        return null;
    }
}
