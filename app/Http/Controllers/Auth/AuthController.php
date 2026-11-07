<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserQuota;
use App\Services\Security\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'login' => ['required', 'string', 'max:191'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $login = trim($credentials['login']);
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $user = User::withTrashed()->where($field, $login)->first();

        if ($user && $user->trashed()) {
            throw ValidationException::withMessages(['login' => __('This account has been disabled.')]);
        }

        if (! Auth::attempt([$field => $login, 'password' => $credentials['password']], (bool) ($credentials['remember'] ?? false))) {
            throw ValidationException::withMessages([
                'login' => __('The username/email or password is incorrect.'),
            ]);
        }

        if (! Auth::user()->is_active) {
            Auth::logout();
            throw ValidationException::withMessages(['login' => __('This account has been disabled.')]);
        }

        $request->session()->regenerate();
        app(AuditLogger::class)->log('auth.login', Auth::user(), __('User signed in.'));

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        app(AuditLogger::class)->log('auth.logout', $request->user(), __('User signed out.'));
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    public function showRegister(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        if (! nr_setting_bool('allow_registration', true)) {
            abort(403, __('Registration is currently disabled.'));
        }

        return view('auth.register');
    }

    public function register(Request $request, AuditLogger $audit): RedirectResponse
    {
        if (! nr_setting_bool('allow_registration', true)) {
            abort(403, __('Registration is currently disabled.'));
        }

        $data = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:64', 'regex:/^[A-Za-z0-9_\-\.]+$/', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:191', 'unique:users,email'],
            'password' => ['required', 'string', 'min:10', 'max:191', 'confirmed'],
            'terms' => ['accepted'],
        ]);

        $quotaMb = max(1, nr_setting_int('registration_quota_mb', 1024));

        $user = User::create([
            'username' => $data['username'],
            'email' => strtolower($data['email']),
            'password' => Hash::make($data['password']),
            'role' => UserRole::User,
            'is_active' => true,
            'storage_quota' => $quotaMb * 1024 * 1024,
            'locale' => app()->getLocale(),
        ]);

        UserQuota::firstOrCreate(['user_id' => $user->id], ['used_bytes' => 0, 'pack_count' => 0]);

        Auth::login($user);
        $request->session()->regenerate();
        $audit->log('auth.register', $user, __('Account registered.'));

        if (nr_setting_bool('email_verification', false)) {
            try {
                $user->sendEmailVerificationNotification();
            } catch (\Throwable $e) {
                report($e);
            }

            return redirect()->route('verification.notice');
        }

        return redirect()->route('dashboard')->with('status', __('Welcome! Your account has been created.'));
    }

    public function verificationNotice(Request $request): View|RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail() || ! nr_setting_bool('email_verification', false)) {
            return redirect()->route('dashboard');
        }

        return view('auth.verify-email');
    }

    public function verifyEmail(Request $request, string $id, string $hash): RedirectResponse
    {
        $user = User::findOrFail($id);

        if ($request->user()->id !== $user->id || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            abort(403, __('This verification link is invalid.'));
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            app(AuditLogger::class)->log('auth.email_verified', $user, __('Email address verified.'));
        }

        return redirect()->route('dashboard')->with('status', __('Your email address has been verified.'));
    }

    public function resendVerification(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', __('A new verification link has been sent to your email address.'));
    }

    public function showForgotPassword(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email', 'max:191']]);

        Password::sendResetLink($request->only('email'));

        return back()->with('status', __('If the email address exists in our system, a password reset link has been sent.'));
    }

    public function showResetForm(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email', 'max:191'],
            'password' => ['required', 'string', 'min:10', 'max:191', 'confirmed'],
        ]);

        $status = Password::reset($data, function (User $user, string $password) use ($data) {
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();

            app(AuditLogger::class)->log('auth.password_reset', $user, __('Password reset completed.'));
        });

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', __('Your password has been reset. Please sign in.'));
        }

        throw ValidationException::withMessages(['email' => [__($status)]]);
    }
}
