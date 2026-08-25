<?php

namespace NomadicSoft\LaravelIndexNow;

use Illuminate\Contracts\Bus\Dispatcher as BusDispatcher;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use NomadicSoft\LaravelIndexNow\Client\IndexNowClient;
use NomadicSoft\LaravelIndexNow\Configuration\HostConfiguration;
use NomadicSoft\LaravelIndexNow\DTO\DispatchReport;
use NomadicSoft\LaravelIndexNow\DTO\SubmissionCollection;
use NomadicSoft\LaravelIndexNow\DTO\SubmissionResult;
use NomadicSoft\LaravelIndexNow\Events\SubmissionFailed;
use NomadicSoft\LaravelIndexNow\Events\UrlsSubmitted;
use NomadicSoft\LaravelIndexNow\Exceptions\PermanentSubmissionException;
use NomadicSoft\LaravelIndexNow\Jobs\SubmitIndexNowUrls;
use NomadicSoft\LaravelIndexNow\Support\NotificationLedger;
use NomadicSoft\LaravelIndexNow\Support\UrlEligibility;
use NomadicSoft\LaravelIndexNow\Support\UrlNormalizer;
use Stringable;

final class IndexNowManager
{
    /** @var list<callable(string, HostConfiguration): bool> */
    private array $filters = [];

    public function __construct(
        private readonly Repository $config,
        private readonly BusDispatcher $bus,
        private readonly EventDispatcher $events,
        private readonly UrlNormalizer $normalizer,
        private readonly UrlEligibility $eligibility,
        private readonly NotificationLedger $ledger,
        private readonly IndexNowClient $client,
    ) {}

    public function enabled(): bool
    {
        return (bool) $this->config->get('indexnow.enabled', false);
    }

    public function filterUsing(callable $filter): self
    {
        $this->filters[] = $filter;

        return $this;
    }

    public function flushFilters(): self
    {
        $this->filters = [];

        return $this;
    }

    public function notify(string|Stringable $url): DispatchReport
    {
        return $this->notifyMany([$url]);
    }

    /** @param  iterable<array-key, string|Stringable>  $urls */
    public function notifyMany(iterable $urls): DispatchReport
    {
        if (! $this->enabled()) {
            return new DispatchReport(0, $this->count($urls), 0, false);
        }

        [$groups, $skipped] = $this->prepare($urls);
        $eligible = array_sum(array_map('count', $groups));

        if ($eligible === 0) {
            return new DispatchReport(0, $skipped, 0, false);
        }

        $batchSize = min(10000, max(1, (int) $this->config->get('indexnow.batch_size', 500)));
        $queued = (bool) $this->config->get('indexnow.queue.enabled', true);
        $batches = 0;
        $results = [];

        foreach ($groups as $host => $group) {
            foreach (array_chunk($group, $batchSize) as $urlsInBatch) {
                if ($queued) {
                    $this->dispatch($host, $urlsInBatch);
                    $batches++;

                    continue;
                }

                $claim = $this->ledger->claim($urlsInBatch);
                $skipped += count($claim['coalesced']);
                $eligible -= count($claim['coalesced']);

                if ($claim['claimed'] === []) {
                    continue;
                }

                try {
                    $result = $this->client->submit(
                        $this->normalizer->normalize($claim['claimed'][0])['host'],
                        $claim['claimed'],
                    );
                } catch (\Throwable $exception) {
                    $this->ledger->release($claim['claimed']);

                    throw $exception;
                }
                $this->recordResult($result, releaseRetryableClaims: true);
                $results[] = $result;
                $batches++;
            }
        }

        return new DispatchReport($eligible, $skipped, $batches, $queued, $results);
    }

    /**
     * @param  string|Stringable|iterable<array-key, string|Stringable>  $urls
     */
    public function sendNow(string|Stringable|iterable $urls): SubmissionCollection
    {
        if (! $this->enabled()) {
            return new SubmissionCollection([]);
        }

        $values = is_string($urls) || $urls instanceof Stringable ? [$urls] : $urls;
        [$groups] = $this->prepare($values);
        $batchSize = min(10000, max(1, (int) $this->config->get('indexnow.batch_size', 500)));
        $results = [];

        foreach ($groups as $group) {
            foreach (array_chunk($group, $batchSize) as $urlsInBatch) {
                $host = $this->normalizer->normalize($urlsInBatch[0])['host'];
                $result = $this->client->submit($host, $urlsInBatch);
                $this->recordResult($result, releaseRetryableClaims: false);
                $results[] = $result;
            }
        }

        return new SubmissionCollection($results);
    }

    /**
     * @param  iterable<array-key, string|Stringable>  $urls
     * @return array{array<string, list<string>>, int}
     */
    private function prepare(iterable $urls): array
    {
        $groups = [];
        $seen = [];
        $skipped = 0;

        foreach ($urls as $value) {
            $normalized = $this->normalizer->normalize($value);
            $url = $normalized['url'];
            $host = $normalized['host'];

            if (isset($seen[$url]) || ! $this->eligibility->allows($url) || ! $this->passesFilters($url, $host)) {
                $skipped++;

                continue;
            }

            $seen[$url] = true;
            $groups[$host->host][] = $url;
        }

        return [$groups, $skipped];
    }

    /** @param  list<string>  $urls */
    private function dispatch(string $host, array $urls): void
    {
        $job = new SubmitIndexNowUrls($host, $urls);
        $job->tries = max(1, (int) $this->config->get('indexnow.queue.tries', 5));
        $job->timeout = max(1, (int) $this->config->get('indexnow.queue.timeout', 30));
        $job->backoffSchedule = array_values(array_map(
            static fn (mixed $delay): int => max(1, (int) $delay),
            (array) $this->config->get('indexnow.queue.backoff', [30, 120, 600, 1800]),
        ));

        $connection = $this->config->get('indexnow.queue.connection');
        $queue = $this->config->get('indexnow.queue.name');

        if (is_string($connection) && $connection !== '') {
            $job->onConnection($connection);
        }

        if (is_string($queue) && $queue !== '') {
            $job->onQueue($queue);
        }

        $delay = max(0, (int) $this->config->get('indexnow.queue.delay', 5));

        if ($delay > 0) {
            $job->delay($delay);
        }

        if ($this->config->get('indexnow.queue.after_commit', true)) {
            $job->afterCommit();
        }

        $this->bus->dispatch($job);
    }

    private function passesFilters(string $url, HostConfiguration $host): bool
    {
        foreach ($this->filters as $filter) {
            if (! $filter($url, $host)) {
                return false;
            }
        }

        return true;
    }

    private function recordResult(SubmissionResult $result, bool $releaseRetryableClaims): void
    {
        if ($result->accepted()) {
            $this->events->dispatch(new UrlsSubmitted($result));

            return;
        }

        if ($result->retryable()) {
            if ($releaseRetryableClaims) {
                $this->ledger->release($result->urls);
            }

            return;
        }

        $exception = new PermanentSubmissionException($result);
        $this->events->dispatch(new SubmissionFailed(
            $result->host,
            $result->urls,
            $exception,
            $result,
        ));
    }

    /** @param  iterable<array-key, mixed>  $values */
    private function count(iterable $values): int
    {
        $count = 0;

        foreach ($values as $_) {
            $count++;
        }

        return $count;
    }
}
