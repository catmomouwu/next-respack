<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Installation;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Exercises the complete browser installer against an isolated SQLite database.
 * No file in the real project environment is touched.
 */
class InstallerTest extends TestCase
{
    private string $workDir;

    private string $sqlitePath;

    private string $storagePath;

    private string $envFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workDir = $this->temporaryDirectory();
        $this->sqlitePath = $this->workDir.'/database.sqlite';
        $this->storagePath = $this->workDir.'/storage';
        $this->envFile = $this->workDir.'/.env';

        config([
            'nextrespack.force_installed' => false,
            'nextrespack.env_file' => $this->envFile,
            'nextrespack.install_lock' => $this->workDir.'/installed.lock',
            'nextrespack.install_state' => $this->workDir.'/install-state.json',
            'nextrespack.installer_key' => $this->workDir.'/.installer-key',
            'nextrespack.storage_root' => $this->storagePath,
        ]);
    }

    public function test_wizard_runs_to_completion_and_locks_the_installer(): void
    {
        $this->get('/install')->assertOk()->assertSee('Next Respack');

        $this->get('/install/requirements')->assertOk();

        $this->post('/install/requirements')->assertRedirect('/install/database');

        $this->post('/install/database', [
            'driver' => 'sqlite',
            'sqlite_path' => $this->sqlitePath,
        ])->assertRedirect('/install/storage');

        $this->post('/install/storage', [
            'driver' => 'local',
            'local_path' => $this->storagePath,
        ])->assertRedirect('/install/site');

        $this->post('/install/site', [
            'name' => 'Test Respack',
            'description' => 'Installer test site',
            'url' => 'http://localhost',
            'locale' => 'zh_CN',
            'timezone' => 'Asia/Shanghai',
            'max_upload_mb' => 128,
            'default_quota_mb' => 1024,
            'registration_quota_mb' => 512,
            'allow_registration' => '1',
            'email_verification' => '0',
        ])->assertRedirect('/install/admin');

        $this->post('/install/admin', [
            'username' => 'owner',
            'email' => 'owner@example.com',
            'password' => 'InstallerPassword123',
            'password_confirmation' => 'InstallerPassword123',
        ])->assertRedirect('/install/confirm');

        $this->get('/install/confirm')
            ->assertOk()
            ->assertSee('owner@example.com')
            ->assertSee('Test Respack');

        $response = $this->post('/install/run');
        $response->assertOk();
        $response->assertSee('Test Respack');

        // The lock file and the environment file were created.
        $this->assertFileExists($this->workDir.'/installed.lock');
        $this->assertFileExists($this->envFile);

        $env = (string) file_get_contents($this->envFile);
        $this->assertStringContainsString('APP_INSTALLED=true', $env);
        $this->assertStringContainsString('DB_CONNECTION=sqlite', $env);
        $this->assertStringContainsString('APP_URL=http://localhost', $env);

        // Verify the isolated database directly.
        $pdo = new \PDO('sqlite:'.$this->sqlitePath, null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);

        $admin = $pdo->query("SELECT username, email, role, is_active FROM users WHERE email = 'owner@example.com'")->fetch(\PDO::FETCH_ASSOC);
        $this->assertNotFalse($admin);
        $this->assertSame('owner', $admin['username']);
        $this->assertSame('super_admin', $admin['role']);
        $this->assertSame(1, (int) $admin['is_active']);

        $installations = (int) $pdo->query('SELECT COUNT(*) FROM installations')->fetchColumn();
        $this->assertSame(1, $installations);

        $siteName = $pdo->query("SELECT value FROM system_settings WHERE key = 'site_name'")->fetchColumn();
        $this->assertSame('Test Respack', $siteName);

        $formats = (int) $pdo->query('SELECT COUNT(*) FROM minecraft_pack_formats')->fetchColumn();
        $this->assertGreaterThan(0, $formats, 'The bundled pack format dataset should be imported.');

        // The installer endpoint is no longer reachable.
        $this->get('/install')->assertNotFound();
    }

    public function test_database_step_rejects_an_unwritable_sqlite_directory(): void
    {
        $this->get('/install/requirements')->assertOk();
        $this->post('/install/requirements')->assertRedirect('/install/database');

        $response = $this->post('/install/database', [
            'driver' => 'sqlite',
            'sqlite_path' => '/proc/next-respack/blocked.sqlite',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('database');
    }

    public function test_site_step_requires_https_for_public_hosts(): void
    {
        $this->get('/install/requirements')->assertOk();
        $this->post('/install/requirements');
        $this->post('/install/database', ['driver' => 'sqlite', 'sqlite_path' => $this->sqlitePath]);
        $this->post('/install/storage', ['driver' => 'local', 'local_path' => $this->storagePath]);

        $payload = [
            'name' => 'Test Respack',
            'url' => 'http://respack.example.com',
            'locale' => 'zh_CN',
            'timezone' => 'Asia/Shanghai',
            'max_upload_mb' => 128,
            'default_quota_mb' => 1024,
            'registration_quota_mb' => 512,
        ];

        $this->post('/install/site', $payload)->assertSessionHasErrors('url');

        $this->post('/install/site', array_merge($payload, ['url' => 'https://respack.example.com']))
            ->assertRedirect('/install/admin');
    }
}
