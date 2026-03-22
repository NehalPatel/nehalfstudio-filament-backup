<?php

namespace NehalfStudio\FilamentBackup\Services;

use Illuminate\Support\Facades\Config;
use RuntimeException;
use Symfony\Component\Process\Process;

class DatabaseDumper
{
    public function dumpToFile(string $targetPath): void
    {
        $connectionName = config('filament-backup.database.connection') ?: Config::get('database.default');
        $config = Config::get("database.connections.{$connectionName}");

        if (! is_array($config)) {
            throw new RuntimeException("Database connection [{$connectionName}] is not configured.");
        }

        $driver = $config['driver'] ?? '';

        match ($driver) {
            'mysql', 'mariadb' => $this->dumpMysql($config, $targetPath),
            'sqlite' => $this->dumpSqlite($config, $targetPath),
            default => throw new RuntimeException("Unsupported database driver [{$driver}] for backup. Use mysql, mariadb, or sqlite."),
        };
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function dumpMysql(array $config, string $targetPath): void
    {
        $binary = $this->resolveMysqldumpBinary();
        $database = (string) ($config['database'] ?? '');
        $user = (string) ($config['username'] ?? '');
        $password = (string) ($config['password'] ?? '');
        $host = (string) ($config['host'] ?? '127.0.0.1');
        $port = (string) ($config['port'] ?? '3306');

        $extra = config('filament-backup.database.mysqldump_options', []);
        $extra = is_array($extra) ? $extra : [];

        $command = array_merge(
            [$binary],
            $extra,
            [
                '--host='.$host,
                '--port='.$port,
                '--user='.$user,
                '--result-file='.$targetPath,
                $database,
            ],
        );

        $timeout = (int) config('filament-backup.database.timeout_seconds', 3600);

        $process = new Process($command, null, array_merge(
            $_ENV,
            ['MYSQL_PWD' => $password],
        ), null, $timeout);

        $process->run();

        if (! $process->isSuccessful()) {
            @unlink($targetPath);
            throw new RuntimeException('mysqldump failed: '.$process->getErrorOutput().$process->getOutput());
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function dumpSqlite(array $config, string $targetPath): void
    {
        $database = (string) ($config['database'] ?? '');

        if ($database === '' || ! is_file($database)) {
            throw new RuntimeException('SQLite database file not found: '.$database);
        }

        if (! copy($database, $targetPath)) {
            throw new RuntimeException('Failed to copy SQLite database to backup path.');
        }
    }

    /**
     * Use configured path, or on Windows try common XAMPP / PHP sibling layouts when default "mysqldump" is not on PATH.
     */
    protected function resolveMysqldumpBinary(): string
    {
        $configured = trim((string) config('filament-backup.database.mysqldump_path', 'mysqldump'));

        if ($configured !== '' && $configured !== 'mysqldump') {
            return $configured;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $phpDir = dirname(PHP_BINARY);
            $candidates = [
                'C:\\xampp\\mysql\\bin\\mysqldump.exe',
                $phpDir.'\\..\\mysql\\bin\\mysqldump.exe',
                $phpDir.'\\..\\..\\mysql\\bin\\mysqldump.exe',
            ];
            foreach ($candidates as $path) {
                $resolved = realpath($path);
                if ($resolved !== false && is_file($resolved)) {
                    return $resolved;
                }
            }
        }

        return $configured === '' ? 'mysqldump' : $configured;
    }
}
