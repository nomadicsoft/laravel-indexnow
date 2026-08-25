<?php

namespace NomadicSoft\LaravelIndexNow\Tests;

use NomadicSoft\LaravelIndexNow\IndexNowServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [IndexNowServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $key = 'abc12345abc12345abc12345abc12345';

        $app['config']->set('app.url', 'https://example.com');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('indexnow.enabled', true);
        $app['config']->set('indexnow.endpoint', 'https://api.indexnow.test/indexnow');
        $app['config']->set('indexnow.default_host', 'example.com');
        $app['config']->set('indexnow.default_scheme', 'https');
        $app['config']->set('indexnow.hosts', [
            'example.com' => [
                'key' => $key,
                'key_location' => 'https://example.com/'.$key.'.txt',
                'endpoint' => 'https://api.indexnow.test/indexnow',
                'scheme' => 'https',
            ],
        ]);
        $app['config']->set('indexnow.batch_size', 500);
        $app['config']->set('indexnow.queue.enabled', false);
        $app['config']->set('indexnow.queue.after_commit', true);
        $app['config']->set('indexnow.queue.delay', 0);
        $app['config']->set('indexnow.dedupe.enabled', true);
        $app['config']->set('indexnow.dedupe.store', 'array');
        $app['config']->set('indexnow.dedupe.ttl', 300);
        $app['config']->set('indexnow.serve_key', true);
    }
}
