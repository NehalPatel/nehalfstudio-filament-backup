<?php

namespace NehalfStudio\FilamentBackup\Commands;

use Illuminate\Console\Command;
use NehalfStudio\FilamentBackup\Jobs\RunBackupJob;
use NehalfStudio\FilamentBackup\Services\BackupRunner;
use Throwable;

class RunBackupCommand extends Command
{
    protected $signature = 'filament-backup:run
                            {type=both : database, storage, or both}
                            {--sync : Run in the current process instead of the queue}';

    protected $description = 'Create database and/or storage backups per filament-backup config.';

    public function handle(BackupRunner $runner): int
    {
        $type = strtolower((string) $this->argument('type'));
        if (! in_array($type, ['database', 'storage', 'both'], true)) {
            $this->error('Type must be database, storage, or both.');

            return self::INVALID;
        }

        if ($this->option('sync')) {
            try {
                $result = $runner->run($type);
                $this->info('Backup completed.');
                foreach ($result as $key => $name) {
                    if ($name !== null) {
                        $this->line("  {$key}: {$name}");
                    }
                }
            } catch (Throwable $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }

            return self::SUCCESS;
        }

        RunBackupJob::dispatch($type, 'cli', null);
        $this->info('Backup job queued.');

        return self::SUCCESS;
    }
}
