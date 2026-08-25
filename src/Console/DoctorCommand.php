<?php

namespace NomadicSoft\LaravelIndexNow\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Client\Factory;
use NomadicSoft\LaravelIndexNow\Configuration\HostRegistry;
use Throwable;

final class DoctorCommand extends Command
{
    protected $signature = 'indexnow:doctor
        {--remote : Fetch each public key location and verify its exact content}';

    protected $description = 'Validate the IndexNow configuration and public verification keys';

    public function handle(
        HostRegistry $hosts,
        Factory $http,
        Repository $config,
    ): int {
        $errors = 0;

        $this->components->info('IndexNow diagnostics');

        if (! $config->get('indexnow.enabled', false)) {
            $this->components->warn('IndexNow is disabled. This is safe until setup is complete.');
        } else {
            $this->components->info('IndexNow is enabled.');
        }

        $batchSize = (int) $config->get('indexnow.batch_size', 500);

        if ($batchSize < 1 || $batchSize > 10000) {
            $this->components->error('Batch size must be between 1 and 10,000.');
            $errors++;
        } else {
            $this->line(sprintf('  Batch size: %d', $batchSize));
        }

        $dedupeTtl = (int) $config->get('indexnow.dedupe.ttl', 300);

        if ($config->get('indexnow.dedupe.enabled', true) && $dedupeTtl < 300) {
            $this->components->warn('A debounce shorter than five minutes may create unnecessary duplicate submissions.');
        }

        $connection = $config->get('indexnow.queue.connection') ?: config('queue.default');
        $queue = $config->get('indexnow.queue.name', 'default');
        $this->line(sprintf(
            '  Delivery: %s via %s/%s; after-commit %s',
            $config->get('indexnow.queue.enabled', true) ? 'queued' : 'inline',
            (string) $connection,
            (string) $queue,
            $config->get('indexnow.queue.after_commit', true) ? 'enabled' : 'disabled',
        ));

        if (! $config->get('indexnow.queue.after_commit', true)) {
            $this->components->warn('After-commit dispatch is disabled; rolled-back content changes may be submitted.');
        }

        try {
            $configuredHosts = $hosts->all();
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        foreach ($configuredHosts as $host) {
            $this->newLine();
            $this->line(sprintf('  Host: %s', $host->host));
            $this->line(sprintf('  Key: configured (%d characters)', strlen($host->key)));
            $this->line(sprintf('  Key scope: %s', $host->keyScope()));

            if ($host->usesStandardRootKeyLocation()) {
                $mode = $config->get('indexnow.serve_key', true)
                    ? 'package route or static file'
                    : 'static file / application route';
                $this->line('  Verification: root key, served by '.$mode);
            } else {
                $this->components->warn(sprintf(
                    '%s uses a custom key location. Serve it in the application; the fallback route only serves root key files.',
                    $host->host,
                ));
            }

            if (! $this->option('remote')) {
                continue;
            }

            try {
                $response = $http
                    ->connectTimeout(max(1, (int) $config->get('indexnow.http.connect_timeout', 5)))
                    ->timeout(max(1, (int) $config->get('indexnow.http.timeout', 10)))
                    ->get($host->keyLocation);

                if ($response->successful() && hash_equals($host->key, trim($response->body()))) {
                    $this->components->info(sprintf('Remote key verification passed for %s.', $host->host));
                } else {
                    $this->components->error(sprintf(
                        'Remote key verification failed for %s (HTTP %d or content mismatch).',
                        $host->host,
                        $response->status(),
                    ));
                    $errors++;
                }
            } catch (Throwable) {
                $this->components->error(sprintf('Remote key verification could not reach %s.', $host->host));
                $errors++;
            }
        }

        $this->newLine();

        if ($errors > 0) {
            $this->components->error(sprintf('IndexNow diagnostics found %d error(s).', $errors));

            return self::FAILURE;
        }

        $this->components->info('IndexNow configuration is ready.');

        return self::SUCCESS;
    }
}
