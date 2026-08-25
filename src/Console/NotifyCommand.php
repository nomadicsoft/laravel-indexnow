<?php

namespace NomadicSoft\LaravelIndexNow\Console;

use Illuminate\Console\Command;
use NomadicSoft\LaravelIndexNow\DTO\SubmissionCollection;
use NomadicSoft\LaravelIndexNow\IndexNowManager;

final class NotifyCommand extends Command
{
    protected $signature = 'indexnow:notify
        {urls* : Absolute URLs or paths relative to the configured default host}
        {--now : Submit in this process instead of dispatching queued jobs}';

    protected $description = 'Notify IndexNow about one or more changed URLs';

    public function handle(IndexNowManager $indexNow): int
    {
        /** @var list<string> $urls */
        $urls = $this->argument('urls');

        if ($urls === []) {
            $this->error('Provide at least one URL or relative path.');

            return self::INVALID;
        }

        if (! $indexNow->enabled()) {
            $this->warn('IndexNow is disabled. Set INDEXNOW_ENABLED=true after the key is reachable.');

            return self::FAILURE;
        }

        if ($this->option('now')) {
            return $this->renderSubmissions($indexNow->sendNow($urls));
        }

        $report = $indexNow->notifyMany($urls);
        $this->info(sprintf(
            'Queued %d eligible URL(s) in %d batch(es); skipped %d.',
            $report->eligibleUrls,
            $report->batches,
            $report->skippedUrls,
        ));

        return self::SUCCESS;
    }

    private function renderSubmissions(SubmissionCollection $submissions): int
    {
        $rows = [];

        foreach ($submissions as $result) {
            $rows[] = [
                $result->host,
                (string) $result->status,
                $result->state->value,
                (string) count($result->urls),
            ];
        }

        $this->table(['Host', 'HTTP', 'State', 'URLs'], $rows);

        return $submissions->successful() ? self::SUCCESS : self::FAILURE;
    }
}
