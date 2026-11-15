<?php

namespace App\Services\Installation;

class RequirementsChecker
{
    /** @return array<int, array{key:string,label:string,value:string,status:string,required:bool,fix:string}> */
    public function check(): array
    {
        $items = [];

        $phpOk = version_compare(PHP_VERSION, '8.3.0', '>=');
        $items[] = $this->item(
            'php_version',
            __('PHP version'),
            PHP_VERSION,
            $phpOk ? 'pass' : 'fail',
            true,
            __('PHP 8.3 or newer is required.')
        );

        $extensions = [
            'ctype', 'curl', 'dom', 'fileinfo', 'filter', 'hash', 'json', 'mbstring',
            'openssl', 'pcre', 'pdo', 'pdo_mysql', 'session', 'tokenizer', 'xml', 'zip',
        ];

        foreach ($extensions as $ext) {
            $loaded = extension_loaded($ext);
            $items[] = $this->item(
                'ext_'.$ext,
                __('PHP extension').' '.$ext,
                $loaded ? __('Loaded') : __('Missing'),
                $loaded ? 'pass' : 'fail',
                true,
                __('Install the PHP extension ":ext" and restart PHP.', ['ext' => $ext])
            );
        }

        $sqlite = extension_loaded('pdo_sqlite');
        $items[] = $this->item(
            'ext_pdo_sqlite',
            __('SQLite driver (optional)'),
            $sqlite ? __('Loaded') : __('Missing'),
            $sqlite ? 'pass' : 'warn',
            false,
            __('Optional. Needed only when deploying with SQLite.')
        );

        $uploadOn = (bool) ini_get('file_uploads');
        $uploadMax = $this->toBytes((string) ini_get('upload_max_filesize'));
        $postMax = $this->toBytes((string) ini_get('post_max_size'));

        $items[] = $this->item(
            'file_uploads',
            __('File uploads enabled'),
            $uploadOn ? __('Enabled') : __('Disabled'),
            $uploadOn ? 'pass' : 'fail',
            true,
            __('Enable "file_uploads" in php.ini.')
        );
        $items[] = $this->item(
            'upload_max_filesize',
            __('upload_max_filesize'),
            (string) ini_get('upload_max_filesize'),
            $uploadMax >= 64 * 1024 * 1024 ? 'pass' : 'warn',
            false,
            __('Increase upload_max_filesize to allow larger resource packs (512 MB recommended).')
        );
        $items[] = $this->item(
            'post_max_size',
            __('post_max_size'),
            (string) ini_get('post_max_size'),
            $postMax >= 64 * 1024 * 1024 ? 'pass' : 'warn',
            false,
            __('post_max_size must be larger than your maximum resource pack size.')
        );

        $memory = $this->toBytes((string) ini_get('memory_limit'));
        $items[] = $this->item(
            'memory_limit',
            __('memory_limit'),
            (string) ini_get('memory_limit'),
            $memory === -1 || $memory >= 128 * 1024 * 1024 ? 'pass' : 'warn',
            false,
            __('128 MB or more is recommended. Large packs are streamed, not loaded into memory.')
        );

        $maxExecution = (int) ini_get('max_execution_time');
        $items[] = $this->item(
            'max_execution_time',
            __('max_execution_time'),
            $maxExecution === 0 ? __('Unlimited') : $maxExecution.'s',
            $maxExecution === 0 || $maxExecution >= 60 ? 'pass' : 'warn',
            false,
            __('A value of 60 seconds or more is recommended for large uploads.')
        );

        $paths = [
            'storage' => storage_path(),
            'storage_app' => storage_path('app'),
            'bootstrap_cache' => base_path('bootstrap/cache'),
            'database' => database_path(),
            'root' => base_path(),
        ];

        foreach ($paths as $key => $path) {
            $writable = is_dir($path) ? is_writable($path) : is_writable(dirname($path));
            $items[] = $this->item(
                'writable_'.$key,
                __('Write permission').': '.$this->relative($path),
                $writable ? __('Writable') : __('Not writable'),
                $writable ? 'pass' : 'fail',
                true,
                __('Grant write permission (for example chmod 775) to this directory.')
            );
        }

        $envWritable = app(EnvWriter::class)->isWritable();
        $items[] = $this->item(
            'env_writable',
            '.env '. __('writable'),
            $envWritable ? __('Writable') : __('Not writable'),
            $envWritable ? 'pass' : 'fail',
            true,
            __('Create a writable .env file in the project root or make the project root writable during installation.')
        );

        $free = @disk_free_space(base_path());
        $items[] = $this->item(
            'disk_space',
            __('Free disk space'),
            $free !== false ? nr_bytes($free) : __('Unknown'),
            ($free !== false && $free >= 100 * 1024 * 1024) ? 'pass' : ($free === false ? 'warn' : 'fail'),
            false,
            __('At least 100 MB of free disk space is required for installation and uploads.')
        );

        $manifest = public_path('build/manifest.json');
        $items[] = $this->item(
            'frontend_assets',
            __('Built frontend assets'),
            is_file($manifest) ? __('Present') : __('Missing'),
            is_file($manifest) ? 'pass' : 'warn',
            false,
            __('The release package already contains built assets. If you are developing, run npm install and npm run build.')
        );

        $https = request()->isSecure() || str_starts_with((string) config('app.url'), 'https://');
        $items[] = $this->item(
            'https',
            __('HTTPS'),
            $https ? __('Enabled') : __('Not detected'),
            $https ? 'pass' : 'warn',
            false,
            __('HTTPS is strongly recommended. Minecraft clients should only download resource packs over HTTPS.')
        );

        $publicReal = realpath(public_path());
        $storageReal = realpath(storage_path());
        $envInPublic = is_file(public_path('.env'));
        $storageInPublic = $publicReal && $storageReal && str_starts_with($storageReal, $publicReal.DIRECTORY_SEPARATOR);
        $items[] = $this->item(
            'directory_safety',
            __('Directory safety'),
            ($envInPublic || $storageInPublic) ? __('Unsafe') : __('Safe'),
            ($envInPublic || $storageInPublic) ? 'fail' : 'pass',
            true,
            __('The Laravel application directory must not be inside the public web root. Point the document root to public/ or use the shared-hosting package.')
        );

        return $items;
    }

    public function passed(array $items): bool
    {
        foreach ($items as $item) {
            if ($item['required'] && $item['status'] === 'fail') {
                return false;
            }
        }

        return true;
    }

    public function summary(array $items): array
    {
        $summary = ['pass' => 0, 'warn' => 0, 'fail' => 0];

        foreach ($items as $item) {
            $summary[$item['status']]++;
        }

        return $summary;
    }

    private function item(string $key, string $label, string $value, string $status, bool $required, string $fix): array
    {
        return compact('key', 'label', 'value', 'status', 'required', 'fix');
    }

    private function toBytes(string $value): int
    {
        $value = trim($value);

        if ($value === '' || $value === '-1') {
            return -1;
        }

        $unit = strtolower(substr($value, -1));
        $number = (float) $value;

        return (int) match ($unit) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }

    private function relative(string $path): string
    {
        return str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
    }
}
