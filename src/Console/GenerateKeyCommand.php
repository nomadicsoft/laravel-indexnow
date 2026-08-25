<?php

namespace NomadicSoft\LaravelIndexNow\Console;

use Illuminate\Console\Command;
use RuntimeException;

final class GenerateKeyCommand extends Command
{
    protected $signature = 'indexnow:generate-key
        {--length=32 : Key length from 8 to 128 characters}
        {--write : Atomically write the key to public/{key}.txt}
        {--force : Replace an existing file when using --write}';

    protected $description = 'Generate an IndexNow verification key';

    public function handle(): int
    {
        $length = (int) $this->option('length');

        if ($length < 8 || $length > 128) {
            $this->error('Key length must be between 8 and 128.');

            return self::FAILURE;
        }

        $key = substr(bin2hex(random_bytes((int) ceil($length / 2))), 0, $length);

        $this->newLine();
        $this->line('INDEXNOW_KEY='.$key);

        if (! $this->option('write')) {
            $this->newLine();
            $this->info('Set this value in the deployment environment, then run indexnow:doctor --remote.');

            return self::SUCCESS;
        }

        try {
            $path = $this->writeAtomically($key);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Verification file written to '.$path);

        return self::SUCCESS;
    }

    private function writeAtomically(string $key): string
    {
        $path = public_path($key.'.txt');

        if (is_file($path) && ! $this->option('force')) {
            throw new RuntimeException('The verification file already exists. Re-run with --force to replace it.');
        }

        $temporary = $path.'.tmp.'.bin2hex(random_bytes(4));
        $written = file_put_contents($temporary, $key, LOCK_EX);

        if ($written !== strlen($key)) {
            @unlink($temporary);

            throw new RuntimeException('Could not write the verification file.');
        }

        @chmod($temporary, 0644);

        if (! @rename($temporary, $path)) {
            @unlink($temporary);

            throw new RuntimeException('Could not move the verification file into place.');
        }

        return $path;
    }
}
