<?php

namespace App\Services\Download;

use App\Models\ResourcePackVersion;
use Illuminate\Support\Facades\Hash;

class DownloadTokenService
{
    public function tokenFor(ResourcePackVersion $version): string
    {
        $key = (string) config('app.key');
        if ($key === '') {
            $key = 'next-respack-installation-key';
        }

        $binary = hash_hmac('sha256', 'download|'.$version->public_id.'|'.$version->token_generation, $key, true);

        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }

    public function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public function syncHash(ResourcePackVersion $version): void
    {
        $version->forceFill(['token_hash' => $this->hash($this->tokenFor($version))])->save();
    }

    public function reset(ResourcePackVersion $version): string
    {
        $version->token_generation = (int) $version->token_generation + 1;
        $version->save();

        $this->syncHash($version);

        return $this->tokenFor($version);
    }

    public function find(string $token): ?ResourcePackVersion
    {
        if ($token === '' || strlen($token) > 128) {
            return null;
        }

        return ResourcePackVersion::query()
            ->where('token_hash', $this->hash($token))
            ->first();
    }

    public function url(ResourcePackVersion $version, ?string $baseUrl = null): string
    {
        $base = rtrim($baseUrl ?? (string) config('app.url'), '/');
        $token = $this->tokenFor($version);

        return $base.'/dl/'.$token;
    }

    public function httpsUrl(ResourcePackVersion $version): string
    {
        $url = $this->url($version);

        return preg_replace('#^http://#i', 'https://', $url) ?: $url;
    }
}
