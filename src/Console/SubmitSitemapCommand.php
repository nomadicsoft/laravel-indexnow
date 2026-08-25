<?php

namespace NomadicSoft\LaravelIndexNow\Console;

use Illuminate\Console\Command;
use NomadicSoft\LaravelIndexNow\IndexNowManager;
use NomadicSoft\LaravelIndexNow\Support\SitemapReader;
use Throwable;

final class SubmitSitemapCommand extends Command
{
    protected $signature = 'indexnow:submit-sitemap
        {url? : Sitemap or sitemap-index URL; defaults to APP_URL/sitemap.xml}
        {--limit=0 : Maximum number of page URLs to read; zero means unlimited}
        {--now : Submit in this process instead of dispatching queued jobs}';

    protected $description = 'Backfill IndexNow notifications from a sitemap';

    public function handle(SitemapReader $reader, IndexNowManager $indexNow): int
    {
        if (! $indexNow->enabled()) {
            $this->warn('IndexNow is disabled. Set INDEXNOW_ENABLED=true after the key is reachable.');

            return self::FAILURE;
        }

        $url = (string) ($this->argument('url') ?: rtrim((string) config('app.url'), '/').'/sitemap.xml');
        $limit = max(0, (int) $this->option('limit'));

        try {
            $urls = $reader->read($url, $limit);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($urls === []) {
            $this->warn('The sitemap contained no page URLs.');

            return self::SUCCESS;
        }

        if ($this->option('now')) {
            $results = $indexNow->sendNow($urls);
            $this->info(sprintf(
                'Submitted %d sitemap URL(s) in %d request(s); %d request(s) failed.',
                count($urls),
                count($results),
                $results->failedCount(),
            ));

            return $results->successful() ? self::SUCCESS : self::FAILURE;
        }

        $report = $indexNow->notifyMany($urls);
        $this->info(sprintf(
            'Queued %d sitemap URL(s) in %d batch(es); skipped %d.',
            $report->eligibleUrls,
            $report->batches,
            $report->skippedUrls,
        ));

        return self::SUCCESS;
    }
}
