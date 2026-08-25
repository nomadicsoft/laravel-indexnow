<?php

namespace NomadicSoft\LaravelIndexNow\Configuration;

use NomadicSoft\LaravelIndexNow\Exceptions\ConfigurationException;

final readonly class HostConfiguration
{
    public function __construct(
        public string $host,
        public string $key,
        public string $keyLocation,
        public string $endpoint,
        public string $scheme,
    ) {
        if (! preg_match('/\A[A-Za-z0-9-]{8,128}\z/', $this->key)) {
            throw new ConfigurationException(sprintf(
                'The IndexNow key for %s must contain 8-128 letters, numbers, or hyphens.',
                $this->host,
            ));
        }

        $this->assertUrl('key location', $this->keyLocation);
        $this->assertUrl('endpoint', $this->endpoint);

        $keyHost = strtolower((string) parse_url($this->keyLocation, PHP_URL_HOST));

        if ($keyHost !== $this->host) {
            throw new ConfigurationException(sprintf(
                'The IndexNow key location for %s must use the same exact hostname.',
                $this->host,
            ));
        }

        if (parse_url($this->keyLocation, PHP_URL_QUERY) !== null
            || parse_url($this->keyLocation, PHP_URL_FRAGMENT) !== null) {
            throw new ConfigurationException(sprintf(
                'The IndexNow key location for %s cannot contain a query or fragment.',
                $this->host,
            ));
        }
    }

    public static function fromArray(string $host, array $configuration, array $defaults): self
    {
        $host = strtolower(rtrim(trim($host), '.'));

        if ($host === '' || parse_url('https://'.$host, PHP_URL_HOST) !== $host) {
            throw new ConfigurationException('IndexNow host configuration contains an invalid hostname.');
        }

        $key = trim((string) ($configuration['key'] ?? ''));
        $scheme = strtolower((string) ($configuration['scheme'] ?? $defaults['scheme'] ?? 'https'));
        $keyLocation = trim((string) ($configuration['key_location'] ?? ''));

        if ($keyLocation === '' && $key !== '') {
            $keyLocation = sprintf('%s://%s/%s.txt', $scheme, $host, $key);
        }

        return new self(
            host: $host,
            key: $key,
            keyLocation: $keyLocation,
            endpoint: (string) (($configuration['endpoint'] ?? null) ?: $defaults['endpoint']),
            scheme: $scheme,
        );
    }

    public function owns(string $url): bool
    {
        if (strtolower((string) parse_url($url, PHP_URL_HOST)) !== $this->host) {
            return false;
        }

        $scope = $this->keyScope();

        if ($scope === '/') {
            return true;
        }

        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '/');

        return $path === rtrim($scope, '/') || str_starts_with($path, $scope);
    }

    public function keyScope(): string
    {
        $path = (string) (parse_url($this->keyLocation, PHP_URL_PATH) ?: '/');
        $directory = str_replace('\\', '/', dirname($path));

        if ($directory === '.' || $directory === '/') {
            return '/';
        }

        return '/'.trim($directory, '/').'/';
    }

    public function usesStandardRootKeyLocation(): bool
    {
        return (string) parse_url($this->keyLocation, PHP_URL_PATH) === '/'.$this->key.'.txt';
    }

    private function assertUrl(string $label, string $url): void
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = (string) parse_url($url, PHP_URL_HOST);

        if (! filter_var($url, FILTER_VALIDATE_URL)
            || ! in_array($scheme, ['http', 'https'], true)
            || $host === '') {
            throw new ConfigurationException(sprintf(
                'The IndexNow %s for %s must be an absolute HTTP(S) URL.',
                $label,
                $this->host,
            ));
        }
    }
}
