<?php

namespace NehalfStudio\FilamentBackup\Services;

use RuntimeException;

class LocalDestination
{
    public function isEnabled(): bool
    {
        return (bool) config('filament-backup.local.enabled', true);
    }

    public function basePath(): string
    {
        $path = config('filament-backup.local.path');

        if ($path) {
            return rtrim((string) $path, DIRECTORY_SEPARATOR);
        }

        return storage_path('app/backups');
    }

    /**
     * Current backup day folder name (YYYYMMDD).
     */
    public function dateFolderName(): string
    {
        return date('Ymd');
    }

    public function store(string $sourcePath, string $fileName): string
    {
        $dir = $this->basePath();
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new RuntimeException("Could not create local backup directory: {$dir}");
        }

        $dayDir = $dir.DIRECTORY_SEPARATOR.$this->dateFolderName();
        if (! is_dir($dayDir) && ! mkdir($dayDir, 0755, true) && ! is_dir($dayDir)) {
            throw new RuntimeException("Could not create local backup day directory: {$dayDir}");
        }

        $target = $dayDir.DIRECTORY_SEPARATOR.$fileName;

        if (! copy($sourcePath, $target)) {
            throw new RuntimeException("Failed to copy backup to local path: {$target}");
        }

        return $target;
    }

    /**
     * @return array<int, array{path: string, mtime: int}>
     */
    public function listBackups(string $prefix): array
    {
        $dir = $this->basePath();
        if (! is_dir($dir)) {
            return [];
        }

        $items = [];

        foreach (scandir($dir) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }

            $path = $dir.DIRECTORY_SEPARATOR.$name;

            if (is_file($path) && str_starts_with($name, $prefix.'-')) {
                $items[] = [
                    'path' => $path,
                    'mtime' => (int) filemtime($path),
                ];

                continue;
            }

            if (is_dir($path) && preg_match('/^\d{8}$/', $name) === 1) {
                foreach (scandir($path) ?: [] as $inner) {
                    if ($inner === '.' || $inner === '..') {
                        continue;
                    }
                    if (! str_starts_with($inner, $prefix.'-')) {
                        continue;
                    }
                    $innerPath = $path.DIRECTORY_SEPARATOR.$inner;
                    if (! is_file($innerPath)) {
                        continue;
                    }
                    $items[] = [
                        'path' => $innerPath,
                        'mtime' => (int) filemtime($innerPath),
                    ];
                }
            }
        }

        usort($items, fn (array $a, array $b): int => $b['mtime'] <=> $a['mtime']);

        return $items;
    }

    public function delete(string $path): void
    {
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * Keep the newest $keepCount files matching prefix; delete the rest.
     */
    public function prune(string $prefix, int $keepCount): void
    {
        if ($keepCount < 1) {
            return;
        }

        $items = $this->listBackups($prefix);
        foreach (array_slice($items, $keepCount) as $row) {
            $this->delete($row['path']);
        }
    }
}
