<?php

namespace NomadicSoft\LaravelIndexNow\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use NomadicSoft\LaravelIndexNow\Facades\IndexNow;
use NomadicSoft\LaravelIndexNow\Jobs\SubmitIndexNowUrls;
use NomadicSoft\LaravelIndexNow\Tests\TestCase;

final class IndexNowManagerTest extends TestCase
{
    public function test_it_sends_the_protocol_payload_and_accepts_202(): void
    {
        Http::fake([
            'https://api.indexnow.test/indexnow' => Http::response('', 202),
        ]);

        $submissions = IndexNow::sendNow([
            'https://example.com/articles/one',
            '/articles/two',
        ]);

        $this->assertCount(1, $submissions);
        $this->assertTrue($submissions->successful());
        $this->assertTrue($submissions->all()[0]->keyValidationPending());

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.indexnow.test/indexnow'
                && $request['host'] === 'example.com'
                && $request['key'] === 'abc12345abc12345abc12345abc12345'
                && $request['keyLocation'] === 'https://example.com/abc12345abc12345abc12345abc12345.txt'
                && $request['urlList'] === [
                    'https://example.com/articles/one',
                    'https://example.com/articles/two',
                ];
        });
    }

    public function test_it_groups_exact_hosts_and_chunks_batches(): void
    {
        config()->set('indexnow.batch_size', 2);
        $hosts = config('indexnow.hosts');
        $hosts['docs.example.com'] = [
            'key' => 'docs12345docs12345docs12345docs1',
            'key_location' => null,
            'endpoint' => 'https://api.indexnow.test/indexnow',
            'scheme' => 'https',
        ];
        config()->set('indexnow.hosts', $hosts);

        Http::fake([
            'https://api.indexnow.test/indexnow' => Http::response('', 200),
        ]);

        $submissions = IndexNow::sendNow([
            '/one',
            '/two',
            '/three',
            'https://docs.example.com/guide',
        ]);

        $this->assertCount(3, $submissions);
        Http::assertSentCount(3);

        foreach ($submissions as $submission) {
            $this->assertLessThanOrEqual(2, count($submission->urls));
            $this->assertCount(1, array_unique(array_map(
                static fn (string $url): string => (string) parse_url($url, PHP_URL_HOST),
                $submission->urls,
            )));
        }
    }

    public function test_it_skips_tracking_variants_and_duplicates(): void
    {
        Http::fake([
            'https://api.indexnow.test/indexnow' => Http::response('', 200),
        ]);

        $submissions = IndexNow::sendNow([
            '/canonical',
            '/canonical',
            '/canonical?utm_source=newsletter',
        ]);

        $this->assertCount(1, $submissions);
        $this->assertSame(['https://example.com/canonical'], $submissions->all()[0]->urls);
    }

    public function test_it_classifies_documented_response_statuses(): void
    {
        Http::fake(function (Request $request) {
            preg_match('/status-(\d+)$/', $request['urlList'][0], $matches);

            return Http::response('', (int) $matches[1]);
        });

        $cases = [
            200 => 'accepted',
            202 => 'key_validation_pending',
            400 => 'permanent_failure',
            403 => 'permanent_failure',
            422 => 'permanent_failure',
            500 => 'retryable_failure',
            503 => 'retryable_failure',
        ];

        foreach ($cases as $status => $expectedState) {
            $result = IndexNow::sendNow('/status-'.$status)->all()[0];

            $this->assertSame($expectedState, $result->state->value, 'HTTP '.$status);
        }
    }

    public function test_notify_dispatches_a_secret_free_after_commit_job(): void
    {
        config()->set('indexnow.queue.enabled', true);
        config()->set('indexnow.queue.connection', 'database');
        config()->set('indexnow.queue.name', 'indexing');
        Queue::fake();

        $report = IndexNow::notifyMany(['/one', '/two']);

        $this->assertTrue($report->queued);
        $this->assertSame(2, $report->eligibleUrls);

        Queue::assertPushed(SubmitIndexNowUrls::class, function (SubmitIndexNowUrls $job): bool {
            $serialized = serialize($job);

            return $job->host === 'example.com'
                && $job->urls === ['https://example.com/one', 'https://example.com/two']
                && $job->connection === 'database'
                && $job->queue === 'indexing'
                && $job->afterCommit === true
                && ! str_contains($serialized, 'abc12345abc12345abc12345abc12345');
        });
    }
}
