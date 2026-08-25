<?php

namespace NomadicSoft\LaravelIndexNow\Tests\Unit;

use NomadicSoft\LaravelIndexNow\Configuration\HostConfiguration;
use NomadicSoft\LaravelIndexNow\Exceptions\ConfigurationException;
use PHPUnit\Framework\TestCase;

final class HostConfigurationTest extends TestCase
{
    public function test_custom_key_locations_scope_owned_urls(): void
    {
        $host = new HostConfiguration(
            host: 'example.com',
            key: 'abc12345abc12345',
            keyLocation: 'https://example.com/docs/abc12345abc12345.txt',
            endpoint: 'https://api.indexnow.org/indexnow',
            scheme: 'https',
        );

        $this->assertTrue($host->owns('https://example.com/docs/guide'));
        $this->assertTrue($host->owns('https://example.com/docs'));
        $this->assertFalse($host->owns('https://example.com/blog/post'));
        $this->assertFalse($host->owns('https://www.example.com/docs/guide'));
    }

    public function test_it_rejects_a_key_location_on_another_host(): void
    {
        $this->expectException(ConfigurationException::class);

        new HostConfiguration(
            host: 'example.com',
            key: 'abc12345abc12345',
            keyLocation: 'https://cdn.example.com/abc12345abc12345.txt',
            endpoint: 'https://api.indexnow.org/indexnow',
            scheme: 'https',
        );
    }
}
