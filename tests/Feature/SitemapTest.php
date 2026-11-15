<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, string> */
    private function sitemapLocations(): array
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        $xml = simplexml_load_string((string) $response->getContent());
        $this->assertNotFalse($xml);

        return array_map('strval', $xml->xpath('//*[local-name()="loc"]') ?: []);
    }

    public function test_sitemap_contains_only_the_homepage_pack_format_index_and_guest_upload(): void
    {
        $locations = $this->sitemapLocations();

        $this->assertCount(3, $locations);
        $this->assertSame([
            route('home'),
            route('pack-formats.index'),
            route('guest.upload'),
        ], $locations);
    }

    public function test_pack_format_detail_routes_are_explicitly_excluded_from_the_sitemap(): void
    {
        Route::get('/minecraft/pack-formats/{format}', fn () => 'detail')
            ->where('format', '[0-9]+(?:\.[0-9]+)?')
            ->name('pack-formats.show');

        $detailUrl = url('/minecraft/pack-formats/15');

        $this->get($detailUrl)->assertOk();

        $response = $this->get('/sitemap.xml');

        $response->assertOk();

        $content = (string) $response->getContent();

        $this->assertStringNotContainsString('/minecraft/pack-formats/', $content);
        $this->assertStringNotContainsString($detailUrl, $content);
        $this->assertStringContainsString('<loc>'.route('pack-formats.index').'</loc>', $content);
    }

    public function test_robots_txt_advertises_the_sitemap_and_disallows_private_paths(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

        $content = (string) $response->getContent();

        $this->assertStringContainsString('Sitemap: '.route('sitemap'), $content);

        foreach ([
            'Disallow: /admin',
            'Disallow: /dashboard',
            'Disallow: /account',
            'Disallow: /install',
            'Disallow: /login',
            'Disallow: /captcha',
            'Disallow: /dl/',
            'Disallow: /upload/manage/',
        ] as $rule) {
            $this->assertStringContainsString($rule, $content);
        }
    }

    public function test_public_index_pages_expose_canonical_and_open_graph_metadata(): void
    {
        foreach ([route('home'), route('pack-formats.index'), route('guest.upload')] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('<link rel="canonical" href="'.$url.'">', false)
                ->assertSee('property="og:title"', false)
                ->assertSee('property="og:description"', false)
                ->assertSee('property="og:url"', false);
        }
    }
}
