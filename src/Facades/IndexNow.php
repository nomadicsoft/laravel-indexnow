<?php

namespace NomadicSoft\LaravelIndexNow\Facades;

use Illuminate\Support\Facades\Facade;
use NomadicSoft\LaravelIndexNow\Configuration\HostConfiguration;
use NomadicSoft\LaravelIndexNow\DTO\DispatchReport;
use NomadicSoft\LaravelIndexNow\DTO\SubmissionCollection;
use NomadicSoft\LaravelIndexNow\IndexNowManager;

/**
 * @method static bool enabled()
 * @method static self filterUsing(callable(string, HostConfiguration): bool $filter)
 * @method static self flushFilters()
 * @method static DispatchReport notify(string|\Stringable $url)
 * @method static DispatchReport notifyMany(iterable $urls)
 * @method static SubmissionCollection sendNow(string|\Stringable|iterable $urls)
 *
 * @see IndexNowManager
 */
final class IndexNow extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'indexnow';
    }
}
