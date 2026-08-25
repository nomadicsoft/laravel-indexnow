<?php

namespace NomadicSoft\LaravelIndexNow\Support;

use Illuminate\Contracts\Cache\Factory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository;

final class NotificationLedger
{
    public function __construct(
        private readonly Factory $cache,
        private readonly Repository $config,
    ) {}

    /**
     * @param  list<string>  $urls
     * @return array{claimed: list<string>, coalesced: list<string>}
     */
    public function claim(array $urls): array
    {
        if (! $this->config->get('indexnow.dedupe.enabled', true)) {
            return ['claimed' => $urls, 'coalesced' => []];
        }

        $ttl = max(1, (int) $this->config->get('indexnow.dedupe.ttl', 300));
        $claimed = [];
        $coalesced = [];

        foreach ($urls as $url) {
            if ($this->repository()->add($this->key($url), true, $ttl)) {
                $claimed[] = $url;
            } else {
                $coalesced[] = $url;
            }
        }

        return ['claimed' => $claimed, 'coalesced' => $coalesced];
    }

    /** @param  list<string>  $urls */
    public function release(array $urls): void
    {
        if (! $this->config->get('indexnow.dedupe.enabled', true)) {
            return;
        }

        foreach ($urls as $url) {
            $this->repository()->forget($this->key($url));
        }
    }

    private function repository(): CacheRepository
    {
        $store = $this->config->get('indexnow.dedupe.store');

        return $this->cache->store(is_string($store) && $store !== '' ? $store : null);
    }

    private function key(string $url): string
    {
        return (string) $this->config->get('indexnow.dedupe.prefix', 'indexnow:submitted:')
            .hash('sha256', $url);
    }
}
