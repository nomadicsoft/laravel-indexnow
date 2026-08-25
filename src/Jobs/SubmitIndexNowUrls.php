<?php

namespace NomadicSoft\LaravelIndexNow\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use NomadicSoft\LaravelIndexNow\Client\IndexNowClient;
use NomadicSoft\LaravelIndexNow\Configuration\HostRegistry;
use NomadicSoft\LaravelIndexNow\DTO\SubmissionResult;
use NomadicSoft\LaravelIndexNow\Events\SubmissionDeferred;
use NomadicSoft\LaravelIndexNow\Events\SubmissionFailed;
use NomadicSoft\LaravelIndexNow\Events\UrlsSubmitted;
use NomadicSoft\LaravelIndexNow\Exceptions\ConfigurationException;
use NomadicSoft\LaravelIndexNow\Exceptions\PermanentSubmissionException;
use NomadicSoft\LaravelIndexNow\Support\NotificationLedger;
use Throwable;

class SubmitIndexNowUrls implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public int $timeout = 30;

    /** @var list<int> */
    public array $backoffSchedule = [30, 120, 600, 1800];

    /**
     * The job stores URL strings and a hostname, never an Eloquent model or key.
     *
     * @param  list<string>  $urls
     */
    public function __construct(
        public string $host,
        public array $urls,
    ) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return $this->backoffSchedule;
    }

    public function handle(
        HostRegistry $hosts,
        IndexNowClient $client,
        NotificationLedger $ledger,
        Dispatcher $events,
    ): void {
        $claim = $ledger->claim($this->urls);

        if ($claim['claimed'] === []) {
            return;
        }

        try {
            $host = $hosts->forHost($this->host);
            $result = $client->submit($host, $claim['claimed']);
        } catch (ConfigurationException $exception) {
            $this->fail($exception);

            return;
        } catch (ConnectionException $exception) {
            $ledger->release($claim['claimed']);
            $this->deferConnectionFailure($events, $exception);

            return;
        }

        if ($result->accepted()) {
            $events->dispatch(new UrlsSubmitted($result));

            return;
        }

        if ($result->retryable()) {
            $ledger->release($claim['claimed']);
            $this->deferResult($events, $result);

            return;
        }

        $this->fail(new PermanentSubmissionException($result));
    }

    public function failed(?Throwable $exception): void
    {
        if ($exception === null) {
            return;
        }

        $result = $exception instanceof PermanentSubmissionException
            ? $exception->result
            : null;

        event(new SubmissionFailed($this->host, $this->urls, $exception, $result));
    }

    private function deferConnectionFailure(Dispatcher $events, ConnectionException $exception): void
    {
        if ($this->attempts() >= $this->tries) {
            $this->fail($exception);

            return;
        }

        $delay = $this->fallbackDelay();
        $events->dispatch(new SubmissionDeferred(
            $this->host,
            $this->urls,
            $delay,
            null,
            $exception,
        ));
        $this->release($delay);
    }

    private function deferResult(Dispatcher $events, SubmissionResult $result): void
    {
        if ($this->attempts() >= $this->tries) {
            $this->fail(new \RuntimeException(sprintf(
                'IndexNow remained unavailable after %d attempts (HTTP %d).',
                $this->tries,
                $result->status,
            )));

            return;
        }

        $delay = $result->retryAfterSeconds ?? $this->fallbackDelay();
        $events->dispatch(new SubmissionDeferred(
            $this->host,
            $result->urls,
            $delay,
            $result,
        ));
        $this->release($delay);
    }

    private function fallbackDelay(): int
    {
        $schedule = $this->backoffSchedule !== [] ? $this->backoffSchedule : [30];
        $index = min(max(0, $this->attempts() - 1), count($schedule) - 1);
        $base = max(1, (int) $schedule[$index]);
        $jitter = random_int(0, max(1, (int) floor($base * 0.2)));

        return min(86400, $base + $jitter);
    }
}
