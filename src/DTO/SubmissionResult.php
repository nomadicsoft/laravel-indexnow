<?php

namespace NomadicSoft\LaravelIndexNow\DTO;

use JsonSerializable;
use NomadicSoft\LaravelIndexNow\Enums\SubmissionState;

final readonly class SubmissionResult implements JsonSerializable
{
    /**
     * @param  list<string>  $urls
     */
    public function __construct(
        public string $host,
        public string $endpoint,
        public array $urls,
        public int $status,
        public SubmissionState $state,
        public ?int $retryAfterSeconds = null,
        public ?string $responseBody = null,
    ) {}

    public function accepted(): bool
    {
        return in_array($this->state, [
            SubmissionState::Accepted,
            SubmissionState::KeyValidationPending,
        ], true);
    }

    public function keyValidationPending(): bool
    {
        return $this->state === SubmissionState::KeyValidationPending;
    }

    public function retryable(): bool
    {
        return $this->state === SubmissionState::RetryableFailure;
    }

    public function permanentFailure(): bool
    {
        return $this->state === SubmissionState::PermanentFailure;
    }

    public function jsonSerialize(): array
    {
        return [
            'host' => $this->host,
            'endpoint' => $this->endpoint,
            'url_count' => count($this->urls),
            'status' => $this->status,
            'state' => $this->state->value,
            'retry_after_seconds' => $this->retryAfterSeconds,
            'response_body' => $this->responseBody,
        ];
    }
}
