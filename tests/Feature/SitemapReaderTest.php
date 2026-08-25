<?php

namespace NomadicSoft\LaravelIndexNow\Tests\Feature;

use Illuminate\Support\Facades\Http;
use NomadicSoft\LaravelIndexNow\Support\SitemapReader;
use NomadicSoft\LaravelIndexNow\Tests\TestCase;

final class SitemapReaderTest extends TestCase
{
    public function test_it_reads_url_sets_and_same_host_sitemap_indexes(): void
    {
        if (! function_exists('simplexml_load_string')) {
            $this->markTestSkipped('SimpleXML is unavailable.');
        }

        Http::fake([
            'https://example.com/sitemap.xml' => Http::response(
                '<?xml version="1.0"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><sitemap><loc>https://example.com/pages.xml</loc></sitemap></sitemapindex>',
                200,
            ),
            'https://example.com/pages.xml' => Http::response(
                '<?xml version="1.0"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>https://example.com/one</loc></url><url><loc>https://example.com/two?x=1&amp;y=2</loc></url></urlset>',
                200,
            ),
        ]);

        $urls = app(SitemapReader::class)->read('https://example.com/sitemap.xml');

        $this->assertSame([
            'https://example.com/one',
            'https://example.com/two?x=1&y=2',
        ], $urls);
    }
}
