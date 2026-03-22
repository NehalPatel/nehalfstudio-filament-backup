<?php

namespace NehalfStudio\FilamentBackup\Commands;

use Illuminate\Console\Command;
use NehalfStudio\FilamentBackup\Services\BackupRunner;
use Throwable;

class RunBackupCommand extends Command
{
    protected $signature = 'filament-backup:run {type=both : database, storage, or both}';

    protected $description = 'Create database and/or storage backups synchronously in the current process.';

    public function handle(BackupRunner $runner): int
    {
        $type = strtolower((string) $this->argument('type'));
        if (! in_array($type, ['database', 'storage', 'both'], true)) {
            $this->error('Type must be database, storage, or both.');

            return self::INVALID;
        }

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
}
