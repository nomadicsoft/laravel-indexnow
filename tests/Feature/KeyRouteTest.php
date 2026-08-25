<?php

namespace NomadicSoft\LaravelIndexNow\Tests\Feature;

use NomadicSoft\LaravelIndexNow\Tests\TestCase;

final class KeyRouteTest extends TestCase
{
    public function test_it_serves_the_key_only_on_its_configured_host(): void
    {
        $key = 'abc12345abc12345abc12345abc12345';

        $this->get('https://example.com/'.$key.'.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
            ->assertSeeText($key);

        $this->get('https://unconfigured.example/'.$key.'.txt')
            ->assertNotFound();
    }

    public function test_the_fallback_route_does_not_serve_custom_key_locations(): void
    {
        $key = 'abc12345abc12345abc12345abc12345';
        config()->set('indexnow.hosts.example.com.key_location', 'https://example.com/seo/'.$key.'.txt');

        $this->get('https://example.com/'.$key.'.txt')
            ->assertNotFound();
    }
}
