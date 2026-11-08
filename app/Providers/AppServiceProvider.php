<?php

namespace App\Providers;

use App\Services\Installation\InstallState;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->ensureApplicationKey();
    }

    public function boot(): void
    {
        $this->loadSettings();
        $this->registerRateLimiters();
    }

    /**
     * Before installation there is no APP_KEY in .env. Laravel still needs a key
     * for encrypted cookies so the installer wizard can keep session state.
     */
    private function ensureApplicationKey(): void
    {
        if (config('app.key') !== null && config('app.key') !== '') {
            return;
        }

        $path = (string) config('nextrespack.installer_key');
        $key = null;

        if (is_file($path)) {
            $content = trim((string) @file_get_contents($path));
            if ($content !== '') {
                $key = $content;
            }
        }

        if ($key === null) {
            $key = 'base64:'.base64_encode(random_bytes(32));
            $dir = dirname($path);
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            if (@file_put_contents($path, $key, LOCK_EX) !== false) {
                @chmod($path, 0600);
            }
        }

        config(['app.key' => $key]);
    }

    private function loadSettings(): void
    {
        try {
            if (! app(InstallState::class)->installed()) {
                return;
            }

            $settings = \App\Models\SystemSetting::allCached();

            if (! empty($settings['site_name'])) {
                config(['app.name' => $settings['site_name']]);
            }

            if (! empty($settings['site_url'])) {
                config(['app.url' => rtrim($settings['site_url'], '/')]);
                if (str_starts_with($settings['site_url'], 'https://')) {
                    URL::forceScheme('https');
                }
            }

            if (! empty($settings['default_locale'])) {
                config(['app.locale' => $settings['default_locale']]);
            }

            if (! empty($settings['timezone'])) {
                config(['app.timezone' => $settings['timezone']]);
                date_default_timezone_set($settings['timezone']);
            }

            if (! empty($settings['mail_host'])) {
                $mailPassword = null;
                if (! empty($settings['mail_password'])) {
                    try {
                        $mailPassword = \Illuminate\Support\Facades\Crypt::decryptString($settings['mail_password']);
                    } catch (\Throwable) {
                        $mailPassword = null;
                    }
                }

                config([
                    'mail.default' => 'smtp',
                    'mail.mailers.smtp.host' => $settings['mail_host'],
                    'mail.mailers.smtp.port' => (int) ($settings['mail_port'] ?? 587),
                    'mail.mailers.smtp.username' => $settings['mail_username'] ?? null,
                    'mail.mailers.smtp.password' => $mailPassword,
                    'mail.mailers.smtp.encryption' => $settings['mail_encryption'] ?: null,
                    'mail.from.address' => $settings['mail_from_address'] ?? config('mail.from.address'),
                    'mail.from.name' => $settings['mail_from_name'] ?? config('app.name'),
                ]);
            }
        } catch (\Throwable) {
            // The installer may be running and the settings table may not exist yet.
        }
    }

    private function registerRateLimiters(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $identifier = strtolower((string) $request->input('login')).'|'.$request->ip();

            return Limit::perMinute(5)->by($identifier)->response(function () {
                return back()->withErrors(['login' => __('Too many login attempts. Please wait a minute and try again.')]);
            });
        });

        RateLimiter::for('register', fn (Request $request) => Limit::perHour(3)->by($request->ip()));
        RateLimiter::for('forgot-password', fn (Request $request) => Limit::perHour(3)->by($request->ip()));
        RateLimiter::for('uploads', fn (Request $request) => Limit::perHour(20)->by(optional($request->user())->id ?: $request->ip()));
        RateLimiter::for('guest-uploads', fn (Request $request) => [
            Limit::perHour(5)->by($request->ip()),
            Limit::perDay(20)->by($request->ip()),
        ]);
        RateLimiter::for('captcha', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
        RateLimiter::for('install', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
    }
}
