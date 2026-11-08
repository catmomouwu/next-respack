<?php

namespace App\Http\Controllers;

use App\Models\MinecraftPackFormat;
use App\Models\ResourcePack;
use App\Services\Minecraft\PackFormatLookup;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class SitemapController extends Controller
{
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

        $urls = [
            [
                'loc' => route('home'),
                'lastmod' => $lastContentUpdate->toAtomString(),
                'changefreq' => 'daily',
                'priority' => '1.0',
            ],
            [
                'loc' => route('pack-formats.index'),
                'lastmod' => $now->toAtomString(),
                'changefreq' => 'weekly',
                'priority' => '0.9',
            ],
            [
                'loc' => route('guest.upload'),
                'lastmod' => $now->toAtomString(),
                'changefreq' => 'monthly',
                'priority' => '0.6',
            ],
        ];

        try {
            $formats = MinecraftPackFormat::query()
                ->where('edition', PackFormatLookup::EDITION)
                ->withMax('versions', 'updated_at')
                ->orderByDesc('format_major')
                ->orderByDesc('format_minor')
                ->get(['id', 'format_value', 'updated_at']);

            foreach ($formats as $format) {
                if (! $format->format_value) {
                    continue;
                }

                $lastmod = $format->updated_at;

                if ($format->versions_max_updated_at) {
                    $versionLastmod = Carbon::parse($format->versions_max_updated_at);

                    if (! $lastmod || $versionLastmod->gt($lastmod)) {
                        $lastmod = $versionLastmod;
                    }
                }

                $urls[] = [
                    'loc' => route('pack-formats.show', $format->format_value),
                    'lastmod' => ($lastmod ?? $now)->toAtomString(),
                    'changefreq' => 'monthly',
                    'priority' => '0.7',
                ];
            }
        } catch (\Throwable) {
            // Ignore missing pack format tables during installation.
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .view('sitemap', ['urls' => $urls])->render();

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
