<?php

namespace NehalfStudio\FilamentBackup;

use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\HtmlString;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use NehalfStudio\FilamentBackup\Commands\GoogleDriveAuthCommand;
use NehalfStudio\FilamentBackup\Commands\RunBackupCommand;
use NehalfStudio\FilamentBackup\Filament\Pages\ManageBackups;
use NehalfStudio\FilamentBackup\Services\BackupRunner;
use Throwable;

class FilamentBackupServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            dirname(__DIR__).'/config/filament-backup.php',
            'filament-backup'
        );

        $this->app->singleton(Services\BackupRunner::class);
        $this->app->singleton(Services\BackupNotifier::class);

        FilamentAsset::register([
            Css::make(
                'manage-backups-page',
                dirname(__DIR__).'/dist/manage-backups-page.css',
            )->loadedOnRequest(),
        ], package: 'nehalfstudio-filament-backup');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(dirname(__DIR__).'/resources/views', 'filament-backup');

        $langPath = is_dir(lang_path('vendor/filament-backup'))
            ? lang_path('vendor/filament-backup')
            : dirname(__DIR__).'/lang';
        $this->loadTranslationsFrom($langPath, 'filament-backup');

        $this->publishes([
            dirname(__DIR__).'/config/filament-backup.php' => config_path('filament-backup.php'),
        ], 'filament-backup-config');

        $this->publishes([
            dirname(__DIR__).'/lang' => lang_path('vendor/filament-backup'),
        ], 'filament-backup-translations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                RunBackupCommand::class,
                GoogleDriveAuthCommand::class,
            ]);
        }

        $this->registerManageBackupsStyles();

        $this->registerSchedule();
    }

    protected function registerManageBackupsStyles(): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::STYLES_AFTER,
            function (): HtmlString|string {
                $component = Livewire::current();
                if (! $component instanceof ManageBackups) {
                    return '';
                }

                try {
                    $href = FilamentAsset::getStyleHref('manage-backups-page', 'nehalfstudio-filament-backup');
                } catch (Throwable) {
                    return '';
                }

                return new HtmlString(
                    '<link href="'.e($href).'" rel="stylesheet" data-navigate-track />'
                );
            },
        );
    }

    protected function registerSchedule(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->app->booted(function (): void {
            if (! config('filament-backup.schedule.enabled')) {
                return;
            }

            $type = (string) config('filament-backup.schedule.type', 'both');

            $event = Schedule::call(function () use ($type): void {
                try {
                    app(BackupRunner::class)->run($type);
                } catch (Throwable $e) {
                    Log::error('filament-backup scheduled backup failed: '.$e->getMessage(), ['exception' => $e]);
                }
            })->cron((string) config('filament-backup.schedule.expression', '0 2 * * *'));

            if (config('filament-backup.schedule.without_overlapping')) {
                $event->withoutOverlapping(
                    (int) config('filament-backup.schedule.overlap_minutes', 120)
                );
            }
        });
    }
}
