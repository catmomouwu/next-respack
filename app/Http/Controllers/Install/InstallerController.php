<?php

namespace App\Http\Controllers\Install;

use App\Http\Controllers\Controller;
use App\Http\Middleware\SetLocale;
use App\Services\Installation\DatabaseTester;
use App\Services\Installation\EnvWriter;
use App\Services\Installation\InstallState;
use App\Services\Installation\Installer;
use App\Services\Installation\RequirementsChecker;
use App\Services\Storage\StorageManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Throwable;

class InstallerController extends Controller
{
    private const SESSION_KEY = 'installer';

    public function welcome(InstallState $state): View
    {
        return view('install.welcome', ['state' => $state->state()]);
    }

    public function requirements(Request $request, RequirementsChecker $checker): View|RedirectResponse
    {
        $items = $checker->check();
        $passed = $checker->passed($items);

        $request->session()->put(self::SESSION_KEY.'.requirements_passed', $passed);
        $request->session()->put(self::SESSION_KEY.'.requirements', $items);

        return view('install.requirements', [
            'items' => $items,
            'passed' => $passed,
            'summary' => $checker->summary($items),
        ]);
    }

    public function saveRequirements(Request $request, RequirementsChecker $checker): RedirectResponse
    {
        $items = $checker->check();

        if (! $checker->passed($items)) {
            return redirect()->route('install.requirements')->withErrors([
                'requirements' => __('One or more required environment checks failed. Fix them and try again.'),
            ]);
        }

        $request->session()->put(self::SESSION_KEY.'.requirements_passed', true);

        return redirect()->route('install.database');
    }

    public function database(Request $request): View|RedirectResponse
    {
        if (! $request->session()->get(self::SESSION_KEY.'.requirements_passed')) {
            return redirect()->route('install.requirements');
        }

        $data = $request->session()->get(self::SESSION_KEY.'.database', [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => '',
            'username' => '',
            'password' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'sqlite_path' => database_path('database.sqlite'),
        ]);

        return view('install.database', ['data' => $data]);
    }

