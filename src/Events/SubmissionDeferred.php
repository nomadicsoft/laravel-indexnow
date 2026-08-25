<?php

namespace NomadicSoft\LaravelIndexNow\Events;

use NomadicSoft\LaravelIndexNow\DTO\SubmissionResult;
use Throwable;

final readonly class SubmissionDeferred
{
    /** @param  list<string>  $urls */
    public function __construct(
        public string $host,
        public array $urls,
        public int $delay,
        public ?SubmissionResult $result = null,
        public ?Throwable $exception = null,
    ) {}
}
