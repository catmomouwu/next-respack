<?php

namespace App\Services\Download;

use App\Models\ResourcePack;

class ServerPropertiesGenerator
{
    public function __construct(private readonly DownloadTokenService $tokens)
    {
    }

    public function generate(ResourcePack $pack, bool $require = true): string
    {
        $version = $pack->currentVersion;

        if (! $version || ! $version->download_enabled || ! $pack->isActive()) {
            throw new \RuntimeException('No downloadable version is available for this resource pack.');
        }

        $url = $this->tokens->httpsUrl($version);
        $lines = [
            'resource-pack='.$url,
            'resource-pack-sha1='.$version->sha1,
            'require-resource-pack='.($require ? 'true' : 'false'),
        ];

        return implode("\n", $lines)."\n";
    }

    public function warning(ResourcePack $pack): ?string
    {
        $version = $pack->currentVersion;
        if (! $version) {
            return __('This resource pack has no downloadable version.');
        }

        $siteUrl = (string) nr_setting('site_url', (string) config('app.url'));

        if (! str_starts_with($siteUrl, 'https://')) {
            return __('The download URL is not HTTPS. Configure APP_URL/site_url with https:// before using the link in server.properties.');
        }

        if (! $version->download_enabled || ! $pack->isActive()) {
            return __('The download link is disabled. Enable it before generating server.properties.');
        }

        return null;
    }
}
