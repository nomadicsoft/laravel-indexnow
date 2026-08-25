<?php

namespace NomadicSoft\LaravelIndexNow\Exceptions;

use NomadicSoft\LaravelIndexNow\DTO\SubmissionResult;

class PermanentSubmissionException extends IndexNowException
{
    public function __construct(public readonly SubmissionResult $result)
    {
        parent::__construct(sprintf(
            'IndexNow permanently rejected %d URL(s) for %s with HTTP %d.',
            count($result->urls),
            $result->host,
            $result->status,
        ));
    }
}
