<?php

namespace NomadicSoft\LaravelIndexNow\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use NomadicSoft\LaravelIndexNow\Support\RetryAfter;
use PHPUnit\Framework\TestCase;

final class RetryAfterTest extends TestCase
{
    public function test_it_parses_seconds_and_http_dates(): void
    {
        $now = new DateTimeImmutable('2026-08-24 10:00:00', new DateTimeZone('UTC'));

        $this->assertSame(120, RetryAfter::seconds('120', $now));
        $this->assertSame(90, RetryAfter::seconds('Mon, 24 Aug 2026 10:01:30 GMT', $now));
        $this->assertNull(RetryAfter::seconds('not-a-date', $now));
    }

    public function test_it_clamps_retry_delays(): void
    {
        $this->assertSame(1, RetryAfter::seconds('0'));
        $this->assertSame(86400, RetryAfter::seconds('999999'));
    }
}
