<?php

namespace NomadicSoft\LaravelIndexNow\Support;

use Illuminate\Contracts\Config\Repository;
use NomadicSoft\LaravelIndexNow\Configuration\HostConfiguration;
use NomadicSoft\LaravelIndexNow\Configuration\HostRegistry;
use NomadicSoft\LaravelIndexNow\Exceptions\InvalidUrlException;
use Stringable;

final class UrlNormalizer
{
    public function __construct(
        private readonly Repository $config,
        private readonly HostRegistry $hosts,
    ) {}

    /** @return array{url: string, host: HostConfiguration} */
    public function normalize(string|Stringable $value): array
    {
        $url = trim((string) $value);

        if ($url === '') {
            throw new InvalidUrlException('IndexNow URLs cannot be empty.');
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            $default = $this->hosts->default();
            $url = sprintf('%s://%s%s', $default->scheme, $default->host, $url);
        }

        if (preg_match('/[\x00-\x20\x7F]/', $url)) {
            throw new InvalidUrlException(sprintf('IndexNow URL contains whitespace or control characters: %s', $url));
        }

        /* Fragments are browser-local and are never sent to IndexNow. */
        $fragmentPosition = strpos($url, '#');
        if ($fragmentPosition !== false) {
            $url = substr($url, 0, $fragmentPosition);
        }

        $parts = parse_url($url);

        if (! is_array($parts)) {
            throw new InvalidUrlException(sprintf('IndexNow URL is malformed: %s', $url));
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $hostName = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));
        $allowedSchemes = (array) $this->config->get('indexnow.urls.allowed_schemes', ['http', 'https']);

        if (! in_array($scheme, $allowedSchemes, true) || $hostName === '') {
            throw new InvalidUrlException(sprintf('IndexNow URL must be an absolute allowed HTTP(S) URL: %s', $url));
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidUrlException('IndexNow URLs cannot contain credentials.');
        }

        $host = $this->hosts->forHost($hostName);
        $normalized = $scheme.'://'.$hostName;

        if (isset($parts['port'])) {
            $normalized .= ':'.$parts['port'];
        }

        $normalized .= (string) ($parts['path'] ?? '');

        if (array_key_exists('query', $parts)) {
            $normalized .= '?'.$parts['query'];
        }

        $maximumLength = max(1, (int) $this->config->get('indexnow.urls.max_length', 2048));

        if (strlen($normalized) > $maximumLength) {
            throw new InvalidUrlException(sprintf('IndexNow URL exceeds %d bytes.', $maximumLength));
        }

        if (! filter_var($normalized, FILTER_VALIDATE_URL)) {
            throw new InvalidUrlException(sprintf('IndexNow URL is invalid: %s', $normalized));
        }

        if (! $host->owns($normalized)) {
            throw new InvalidUrlException(sprintf(
                'IndexNow URL %s is outside the scope of its configured key location.',
                $normalized,
            ));
        }

        return ['url' => $normalized, 'host' => $host];
    }
}
