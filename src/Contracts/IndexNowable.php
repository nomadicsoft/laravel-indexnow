<?php

namespace NomadicSoft\LaravelIndexNow\Contracts;

use NomadicSoft\LaravelIndexNow\Enums\IndexNowChange;

interface IndexNowable
{
    /** @return iterable<array-key, string|\Stringable> */
    public function indexNowUrls(IndexNowChange $change): iterable;

    public function shouldNotifyIndexNow(IndexNowChange $change): bool;
}
