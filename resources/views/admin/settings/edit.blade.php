@extends('layouts.admin')

@section('title', __('Settings'))
@section('heading', __('Settings'))
@section('content')
    <form method="POST" action="{{ route('admin.settings.update') }}">
        @csrf
        @method('PATCH')

        <div class="nr-card p-5">
            <p class="nr-section-title">{{ __('General') }}</p>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="nr-label" for="site_name">{{ __('Site name') }}</label>
                    <input class="nr-input" id="site_name" name="site_name" value="{{ old('site_name', $settings['site_name']) }}" required maxlength="120">
                </div>
                <div>
                    <label class="nr-label" for="site_url">{{ __('Public site URL') }}</label>
                    <input class="nr-input" id="site_url" name="site_url" type="url" value="{{ old('site_url', $settings['site_url']) }}" required>
                    <p class="nr-help">{{ __('Must use HTTPS outside localhost. This is also the base for download links.') }}</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="nr-label" for="site_description">{{ __('Site description') }}</label>
                    <textarea class="nr-input" id="site_description" name="site_description" rows="2" maxlength="500">{{ old('site_description', $settings['site_description']) }}</textarea>
                </div>
                <div>
                    <label class="nr-label" for="default_locale">{{ __('Default language') }}</label>
                    <select class="nr-input" id="default_locale" name="default_locale">
                        <option value="zh_CN" @selected(old('default_locale', $settings['default_locale']) === 'zh_CN')>简体中文</option>
                        <option value="en" @selected(old('default_locale', $settings['default_locale']) === 'en')>English</option>
                    </select>
                </div>
                <div>
                    <label class="nr-label" for="timezone">{{ __('Timezone') }}</label>
                    <input class="nr-input" id="timezone" name="timezone" value="{{ old('timezone', $settings['timezone']) }}" required>
                </div>
            </div>
        </div>

        <div class="nr-card mt-4 p-5">
            <p class="nr-section-title">{{ __('Registration & uploads') }}</p>
            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="nr-label" for="max_upload_mb">{{ __('Max upload (MB)') }}</label>
                    <input class="nr-input" id="max_upload_mb" name="max_upload_mb" type="number" min="1" max="1024" value="{{ old('max_upload_mb', $settings['max_upload_mb']) }}" required>
                </div>
                <div>
                    <label class="nr-label" for="default_quota_mb">{{ __('Default quota (MB)') }}</label>
                    <input class="nr-input" id="default_quota_mb" name="default_quota_mb" type="number" min="64" value="{{ old('default_quota_mb', $settings['default_quota_mb']) }}" required>
                </div>
                <div>
                    <label class="nr-label" for="registration_quota_mb">{{ __('New user quota (MB)') }}</label>
                    <input class="nr-input" id="registration_quota_mb" name="registration_quota_mb" type="number" min="64" value="{{ old('registration_quota_mb', $settings['registration_quota_mb']) }}" required>
                </div>
            </div>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <label class="flex items-start gap-2 text-sm text-slate-600 dark:text-slate-300">
                    <input type="checkbox" name="allow_registration" value="1" class="mt-0.5 rounded text-emerald-600" @checked(old('allow_registration', $settings['allow_registration']))>
                    <span>{{ __('Allow public registration') }}</span>
                </label>
                <label class="flex items-start gap-2 text-sm text-slate-600 dark:text-slate-300">
                    <input type="checkbox" name="email_verification" value="1" class="mt-0.5 rounded text-emerald-600" @checked(old('email_verification', $settings['email_verification']))>
                    <span>{{ __('Require email verification') }}</span>
                </label>
            </div>
        </div>

        <div class="nr-card mt-4 p-5">
            <p class="nr-section-title">{{ __('Downloads') }}</p>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="nr-label" for="download_mode">{{ __('Download mode') }}</label>
                    <select class="nr-input" id="download_mode" name="download_mode">
                        <option value="php" @selected(old('download_mode', $settings['download_mode']) === 'php')>{{ __('PHP streaming (works everywhere)') }}</option>
                        <option value="x-accel" @selected(old('download_mode', $settings['download_mode']) === 'x-accel')>{{ __('Nginx X-Accel-Redirect') }}</option>
                    </select>
                    <p class="nr-help">{{ __('X-Accel requires the Nginx internal location documented in the deployment guide.') }}</p>
                </div>
                <div>
                    <label class="nr-label" for="x_accel_prefix">{{ __('X-Accel prefix') }}</label>
                    <input class="nr-input" id="x_accel_prefix" name="x_accel_prefix" value="{{ old('x_accel_prefix', $settings['x_accel_prefix']) }}">
                </div>
            </div>
            <label class="mt-4 flex items-start gap-2 text-sm text-slate-600 dark:text-slate-300">
                <input type="checkbox" name="download_ip_logging" value="1" class="mt-0.5 rounded text-emerald-600" @checked(old('download_ip_logging', $settings['download_ip_logging']))>
                <span>{{ __('Store a salted hash of the downloader IP for abuse analysis (never the raw IP)') }}</span>
            </label>
        </div>

        <div class="nr-card mt-4 p-5">
            <p class="nr-section-title">{{ __('Minecraft data') }}</p>
            <label class="mt-4 flex items-start gap-2 text-sm text-slate-600 dark:text-slate-300">
                <input type="checkbox" name="pack_format_auto_sync" value="1" class="mt-0.5 rounded text-emerald-600" @checked(old('pack_format_auto_sync', $settings['pack_format_auto_sync']))>
                <span>{{ __('Allow a throttled background synchronization from the Minecraft Wiki (at most once per day, triggered by admin visits)') }}</span>
            </label>
            <p class="nr-help">{{ __('Manual synchronization always remains available. Cron is optional.') }}</p>
        </div>

        <div class="nr-card mt-4 p-5">
            <p class="nr-section-title">{{ __('Email (SMTP)') }}</p>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="nr-label" for="mail_host">{{ __('SMTP host') }}</label>
                    <input class="nr-input" id="mail_host" name="mail_host" value="{{ old('mail_host', $settings['mail_host']) }}" autocomplete="off">
                </div>
                <div>
                    <label class="nr-label" for="mail_port">{{ __('SMTP port') }}</label>
                    <input class="nr-input" id="mail_port" name="mail_port" type="number" value="{{ old('mail_port', $settings['mail_port']) }}">
                </div>
                <div>
                    <label class="nr-label" for="mail_username">{{ __('SMTP username') }}</label>
                    <input class="nr-input" id="mail_username" name="mail_username" value="{{ old('mail_username', $settings['mail_username']) }}" autocomplete="off">
                </div>
                <div>
                    <label class="nr-label" for="mail_password">{{ __('SMTP password') }}</label>
                    <input class="nr-input" id="mail_password" name="mail_password" type="password" value="" placeholder="{{ $settings['mail_password_set'] ? __('Leave blank to keep the saved password') : __('Not configured') }}" autocomplete="new-password">
                </div>
                <div>
                    <label class="nr-label" for="mail_encryption">{{ __('Encryption') }}</label>
                    <select class="nr-input" id="mail_encryption" name="mail_encryption">
                        @foreach (['tls' => 'TLS', 'ssl' => 'SSL', 'starttls' => 'STARTTLS', 'none' => __('None')] as $value => $label)
                            <option value="{{ $value }}" @selected(old('mail_encryption', $settings['mail_encryption'] ?: 'none') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="nr-label" for="mail_from_address">{{ __('From address') }}</label>
                    <input class="nr-input" id="mail_from_address" name="mail_from_address" type="email" value="{{ old('mail_from_address', $settings['mail_from_address']) }}">
                </div>
                <div>
                    <label class="nr-label" for="mail_from_name">{{ __('From name') }}</label>
                    <input class="nr-input" id="mail_from_name" name="mail_from_name" value="{{ old('mail_from_name', $settings['mail_from_name']) }}">
                </div>
            </div>
        </div>

        <div class="nr-card mt-4 p-5">
            <p class="nr-section-title">{{ __('Maintenance') }}</p>
            <div class="mt-4">
                <label class="nr-label" for="maintenance_message">{{ __('Maintenance message') }}</label>
                <textarea class="nr-input" id="maintenance_message" name="maintenance_message" rows="2" maxlength="1000">{{ old('maintenance_message', $settings['maintenance_message']) }}</textarea>
                <p class="nr-help">{{ __('Informational only. Put the application in maintenance mode from the deployment environment when needed.') }}</p>
            </div>
        </div>

        <div class="mt-4 flex justify-end">
            <button class="nr-btn-primary !px-6" type="submit">{{ __('Save settings') }}</button>
        </div>
    </form>
@endsection
