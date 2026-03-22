<?php

namespace NehalfStudio\FilamentBackup\Services;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use ZipArchive;

class StorageArchiver
{
    public function archiveToZip(string $rootPath, string $zipPath): void
    {
        $rootPath = rtrim($rootPath, DIRECTORY_SEPARATOR);
        if (! is_dir($rootPath)) {
            throw new RuntimeException("Storage root is not a directory: {$rootPath}");
        }

        $excludes = config('filament-backup.storage.exclude', []);
        $excludes = is_array($excludes) ? $excludes : [];

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Could not open ZIP for writing: {$zipPath}");
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($rootPath, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $full = $file->getPathname();
            $relative = $this->relativePath($rootPath, $full);

            if ($relative === '' || $this->matchesExclude($relative, $excludes)) {
                continue;
            }

            $zip->addFile($full, str_replace('\\', '/', $relative));
        }

        if (! $zip->close()) {
            @unlink($zipPath);
            throw new RuntimeException('Failed to finalize storage ZIP archive.');
        }
    }

    protected function relativePath(string $root, string $full): string
    {
        $root = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $root);
        $full = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $full);

        if (! str_starts_with($full, $root)) {
            return '';
        }

        $rel = substr($full, strlen($root));
        $rel = ltrim($rel, DIRECTORY_SEPARATOR);

        return $rel;
    }

    /**
     * @param  array<int, string>  $patterns
     */
    protected function matchesExclude(string $relative, array $patterns): bool
    {
        $unix = str_replace('\\', '/', $relative);

        foreach ($patterns as $pattern) {
            $p = str_replace('\\', '/', trim((string) $pattern, '/'));
            if ($p === '') {
                continue;
            }

            if ($this->fnmatchSimple($p, $unix)) {
                return true;
            }

            if (str_contains($p, '**')) {
                $regex = '#^'.str_replace(
                    ['**/', '**', '*', '.'],
                    ['(?:.*/)?', '.*', '[^/]*', '\.'],
                    preg_quote(str_replace('**', "\0DOUBLESTAR\0", $p), '#')
                ).'$#i';
                $regex = str_replace("\0DOUBLESTAR\0", '.*', $regex);
                if (@preg_match($regex, $unix) === 1) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function fnmatchSimple(string $pattern, string $path): bool
    {
        if (str_contains($pattern, '*')) {
            return fnmatch($pattern, $path) || fnmatch($pattern.'/*', $path);
        }

        return $path === $pattern || str_starts_with($path, $pattern.'/');
    }
}