    public function testDatabase(Request $request, DatabaseTester $tester): JsonResponse
    {
        if (! $request->session()->get(self::SESSION_KEY.'.requirements_passed')) {
            return response()->json(['ok' => false, 'errors' => [__('Complete the environment check first.')]], 422);
        }

        $validator = Validator::make($request->all(), $this->databaseRules($request));

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'errors' => $validator->errors()->all()], 422);
        }

        $result = $tester->test($this->databasePayload($request));

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function saveDatabase(Request $request, DatabaseTester $tester): RedirectResponse
    {
        if (! $request->session()->get(self::SESSION_KEY.'.requirements_passed')) {
            return redirect()->route('install.requirements');
        }

        $request->validate($this->databaseRules($request));
        $payload = $this->databasePayload($request);
        $result = $tester->test($payload);

        if (! $result['ok']) {
            return back()->withInput()->withErrors([
                'database' => implode(' ', $result['errors']),
            ]);
        }

        $request->session()->put(self::SESSION_KEY.'.database', $payload);

        return redirect()->route('install.storage');
    }

    public function storage(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has(self::SESSION_KEY.'.database')) {
            return redirect()->route('install.database');
        }

        $data = $request->session()->get(self::SESSION_KEY.'.storage', [
            'driver' => 'local',
            'local_path' => config('nextrespack.storage_root'),
            'endpoint' => '',
            'region' => 'us-east-1',
            'bucket' => '',
            'access_key' => '',
            'secret_key' => '',
            'path_style' => true,
            'prefix' => '',
            'webdav_url' => '',
            'webdav_username' => '',
            'webdav_password' => '',
            'webdav_prefix' => '',
            'webdav_verify_ssl' => true,
        ]);

        return view('install.storage', ['data' => $data]);
    }

    public function testStorage(Request $request, StorageManager $storage): JsonResponse
    {
        if (! $request->session()->has(self::SESSION_KEY.'.database')) {
            return response()->json(['ok' => false, 'errors' => [__('Complete the database step first.')]], 422);
        }

        $validator = Validator::make($request->all(), $this->storageRules($request));

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'errors' => $validator->errors()->all()], 422);
        }

        $result = $storage->test((string) $request->input('driver', 'local'), $this->storagePayload($request));

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function saveStorage(Request $request, StorageManager $storage): RedirectResponse
    {
        if (! $request->session()->has(self::SESSION_KEY.'.database')) {
            return redirect()->route('install.database');
        }

        $request->validate($this->storageRules($request));
        $payload = $this->storagePayload($request);
        $result = $storage->test($payload['driver'], $payload);

        if (! $result['ok']) {
            return back()->withInput()->withErrors(['storage' => implode(' ', $result['errors'])]);
        }

        $request->session()->put(self::SESSION_KEY.'.storage', $payload);

        return redirect()->route('install.site');
    }

    public function site(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has(self::SESSION_KEY.'.storage')) {
            return redirect()->route('install.storage');
        }

        $data = $request->session()->get(self::SESSION_KEY.'.site', [
            'name' => 'Next Respack',
            'description' => '',
            'url' => $request->getSchemeAndHttpHost(),
            'locale' => 'zh_CN',
            'timezone' => 'Asia/Shanghai',
            'allow_registration' => true,
            'email_verification' => false,
            'max_upload_mb' => (int) config('nextrespack.max_upload_mb', 512),
            'default_quota_mb' => (int) config('nextrespack.default_quota_mb', 2048),
            'registration_quota_mb' => 1024,
        ]);

        return view('install.site', ['data' => $data, 'locales' => SetLocale::SUPPORTED]);
    }

    public function saveSite(Request $request): RedirectResponse
    {
        if (! $request->session()->has(self::SESSION_KEY.'.storage')) {
            return redirect()->route('install.storage');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'url' => ['required', 'url', 'max:191', 'regex:#^https?://#i'],
            'locale' => ['required', 'in:zh_CN,en'],
            'timezone' => ['required', 'string', 'timezone'],
            'max_upload_mb' => ['required', 'integer', 'min:1', 'max:1024'],
            'default_quota_mb' => ['required', 'integer', 'min:64', 'max:10240'],
            'registration_quota_mb' => ['required', 'integer', 'min:64', 'max:10240'],
            'allow_registration' => ['nullable', 'boolean'],
            'email_verification' => ['nullable', 'boolean'],
        ]);

        $url = rtrim($data['url'], '/');
        $host = parse_url($url, PHP_URL_HOST) ?: '';
        if (! str_starts_with($url, 'https://') && ! in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return back()->withInput()->withErrors([
                'url' => __('A public site must use HTTPS. Set up TLS and enter an https:// URL.'),
            ]);
        }

        $data['url'] = $url;
        $data['allow_registration'] = (bool) ($data['allow_registration'] ?? false);
        $data['email_verification'] = (bool) ($data['email_verification'] ?? false);
        unset($data['_token']);

        $request->session()->put(self::SESSION_KEY.'.site', $data);

        return redirect()->route('install.admin');
    }

    public function admin(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has(self::SESSION_KEY.'.site')) {
            return redirect()->route('install.site');
        }

        $data = $request->session()->get(self::SESSION_KEY.'.admin', [
            'username' => 'admin',
            'email' => '',
        ]);

        return view('install.admin', ['data' => $data]);
    }

    public function saveAdmin(Request $request): RedirectResponse
    {
        if (! $request->session()->has(self::SESSION_KEY.'.site')) {
            return redirect()->route('install.site');
        }

        $data = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:64', 'regex:/^[A-Za-z0-9_\-\.]+$/'],
            'email' => ['required', 'string', 'email', 'max:191'],
            'password' => ['required', 'string', 'min:10', 'max:191', 'confirmed'],
        ]);

        $request->session()->put(self::SESSION_KEY.'.admin', [
            'username' => $data['username'],
            'email' => strtolower($data['email']),
            'password_encrypted' => Crypt::encryptString($data['password']),
        ]);

        return redirect()->route('install.confirm');
    }

    public function confirm(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has(self::SESSION_KEY.'.admin')) {
            return redirect()->route('install.admin');
        }

        $data = $request->session()->get(self::SESSION_KEY);

        return view('install.confirm', [
            'database' => $data['database'] ?? [],
            'storage' => $data['storage'] ?? [],
            'site' => $data['site'] ?? [],
            'admin' => $data['admin'] ?? [],
        ]);
    }

    public function run(Request $request, Installer $installer, RequirementsChecker $checker): View|RedirectResponse
    {
        $session = $request->session()->get(self::SESSION_KEY, []);

        if (! isset($session['database'], $session['storage'], $session['site'], $session['admin'])) {
            return redirect()->route('install.welcome')->withErrors([
                'install' => __('The installation data is incomplete. Please go through the wizard again.'),
            ]);
        }

        $items = $checker->check();
        if (! $checker->passed($items)) {
            return redirect()->route('install.requirements')->withErrors([
                'requirements' => __('The server environment changed. Please fix the failed checks before installing.'),
            ]);
        }

        try {
            $password = Crypt::decryptString($session['admin']['password_encrypted']);

            $result = $installer->install([
                'database' => $session['database'],
                'storage' => $session['storage'],
                'site' => $session['site'],
                'admin' => [
                    'username' => $session['admin']['username'],
                    'email' => $session['admin']['email'],
                    'password' => $password,
                ],
            ]);

            $request->session()->forget(self::SESSION_KEY);
            $request->session()->regenerate();

            return view('install.success', ['result' => $result]);
        } catch (Throwable $e) {
            return back()->withErrors(['install' => $e->getMessage()]);
        }
    }

    private function databaseRules(Request $request): array
    {
        if ($request->input('driver') === 'sqlite') {
            return [
                'driver' => ['required', 'in:sqlite'],
                'sqlite_path' => ['required', 'string', 'max:255'],
            ];
        }

        return [
            'driver' => ['required', 'in:mysql,mariadb'],
            'host' => ['required', 'string', 'max:191'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'database' => ['required', 'string', 'max:64'],
            'username' => ['required', 'string', 'max:191'],
            'password' => ['nullable', 'string', 'max:191'],
            'charset' => ['nullable', 'in:utf8mb4'],
            'collation' => ['nullable', 'in:utf8mb4_unicode_ci,utf8mb4_general_ci,utf8mb4_0900_ai_ci'],
        ];
    }

    private function databasePayload(Request $request): array
    {
        $saved = (array) $request->session()->get(self::SESSION_KEY.'.database', []);
        $password = (string) $request->input('password', '');

        if ($password === '' && ! empty($saved['password'])) {
            $password = (string) $saved['password'];
        }

        return [
            'driver' => (string) $request->input('driver'),
            'host' => (string) $request->input('host', ''),
            'port' => (int) $request->input('port', 3306),
            'database' => (string) $request->input('database', ''),
            'username' => (string) $request->input('username', ''),
            'password' => $password,
            'charset' => (string) $request->input('charset', 'utf8mb4'),
            'collation' => (string) $request->input('collation', 'utf8mb4_unicode_ci'),
            'sqlite_path' => (string) $request->input('sqlite_path', database_path('database.sqlite')),
        ];
    }

    private function storageRules(Request $request): array
    {
        if ($request->input('driver') === 's3') {
            return [
                'driver' => ['required', 'in:s3'],
                'bucket' => ['required', 'string', 'max:191'],
                'region' => ['required', 'string', 'max:64'],
                'access_key' => ['required', 'string', 'max:191'],
                'secret_key' => ['required', 'string', 'max:191'],
                'endpoint' => ['nullable', 'url', 'max:191'],
                'prefix' => ['nullable', 'string', 'max:191'],
                'path_style' => ['nullable', 'boolean'],
            ];
        }

        if ($request->input('driver') === 'webdav') {
            return [
                'driver' => ['required', 'in:webdav'],
                'webdav_url' => ['required', 'url', 'max:191'],
                'webdav_username' => ['required', 'string', 'max:191'],
                'webdav_password' => ['nullable', 'string', 'max:191'],
                'webdav_prefix' => ['nullable', 'string', 'max:191'],
                'webdav_verify_ssl' => ['nullable', 'boolean'],
            ];
        }

        return [
            'driver' => ['required', 'in:local'],
            'local_path' => ['required', 'string', 'max:255'],
        ];
    }

    private function storagePayload(Request $request): array
    {
        $saved = (array) $request->session()->get(self::SESSION_KEY.'.storage', []);
        $secret = (string) $request->input('secret_key', '');
        $webdavPassword = (string) $request->input('webdav_password', '');

        if ($secret === '' && ! empty($saved['secret_key'])) {
            $secret = (string) $saved['secret_key'];
        }

        if ($webdavPassword === '' && ! empty($saved['webdav_password'])) {
            $webdavPassword = (string) $saved['webdav_password'];
        }

        return [
            'driver' => (string) $request->input('driver', 'local'),
            'local_path' => (string) $request->input('local_path', config('nextrespack.storage_root')),
            'endpoint' => (string) $request->input('endpoint', ''),
            'region' => (string) $request->input('region', 'us-east-1'),
            'bucket' => (string) $request->input('bucket', ''),
            'access_key' => (string) $request->input('access_key', ''),
            'secret_key' => $secret,
            'path_style' => (bool) $request->input('path_style', true),
            'prefix' => (string) $request->input('prefix', ''),
            'url' => (string) $request->input('webdav_url', ''),
            'username' => (string) $request->input('webdav_username', ''),
            'webdav_password' => $webdavPassword,
            'webdav_prefix' => (string) $request->input('webdav_prefix', ''),
            'verify_ssl' => (bool) $request->input('webdav_verify_ssl', true),
        ];
    }
}
