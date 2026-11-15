<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Installation\InstallState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('allow_registration', true, 'registration', 'bool');
        SystemSetting::set('email_verification', false, 'registration', 'bool');
    }

    public function test_guest_can_register_and_is_logged_in(): void
    {
        $response = $this->post('/register', [
            'username' => 'new_player',
            'email' => 'player@example.com',
            'password' => 'StrongPassword123',
            'password_confirmation' => 'StrongPassword123',
            'terms' => '1',
        ]);

        $response->assertRedirect('/dashboard');

        $user = User::where('email', 'player@example.com')->firstOrFail();
        $this->assertSame('new_player', $user->username);
        $this->assertSame(UserRole::User, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('user_quotas', ['user_id' => $user->id]);
    }

    public function test_registration_can_be_disabled(): void
    {
        SystemSetting::set('allow_registration', false, 'registration', 'bool');

        $this->get('/register')->assertStatus(403);

        $this->post('/register', [
            'username' => 'blocked_user',
            'email' => 'blocked@example.com',
            'password' => 'StrongPassword123',
            'password_confirmation' => 'StrongPassword123',
            'terms' => '1',
        ])->assertStatus(403);
    }

    public function test_user_can_login_with_username_or_email(): void
    {
        $user = User::factory()->create([
            'username' => 'steve',
            'email' => 'steve@example.com',
            'password' => 'StrongPassword123',
        ]);

        $this->post('/login', ['login' => 'steve', 'password' => 'StrongPassword123'])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();

        $this->post('/login', ['login' => 'steve@example.com', 'password' => 'StrongPassword123'])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        User::factory()->create(['email' => 'steve@example.com', 'password' => 'StrongPassword123']);

        $this->from('/login')->post('/login', [
            'login' => 'steve@example.com',
            'password' => 'wrong-password',
        ])->assertRedirect('/login')->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_inactive_account_cannot_login(): void
    {
        $user = User::factory()->create([
            'email' => 'disabled@example.com',
            'password' => 'StrongPassword123',
            'is_active' => false,
        ]);

        $this->post('/login', ['login' => 'disabled@example.com', 'password' => 'StrongPassword123'])
            ->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_email_verification_flow(): void
    {
        SystemSetting::set('email_verification', true, 'registration', 'bool');

        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get('/dashboard')->assertRedirect('/email/verify');
        $this->actingAs($user)->get('/email/verify')->assertOk();

        $url = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $this->actingAs($user)->get($url)->assertRedirect('/dashboard');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_account_profile_and_password_can_be_updated(): void
    {
        $user = User::factory()->create(['password' => 'OldPassword123']);

        $this->actingAs($user)->patch('/account/profile', [
            'username' => 'renamed',
            'email' => 'renamed@example.com',
            'locale' => 'en',
        ])->assertRedirect();

        $this->assertSame('renamed', $user->fresh()->username);

        $this->actingAs($user)->patch('/account/password', [
            'current_password' => 'OldPassword123',
            'password' => 'NewPassword1234',
            'password_confirmation' => 'NewPassword1234',
        ])->assertRedirect();

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('NewPassword1234', $user->fresh()->password));
    }

    public function test_locale_can_be_switched_and_persisted_for_the_user(): void
    {
        $user = User::factory()->create(['locale' => 'zh_CN']);

        $this->actingAs($user)->get('/locale/en')->assertRedirect();
        $this->assertSame('en', $user->fresh()->locale);
        $this->assertSame('en', session('locale'));

        $this->get('/')->assertOk()->assertSee('<html lang="en"', false);
    }

    public function test_installer_redirects_guests_when_not_installed(): void
    {
        config(['nextrespack.force_installed' => false, 'nextrespack.install_lock' => $this->temporaryDirectory().'/missing.lock']);

        app(InstallState::class)->removeLock();

        $this->get('/dashboard')->assertRedirect('/install');
    }
}
