<?php

namespace Tests\Feature;

use App\Models\MinecraftPackFormat;
use App\Models\MinecraftPackFormatVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    private function seedFormat(): MinecraftPackFormat
    {
        $format = MinecraftPackFormat::create([
            'edition' => 'java_resource_pack',
            'format_key' => 'java_resource_pack:15:-',
            'format_major' => 15,
            'format_minor' => null,
            'format_value' => '15',
            'is_manual' => false,
        ]);

        MinecraftPackFormatVersion::create([
            'minecraft_pack_format_id' => $format->id,
            'edition' => 'java_resource_pack',
            'version_name' => '1.20',
            'version_type' => 'release',
            'release_date' => '2023-06-07',
            'is_manual' => false,
        ]);

        return $format;
    }

    public function test_home_page_exposes_search_metadata(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<title>', false)
            ->assertSee('name="description"', false)
            ->assertSee('rel="canonical"', false)
            ->assertSee('property="og:title"', false)
            ->assertSee('application/ld+json', false)
            ->assertSee('index,follow');
    }

    public function test_private_pages_are_marked_noindex(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('noindex,nofollow');
    }

    public function test_sitemap_lists_public_pages_and_pack_formats(): void
    {
        $this->seedFormat();

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee(route('home'), false)
            ->assertSee(route('pack-formats.index'), false)
            ->assertSee(route('guest.upload'), false)
            ->assertSee(route('pack-formats.show', '15'), false)
            ->assertSee('<lastmod>', false)
            ->assertDontSee(route('login'), false);
    }

    public function test_robots_txt_points_to_the_sitemap_and_blocks_private_areas(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Sitemap: '.route('sitemap'))
            ->assertSee('Disallow: /admin')
            ->assertSee('Disallow: /upload/');
    }

    public function test_pack_format_page_has_unique_metadata_and_breadcrumbs(): void
    {
        $this->seedFormat();

        $this->get('/minecraft/pack-formats/15')
            ->assertOk()
            ->assertSee(route('pack-formats.show', '15'), false)
            ->assertSee('BreadcrumbList', false)
            ->assertSee('"pack_format": 15', false)
            ->assertSee('1.20')
            ->assertSee('index,follow');
    }

    public function test_unknown_pack_format_returns_not_found(): void
    {
        $this->get('/minecraft/pack-formats/999')->assertNotFound();
    }
}
