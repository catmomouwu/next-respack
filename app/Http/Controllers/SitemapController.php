<?php

namespace App\Http\Controllers;

use App\Models\ResourcePack;
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

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .view('sitemap', ['urls' => $urls])->render();

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
