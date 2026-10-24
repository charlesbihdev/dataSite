<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Empty every storage/logs/*.log without a confirmation prompt. Files are truncated in place, not
 * deleted, so their owner and permissions stay intact for the web server and cron users.
 */
class ClearLogs extends Command
{
    protected $signature = 'log:clear';

    protected $description = 'Empty all Laravel log files in storage/logs.';

    public function handle(): int
    {
        $files = File::glob(storage_path('logs/*.log'));

        foreach ($files as $file) {
            file_put_contents($file, '');
        }

        $this->components->info(count($files).' log file(s) cleared.');

        return self::SUCCESS;
    }
}
