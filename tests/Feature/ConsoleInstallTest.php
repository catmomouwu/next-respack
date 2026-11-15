<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PDO;
use Tests\TestCase;

/**
 * Exercises the CLI installer and updater against an isolated SQLite database.
 * No file in the real project environment is touched.
 */
class ConsoleInstallTest extends TestCase
{
    private string $workDir;

    private string $sqlitePath;

    private string $storagePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workDir = $this->temporaryDirectory();
        $this->sqlitePath = $this->workDir.'/database.sqlite';
        $this->storagePath = $this->workDir.'/storage';

        config([
            'nextrespack.force_installed' => false,
            'nextrespack.env_file' => $this->workDir.'/.env',
            'nextrespack.install_lock' => $this->workDir.'/installed.lock',
            'nextrespack.install_state' => $this->workDir.'/install-state.json',
            'nextrespack.installer_key' => $this->workDir.'/.installer-key',
            'nextrespack.storage_root' => $this->storagePath,
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->sqlitePath,
        ]);

        DB::purge('sqlite');
    }

    public function test_install_creates_the_administrator_installation_record_and_lock(): void
    {
        $output = $this->runCommand('nr:install');

        $this->assertStringNotContainsString('InstallerPassword123', $output, 'The administrator password must never be printed.');
        $this->assertFileExists($this->workDir.'/installed.lock');
        $this->assertFileExists($this->workDir.'/.env');
        $this->assertDirectoryExists($this->storagePath);

        $env = (string) file_get_contents($this->workDir.'/.env');
        $this->assertStringContainsString('APP_INSTALLED=true', $env);
        $this->assertStringContainsString('DB_CONNECTION=sqlite', $env);

        $pdo = $this->pdo();

        $admin = $pdo->query("SELECT id, username, email, password, role, is_active FROM users WHERE email = 'owner@example.com'")->fetch();

        $this->assertNotFalse($admin);
        $this->assertSame('owner', $admin['username']);
        $this->assertSame('super_admin', $admin['role']);
        $this->assertSame(1, (int) $admin['is_active']);
        $this->assertTrue(Hash::check('InstallerPassword123', $admin['password']));

        $this->assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM installations')->fetchColumn());
        $this->assertSame(
            1,
            (int) $pdo->query('SELECT COUNT(*) FROM user_quotas WHERE user_id = '.(int) $admin['id'])->fetchColumn()
        );
        $this->assertSame(
            'CLI Respack',
            $pdo->query("SELECT value FROM system_settings WHERE key = 'site_name'")->fetchColumn()
        );
        $this->assertGreaterThan(0, (int) $pdo->query('SELECT COUNT(*) FROM minecraft_pack_formats')->fetchColumn());
    }

    public function test_duplicate_install_is_refused_without_changing_data(): void
    {
        $this->runCommand('nr:install');

        $output = $this->runCommand('nr:install', ['--site-name' => 'Changed by duplicate install'], 1);

        $this->assertStringContainsString('nr:install --force', $output);

        $pdo = $this->pdo();

        $this->assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn());
        $this->assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM installations')->fetchColumn());
        $this->assertSame(
            'CLI Respack',
            $pdo->query("SELECT value FROM system_settings WHERE key = 'site_name'")->fetchColumn()
        );
    }

    public function test_force_install_is_idempotent(): void
    {
        $this->runCommand('nr:install');

        $this->runCommand('nr:install', [
            '--force' => true,
            '--site-name' => 'CLI Respack Updated',
            '--admin-password' => 'AnotherSecurePassword123',
        ]);

        $pdo = $this->pdo();

        $this->assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn());
        $this->assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM installations')->fetchColumn());
        $this->assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM user_quotas')->fetchColumn());
        $this->assertSame(
            'CLI Respack Updated',
            $pdo->query("SELECT value FROM system_settings WHERE key = 'site_name'")->fetchColumn()
        );

        $password = $pdo->query("SELECT password FROM users WHERE email = 'owner@example.com'")->fetchColumn();
        $this->assertTrue(Hash::check('AnotherSecurePassword123', $password));
    }

    public function test_update_requires_an_installed_instance(): void
    {
        $output = $this->runCommand('nr:update', [], 1);

        $this->assertStringContainsString('nr:install', $output);
    }

    public function test_update_runs_migrations_and_preserves_data(): void
    {
        $this->runCommand('nr:install');

        $output = $this->runCommand('nr:update');

        $this->assertStringContainsString('migrate', strtolower($output));

        $pdo = $this->pdo();

        $this->assertGreaterThan(0, (int) $pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn());
        $this->assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn());
        $this->assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM installations')->fetchColumn());
        $this->assertSame(
            'CLI Respack',
            $pdo->query("SELECT value FROM system_settings WHERE key = 'site_name'")->fetchColumn()
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function runCommand(string $command, array $overrides = [], int $expectedExitCode = 0): string
    {
        $parameters = $command === 'nr:install'
            ? array_merge($this->installOptions(), $overrides)
            : $overrides;

        $exitCode = Artisan::call($command, $parameters);
        $output = Artisan::output();

        $this->assertSame($expectedExitCode, $exitCode, $output);

        return $output;
    }

    /**
     * @return array<string, mixed>
     */
    private function installOptions(): array
    {
        return [
            '--admin-name' => 'owner',
            '--admin-email' => 'owner@example.com',
            '--admin-password' => 'InstallerPassword123',
            '--site-name' => 'CLI Respack',
            '--site-url' => 'http://localhost',
        ];
    }

    private function pdo(): PDO
    {
        return new PDO('sqlite:'.$this->sqlitePath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
}
