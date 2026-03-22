<?php

namespace NehalfStudio\FilamentBackup\Tests;

use NehalfStudio\FilamentBackup\Services\StorageArchiver;
use ZipArchive;

class StorageArchiverTest extends TestCase
{
    public function test_archives_files_respecting_excludes(): void
    {
        $root = sys_get_temp_dir().'/fb-test-'.uniqid();
        mkdir($root.'/keep', 0755, true);
        mkdir($root.'/temp', 0755, true);
        file_put_contents($root.'/keep/a.txt', 'a');
        file_put_contents($root.'/temp/b.txt', 'b');

        config()->set('filament-backup.storage.exclude', ['temp', 'temp/**']);

        $zip = $root.'/out.zip';
        $archiver = new StorageArchiver;
        $archiver->archiveToZip($root, $zip);

        $za = new ZipArchive;
        $this->assertTrue($za->open($zip) === true);
        $this->assertSame(1, $za->numFiles);
        $this->assertStringContainsString('keep/a.txt', $za->getNameIndex(0));
        $za->close();

        $this->cleanup($root);
        @unlink($zip);
    }

    protected function cleanup(string $root): void
    {
        if (! is_dir($root)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        @rmdir($root);
    }
}
