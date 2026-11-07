<?php

namespace App\Services\Installation;

use App\Models\Installation;
use Illuminate\Support\Facades\Log;
use Throwable;

class InstallState
{
    public function installed(): bool
    {
        if (config('nextrespack.force_installed')) {
            return true;
        }

        if (is_file(config('nextrespack.install_lock'))) {
            return true;
        }

        if (filter_var(env('APP_INSTALLED', false), FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        return $this->hasInstallationRecord();
    }

    public function hasInstallationRecord(): bool
    {
        try {
            return Installation::query()->exists();
        } catch (Throwable) {
            return false;
        }
    }

    public function installation(): ?Installation
    {
        try {
            return Installation::query()->latest('id')->first();
        } catch (Throwable) {
            return null;
        }
    }

    public function lockPath(): string
    {
        return (string) config('nextrespack.install_lock');
    }

    public function writeLock(array $data): void
    {
        $path = $this->lockPath();
        $dir = dirname($path);

        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new \RuntimeException('Unable to create the installation lock directory.');
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $tmp = tempnam($dir, '.nrlock');

        if ($tmp === false || file_put_contents($tmp, $json) === false) {
            throw new \RuntimeException('Unable to write the installation lock file.');
        }

        @chmod($tmp, 0640);

        if (! @rename($tmp, $path)) {
            @unlink($tmp);
            throw new \RuntimeException('Unable to move the installation lock file into place.');
        }
    }

    public function removeLock(): void
    {
        if (is_file($this->lockPath())) {
            @unlink($this->lockPath());
        }
    }

    public function state(): array
    {
        $path = config('nextrespack.install_state');

        if (! is_file($path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : [];
    }

    public function saveState(array $state): void
    {
        $path = config('nextrespack.install_state');
        $dir = dirname($path);

        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            return;
        }

        $state['updated_at'] = now()->toIso8601String();

        try {
            file_put_contents($path, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
        } catch (Throwable $e) {
            Log::warning('Unable to persist installer state: '.$e->getMessage());
        }
    }

    public function clearState(): void
    {
        $path = config('nextrespack.install_state');

        if (is_file($path)) {
            @unlink($path);
        }
    }
}
