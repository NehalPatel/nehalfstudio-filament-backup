<?php

namespace NehalfStudio\FilamentBackup\Tests;

use NehalfStudio\FilamentBackup\FilamentBackupServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            FilamentBackupServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('filament-backup.local.enabled', true);
        $app['config']->set('filament-backup.google_drive.enabled', false);
    }
}
