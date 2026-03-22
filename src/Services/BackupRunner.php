<?php

namespace NehalfStudio\FilamentBackup\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

class BackupRunner
{
    public function __construct(
        protected DatabaseDumper $databaseDumper,
        protected StorageArchiver $storageArchiver,
        protected LocalDestination $localDestination,
        protected GoogleDriveDestination $googleDriveDestination,
    ) {}

    /**
     * @return array{database: ?string, storage: ?string}
     *
     * @throws Throwable
     */
    public function run(string $type): array
    {
        $type = strtolower($type);
        if (! in_array($type, ['database', 'storage', 'both'], true)) {
            throw new RuntimeException("Invalid backup type [{$type}]. Use database, storage, or both.");
        }

        $runDb = $type === 'database' || $type === 'both';
        $runStorage = $type === 'storage' || $type === 'both';

        if (! $this->localDestination->isEnabled() && ! $this->googleDriveDestination->isEnabled()) {
            throw new RuntimeException('No backup destinations are enabled. Enable local and/or Google Drive in config.');
        }

        $tempDir = storage_path('app/temp/filament-backup');
        File::ensureDirectoryExists($tempDir);

        $timestamp = date('YmdHis');
        $dateFolder = date('Ymd');
        $artifacts = ['database' => null, 'storage' => null];

        $dbFileName = null;
        $storageFileName = null;

        try {
            if ($runDb) {
                $artifacts['database'] = $this->createDatabaseArtifact($tempDir, $timestamp, $dbFileName);
            }

            if ($runStorage) {
                $artifacts['storage'] = $this->createStorageArtifact($tempDir, $timestamp, $storageFileName);
            }

            if ($artifacts['database'] !== null && $dbFileName !== null) {
                $this->distributeArtifact($artifacts['database'], 'db', $dbFileName);
            }

            if ($artifacts['storage'] !== null && $storageFileName !== null) {
                $this->distributeArtifact($artifacts['storage'], 'storage', $storageFileName);
            }
        } finally {
            foreach ($artifacts as $path) {
                if ($path !== null && is_file($path)) {
                    @unlink($path);
                }
            }
        }

        return [
            'database' => $dbFileName !== null ? "{$dateFolder}/{$dbFileName}" : null,
            'storage' => $storageFileName !== null ? "{$dateFolder}/{$storageFileName}" : null,
        ];
    }

    protected function createDatabaseArtifact(string $tempDir, string $timestamp, ?string &$finalName): string
    {
        $connectionName = config('filament-backup.database.connection') ?: config('database.default');
        $driver = config("database.connections.{$connectionName}.driver");
        $gzip = (bool) config('filament-backup.database.gzip', true);

        if (in_array($driver, ['sqlite'], true)) {
            $plain = $tempDir.DIRECTORY_SEPARATOR."db-{$timestamp}.sqlite";
            $this->databaseDumper->dumpToFile($plain);
            if ($gzip) {
                $this->gzipFile($plain, $plain.'.gz');
                @unlink($plain);
                $finalName = "db-{$timestamp}.sqlite.gz";

                return $plain.'.gz';
            }
            $finalName = "db-{$timestamp}.sqlite";

            return $plain;
        }

        $plain = $tempDir.DIRECTORY_SEPARATOR."db-{$timestamp}.sql";
        $this->databaseDumper->dumpToFile($plain);

        if ($gzip) {
            $this->gzipFile($plain, $plain.'.gz');
            @unlink($plain);
            $finalName = "db-{$timestamp}.sql.gz";

            return $plain.'.gz';
        }

        $finalName = "db-{$timestamp}.sql";

        return $plain;
    }

    protected function createStorageArtifact(string $tempDir, string $timestamp, ?string &$finalName): string
    {
        $root = config('filament-backup.storage.root') ?: storage_path('app');
        $root = (string) $root;
        $zipPath = $tempDir.DIRECTORY_SEPARATOR."storage-{$timestamp}.zip";
        $this->storageArchiver->archiveToZip($root, $zipPath);
        $finalName = "storage-{$timestamp}.zip";

        return $zipPath;
    }

    protected function gzipFile(string $source, string $target): void
    {
        $input = fopen($source, 'rb');
        if ($input === false) {
            throw new RuntimeException("Cannot read {$source} for gzip.");
        }
        $output = gzopen($target, 'wb9');
        if ($output === false) {
            fclose($input);
            throw new RuntimeException("Cannot write {$target} for gzip.");
        }

        while (! feof($input)) {
            gzwrite($output, (string) fread($input, 1024 * 512));
        }

        gzclose($output);
        fclose($input);
    }

    protected function distributeArtifact(string $tempPath, string $prefix, string $fileName): void
    {
        $keep = max(1, (int) config('filament-backup.retention_count', 3));

        if ($this->localDestination->isEnabled()) {
            $this->localDestination->store($tempPath, $fileName);
            $this->localDestination->prune($prefix, $keep);
        }

        if ($this->googleDriveDestination->isEnabled()) {
            $this->googleDriveDestination->store($tempPath, $fileName);
            $this->googleDriveDestination->prune($prefix, $keep);
        }
    }
}
