<?php

namespace App\Http\Controllers;

use App\Models\ResourcePack;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class SitemapController extends Controller
{
    /**
     * Only these public entry points may ever appear in the sitemap.
     * Pack format detail pages are intentionally absent.
     */
    public const PUBLIC_ROUTES = [
        'home',
        'pack-formats.index',
        'guest.upload',
    ];

    /**
     * Detail routes must stay excluded even if someone adds them to the list above.
     */
    public const EXCLUDED_ROUTES = [
        'pack-formats.show',
        'pack-formats.detail',
    ];

    public function __invoke(): Response
    {
        $now = now();
        $lastContentUpdate = $now;

        try {
            $latestPack = ResourcePack::query()
                ->where('status', ResourcePack::STATUS_ACTIVE)
                ->max('updated_at');

            if ($latestPack) {
                $lastContentUpdate = Carbon::parse($latestPack);
            }
        } catch (\Throwable) {
            // The sitemap must remain available while the database is being installed.
        }

        $urls = [];

        foreach (self::PUBLIC_ROUTES as $name) {
            if (in_array($name, self::EXCLUDED_ROUTES, true)) {
                continue;
            }

            $loc = route($name);

            if ($this->isPackFormatDetailUrl($loc)) {
                continue;
            }

            $urls[] = [
                'loc' => $loc,
                'lastmod' => $name === 'home' ? $lastContentUpdate->toAtomString() : $now->toAtomString(),
                'changefreq' => $name === 'home' ? 'daily' : ($name === 'pack-formats.index' ? 'weekly' : 'monthly'),
                'priority' => $name === 'home' ? '1.0' : ($name === 'pack-formats.index' ? '0.9' : '0.6'),
            ];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .view('sitemap', ['urls' => $urls])->render();

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    private function isPackFormatDetailUrl(string $url): bool
    {
        $path = (string) parse_url($url, PHP_URL_PATH);

        return (bool) preg_match('#^/minecraft/pack-formats/.+#', $path);
    }
}
