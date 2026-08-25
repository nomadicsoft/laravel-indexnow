<?php

namespace NomadicSoft\LaravelIndexNow\Enums;

enum SubmissionState: string
{
    case Accepted = 'accepted';
    case KeyValidationPending = 'key_validation_pending';
    case RetryableFailure = 'retryable_failure';
    case PermanentFailure = 'permanent_failure';
}
