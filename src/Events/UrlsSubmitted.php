<?php

namespace NomadicSoft\LaravelIndexNow\Events;

use NomadicSoft\LaravelIndexNow\DTO\SubmissionResult;

final readonly class UrlsSubmitted
{
    public function __construct(public SubmissionResult $result) {}
}
