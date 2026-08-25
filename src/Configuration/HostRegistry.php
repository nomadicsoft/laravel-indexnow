<?php

namespace NomadicSoft\LaravelIndexNow\Configuration;

use Illuminate\Contracts\Config\Repository;
use NomadicSoft\LaravelIndexNow\Exceptions\ConfigurationException;

final class HostRegistry
{
    public function __construct(private readonly Repository $config) {}

    /** @return array<string, HostConfiguration> */
    public function all(): array
    {
        $configured = $this->config->get('indexnow.hosts', []);

        if (! is_array($configured) || $configured === []) {
            throw new ConfigurationException('No IndexNow hosts are configured.');
        }

        $defaults = [
            'endpoint' => (string) $this->config->get('indexnow.endpoint'),
            'scheme' => (string) $this->config->get('indexnow.default_scheme', 'https'),
        ];
        $hosts = [];

        foreach ($configured as $host => $configuration) {
            if (! is_string($host) || ! is_array($configuration)) {
                throw new ConfigurationException('Each IndexNow host must be an associative configuration entry.');
            }

            $resolved = HostConfiguration::fromArray($host, $configuration, $defaults);
            $hosts[$resolved->host] = $resolved;
        }

        return $hosts;
    }

    public function forHost(string $host): HostConfiguration
    {
        $host = strtolower(rtrim(trim($host), '.'));
        $configuration = $this->all()[$host] ?? null;

        if ($configuration === null) {
            throw new ConfigurationException(sprintf(
                'No IndexNow key is configured for the exact hostname %s.',
                $host,
            ));
        }

        return $configuration;
    }

    public function default(): HostConfiguration
    {
        return $this->forHost((string) $this->config->get('indexnow.default_host'));
    }
}
