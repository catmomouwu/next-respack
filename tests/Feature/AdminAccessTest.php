<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/login');
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_regular_user_cannot_access_admin_pages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin')->assertForbidden();
        $this->actingAs($user)->get('/admin/users')->assertForbidden();
        $this->actingAs($user)->get('/admin/settings')->assertForbidden();
        $this->actingAs($user)->get('/admin/storage')->assertForbidden();
    }

    public function test_admin_can_access_admin_but_not_super_admin_pages(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin')->assertOk();
        $this->actingAs($admin)->get('/admin/users')->assertOk();
        $this->actingAs($admin)->get('/admin/settings')->assertOk();
        $this->actingAs($admin)->get('/admin/packs')->assertOk();
        $this->actingAs($admin)->get('/admin/logs')->assertOk();

        $this->actingAs($admin)->get('/admin/storage')->assertForbidden();
    }

    public function test_super_admin_can_access_storage_management(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)->get('/admin/storage')->assertOk();
    }

    public function test_admin_can_update_system_settings(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->patch('/admin/settings', [
            'site_name' => 'Updated Respack',
            'site_description' => 'Updated description',
            'site_url' => 'http://localhost',
            'default_locale' => 'en',
            'timezone' => 'Asia/Shanghai',
            'max_upload_mb' => 256,
            'default_quota_mb' => 2048,
            'registration_quota_mb' => 1024,
            'download_mode' => 'php',
            'x_accel_prefix' => '/_protected/respack',
            'mail_port' => 587,
            'mail_encryption' => 'tls',
        ])->assertRedirect();

        $this->assertDatabaseHas('system_settings', ['key' => 'site_name', 'value' => 'Updated Respack']);
        $this->assertDatabaseHas('system_settings', ['key' => 'default_locale', 'value' => 'en']);
    }
}
