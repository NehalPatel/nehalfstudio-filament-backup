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

    public function store(string $sourcePath, string $fileName): string
    {
        $dir = $this->basePath();
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new RuntimeException("Could not create local backup directory: {$dir}");
        }

        $target = $dir.DIRECTORY_SEPARATOR.$fileName;

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
            if (! str_starts_with($name, $prefix.'-')) {
                continue;
            }
            $path = $dir.DIRECTORY_SEPARATOR.$name;
            if (! is_file($path)) {
                continue;
            }
            $items[] = [
                'path' => $path,
                'mtime' => (int) filemtime($path),
            ];
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
