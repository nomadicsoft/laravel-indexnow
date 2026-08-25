<?php

namespace NomadicSoft\LaravelIndexNow\Tests\Feature;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Http;
use NomadicSoft\LaravelIndexNow\Client\IndexNowClient;
use NomadicSoft\LaravelIndexNow\Configuration\HostRegistry;
use NomadicSoft\LaravelIndexNow\Exceptions\PermanentSubmissionException;
use NomadicSoft\LaravelIndexNow\Jobs\SubmitIndexNowUrls;
use NomadicSoft\LaravelIndexNow\Support\NotificationLedger;
use NomadicSoft\LaravelIndexNow\Tests\TestCase;

final class QueuedSubmissionTest extends TestCase
{
    public function test_overlapping_jobs_are_debounced_per_url(): void
    {
        Http::fake([
            'https://api.indexnow.test/indexnow' => Http::response('', 200),
        ]);

        $this->runJob(new SubmitIndexNowUrls('example.com', [
            'https://example.com/one',
            'https://example.com/two',
        ]));
        $this->runJob(new SubmitIndexNowUrls('example.com', [
            'https://example.com/two',
            'https://example.com/three',
        ]));

        Http::assertSentCount(2);
        Http::assertSent(fn ($request): bool => $request['urlList'] === ['https://example.com/three']);
    }

    public function test_429_honors_retry_after_and_releases_debounce_claims(): void
    {
        Http::fakeSequence()
            ->push('', 429, ['Retry-After' => '120'])
            ->push('', 200);

        $job = (new SubmitIndexNowUrls('example.com', ['https://example.com/rate-limited']))
            ->withFakeQueueInteractions();
        $this->runJob($job);
        $job->assertReleased(120);

        $this->runJob(new SubmitIndexNowUrls('example.com', ['https://example.com/rate-limited']));
        Http::assertSentCount(2);
    }

    public function test_permanent_responses_fail_without_release(): void
    {
        Http::fake([
            'https://api.indexnow.test/indexnow' => Http::response('', 403),
        ]);

        $job = (new SubmitIndexNowUrls('example.com', ['https://example.com/forbidden']))
            ->withFakeQueueInteractions();

        $this->runJob($job);

        $job->assertFailedWith(PermanentSubmissionException::class);
        $job->assertNotReleased();
    }

    private function runJob(SubmitIndexNowUrls $job): void
    {
        $job->handle(
            app(HostRegistry::class),
            app(IndexNowClient::class),
            app(NotificationLedger::class),
            app(Dispatcher::class),
        );
    }
}
