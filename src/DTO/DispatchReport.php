<?php

namespace NomadicSoft\LaravelIndexNow\DTO;

use JsonSerializable;

final readonly class DispatchReport implements JsonSerializable
{
    /**
     * @param  list<SubmissionResult>  $results
     */
    public function __construct(
        public int $eligibleUrls,
        public int $skippedUrls,
        public int $batches,
        public bool $queued,
        public array $results = [],
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'eligible_urls' => $this->eligibleUrls,
            'skipped_urls' => $this->skippedUrls,
            'batches' => $this->batches,
            'queued' => $this->queued,
            'results' => $this->results,
        ];
    }
}
