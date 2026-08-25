<?php

namespace NomadicSoft\LaravelIndexNow\Client;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Client\Factory;
use NomadicSoft\LaravelIndexNow\Configuration\HostConfiguration;
use NomadicSoft\LaravelIndexNow\DTO\SubmissionResult;
use NomadicSoft\LaravelIndexNow\Enums\SubmissionState;
use NomadicSoft\LaravelIndexNow\Exceptions\ConfigurationException;
use NomadicSoft\LaravelIndexNow\Support\RetryAfter;

final class IndexNowClient
{
    public function __construct(
        private readonly Factory $http,
        private readonly Repository $config,
    ) {}

    /** @param  list<string>  $urls */
    public function submit(HostConfiguration $host, array $urls): SubmissionResult
    {
        $count = count($urls);

        if ($count < 1 || $count > 10000) {
            throw new ConfigurationException('An IndexNow request must contain between 1 and 10,000 URLs.');
        }

        $response = $this->http
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'Content-Type' => 'application/json; charset=utf-8',
                'User-Agent' => (string) $this->config->get('indexnow.http.user_agent'),
            ])
            ->connectTimeout(max(1, (int) $this->config->get('indexnow.http.connect_timeout', 5)))
            ->timeout(max(1, (int) $this->config->get('indexnow.http.timeout', 10)))
            ->post($host->endpoint, [
                'host' => $host->host,
                'key' => $host->key,
                'keyLocation' => $host->keyLocation,
                'urlList' => $urls,
            ]);

        $status = $response->status();
        $bodyLimit = max(0, (int) $this->config->get('indexnow.http.response_body_limit', 2048));
        $body = trim($response->body());

        if ($bodyLimit === 0 || $body === '') {
            $body = null;
        } elseif (strlen($body) > $bodyLimit) {
            $body = substr($body, 0, $bodyLimit).'...';
        }

        return new SubmissionResult(
            host: $host->host,
            endpoint: $host->endpoint,
            urls: $urls,
            status: $status,
            state: $this->stateFor($status),
            retryAfterSeconds: $status === 429
                ? RetryAfter::seconds($response->header('Retry-After'))
                : null,
            responseBody: $body,
        );
    }

    private function stateFor(int $status): SubmissionState
    {
        return match (true) {
            $status === 200 => SubmissionState::Accepted,
            $status === 202 => SubmissionState::KeyValidationPending,
            $status === 408, $status === 425, $status === 429, $status >= 500 => SubmissionState::RetryableFailure,
            default => SubmissionState::PermanentFailure,
        };
    }
}
