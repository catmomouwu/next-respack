<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\Security\AuditLogger;
use App\Services\Storage\StorageManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(): View
    {
        $settings = [
            'site_name' => nr_setting('site_name', config('app.name')),
            'site_description' => nr_setting('site_description', ''),
            'site_url' => nr_setting('site_url', config('app.url')),
            'default_locale' => nr_setting('default_locale', 'zh_CN'),
            'timezone' => nr_setting('timezone', 'Asia/Shanghai'),
            'allow_registration' => nr_setting_bool('allow_registration', true),
            'email_verification' => nr_setting_bool('email_verification', false),
            'max_upload_mb' => nr_setting_int('max_upload_mb', (int) config('nextrespack.max_upload_mb', 512)),
            'default_quota_mb' => nr_setting_int('default_quota_mb', (int) config('nextrespack.default_quota_mb', 2048)),
            'registration_quota_mb' => nr_setting_int('registration_quota_mb', 1024),
            'download_mode' => nr_setting('download_mode', 'php'),
            'x_accel_prefix' => nr_setting('x_accel_prefix', '/_protected/respack'),
            'download_ip_logging' => nr_setting_bool('download_ip_logging', true),
            'pack_format_auto_sync' => nr_setting_bool('pack_format_auto_sync', true),
            'mail_host' => nr_setting('mail_host', ''),
            'mail_port' => nr_setting_int('mail_port', 587),
            'mail_username' => nr_setting('mail_username', ''),
            'mail_password_set' => nr_setting('mail_password', '') !== '',
            'mail_encryption' => nr_setting('mail_encryption', 'tls'),
            'mail_from_address' => nr_setting('mail_from_address', ''),
            'mail_from_name' => nr_setting('mail_from_name', ''),
            'maintenance_message' => nr_setting('maintenance_message', ''),
        ];

        return view('admin.settings.edit', [
            'settings' => $settings,
        ]);
    }

    public function update(Request $request, AuditLogger $audit, StorageManager $storage): RedirectResponse
    {
        $data = $request->validate([
            'site_name' => ['required', 'string', 'max:120'],
            'site_description' => ['nullable', 'string', 'max:500'],
            'site_url' => ['required', 'url', 'max:191', 'regex:#^https?://#i'],
            'default_locale' => ['required', 'in:zh_CN,en'],
            'timezone' => ['required', 'string', 'timezone'],
            'max_upload_mb' => ['required', 'integer', 'min:1', 'max:1024'],
            'default_quota_mb' => ['required', 'integer', 'min:64', 'max:1048576'],
            'registration_quota_mb' => ['required', 'integer', 'min:64', 'max:1048576'],
            'download_mode' => ['required', 'in:php,x-accel'],
            'x_accel_prefix' => ['nullable', 'string', 'max:191', 'regex:#^/#'],
            'mail_host' => ['nullable', 'string', 'max:191'],
            'mail_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mail_username' => ['nullable', 'string', 'max:191'],
            'mail_password' => ['nullable', 'string', 'max:191'],
            'mail_encryption' => ['nullable', 'in:tls,ssl,starttls,none'],
            'mail_from_address' => ['nullable', 'email', 'max:191'],
            'mail_from_name' => ['nullable', 'string', 'max:120'],
            'maintenance_message' => ['nullable', 'string', 'max:1000'],
        ]);

        $url = rtrim($data['site_url'], '/');
        $host = parse_url($url, PHP_URL_HOST) ?: '';
        if (! str_starts_with($url, 'https://') && ! in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return back()->withInput()->withErrors(['site_url' => __('A public site must use HTTPS.')]);
        }

        $map = [
            'site_name' => ['general', 'string'],
            'site_description' => ['general', 'string'],
            'site_url' => ['general', 'string'],
            'default_locale' => ['general', 'string'],
            'timezone' => ['general', 'string'],
            'max_upload_mb' => ['uploads', 'int'],
            'default_quota_mb' => ['uploads', 'int'],
            'registration_quota_mb' => ['uploads', 'int'],
            'download_mode' => ['downloads', 'string'],
            'x_accel_prefix' => ['downloads', 'string'],
            'mail_host' => ['mail', 'string'],
            'mail_username' => ['mail', 'string'],
            'mail_from_address' => ['mail', 'string'],
            'mail_from_name' => ['mail', 'string'],
            'maintenance_message' => ['general', 'string'],
        ];

        foreach ($map as $key => [$group, $type]) {
            SystemSetting::set($key, $data[$key] ?? '', $group, $type);
        }

        SystemSetting::set('site_url', $url, 'general');
        SystemSetting::set('allow_registration', $request->boolean('allow_registration'), 'registration', 'bool');
        SystemSetting::set('email_verification', $request->boolean('email_verification'), 'registration', 'bool');
        SystemSetting::set('download_ip_logging', $request->boolean('download_ip_logging'), 'downloads', 'bool');
        SystemSetting::set('pack_format_auto_sync', $request->boolean('pack_format_auto_sync'), 'minecraft', 'bool');
        SystemSetting::set('mail_port', (int) ($data['mail_port'] ?? 587), 'mail', 'int');
        SystemSetting::set('mail_encryption', ($data['mail_encryption'] ?? 'none') === 'none' ? '' : ($data['mail_encryption'] ?? ''), 'mail');

        if (! empty($data['mail_password'])) {
            SystemSetting::set('mail_password', $storage->encryptSecret($data['mail_password']), 'mail');
        }

        $audit->log('admin.settings_updated', null, __('System settings updated.'), array_keys($data));

        return back()->with('status', __('Settings saved.'));
    }
}
