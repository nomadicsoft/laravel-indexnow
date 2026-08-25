<?php

namespace NomadicSoft\LaravelIndexNow\Events;

use NomadicSoft\LaravelIndexNow\DTO\SubmissionResult;
use Throwable;

final readonly class SubmissionFailed
{
    /** @param  list<string>  $urls */
    public function __construct(
        public string $host,
        public array $urls,
        public Throwable $exception,
        public ?SubmissionResult $result = null,
    ) {}
}
