<?php

namespace NehalfStudio\FilamentBackup\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use NehalfStudio\FilamentBackup\Filament\FilamentBackupPlugin;
use NehalfStudio\FilamentBackup\Services\BackupNotifier;
use NehalfStudio\FilamentBackup\Services\BackupRunner;
use NehalfStudio\FilamentBackup\Services\LocalDestination;
use Throwable;
use UnitEnum;

class ManageBackups extends Page
{
    protected static bool $isDiscovered = false;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-server-stack';

    protected static ?string $navigationLabel = null;

    protected static string | UnitEnum | null $navigationGroup = 'System';

    protected static ?int $navigationSort = 100;

    protected static ?string $title = null;

    protected string $view = 'filament-backup::pages.manage-backups';

    public static function getNavigationLabel(): string
    {
        return static::$navigationLabel ?? __('filament-backup::page.navigation_label');
    }

    public function getTitle(): string | \Illuminate\Contracts\Support\Htmlable
    {
        if (filled(static::$title)) {
            return static::$title;
        }

        return __('filament-backup::page.title');
    }

    public static function resolvePlugin(): ?FilamentBackupPlugin
    {
        $panel = Filament::getCurrentPanel() ?? Filament::getCurrentOrDefaultPanel();
        if ($panel === null || ! $panel->hasPlugin(FilamentBackupPlugin::ID)) {
            return null;
        }

        $plugin = $panel->getPlugin(FilamentBackupPlugin::ID);

        return $plugin instanceof FilamentBackupPlugin ? $plugin : null;
    }

    public function getPollingInterval(): ?string
    {
        return static::resolvePlugin()?->getPollingInterval();
    }

    /**
     * @return array<int, array{title: string, rows: array<string, string>}>
     */
    public function getConfigSectionsProperty(): array
    {
        $defaultConnection = (string) config('database.default');
        $configuredDbConn = config('filament-backup.database.connection');
        $dbConnectionLabel = $configuredDbConn
            ? (string) $configuredDbConn
            : __('filament-backup::page.label_db_default', ['name' => $defaultConnection]);

        $storageRoot = config('filament-backup.storage.root');
        $storageRootDisplay = $storageRoot ? (string) $storageRoot : storage_path('app');

        $localPath = app(LocalDestination::class)->basePath();

        $credPath = config('filament-backup.google_drive.credentials_json');
        $credDisplay = __('filament-backup::page.not_configured');
        if (is_string($credPath) && trim($credPath) !== '') {
            $credDisplay = is_file($credPath)
                ? __('filament-backup::page.credentials_file_ok', ['file' => basename($credPath)])
                : __('filament-backup::page.credentials_file_missing', ['file' => basename($credPath)]);
        }

        $excludes = config('filament-backup.storage.exclude', []);
        $excludeList = is_array($excludes) ? implode(', ', $excludes) : '';

        $bool = fn (bool $v): string => $v
            ? __('filament-backup::page.bool_yes')
            : __('filament-backup::page.bool_no');

        $folderId = config('filament-backup.google_drive.folder_id');
        $folderDisplay = (is_string($folderId) && trim($folderId) !== '')
            ? $folderId
            : __('filament-backup::page.not_configured');

        return [
            [
                'title' => __('filament-backup::page.section_general'),
                'rows' => [
                    __('filament-backup::page.label_retention') => (string) (int) config('filament-backup.retention_count', 3),
                    __('filament-backup::page.label_require_gate') => $bool((bool) config('filament-backup.require_gate', false)),
                ],
            ],
            [
                'title' => __('filament-backup::page.section_database'),
                'rows' => [
                    __('filament-backup::page.label_db_connection') => $dbConnectionLabel,
                    __('filament-backup::page.label_db_gzip') => $bool((bool) config('filament-backup.database.gzip', true)),
                    __('filament-backup::page.label_mysqldump_path') => (string) config('filament-backup.database.mysqldump_path', 'mysqldump'),
                    __('filament-backup::page.label_db_timeout') => (string) (int) config('filament-backup.database.timeout_seconds', 3600),
                ],
            ],
            [
                'title' => __('filament-backup::page.section_storage'),
                'rows' => [
                    __('filament-backup::page.label_storage_root') => $storageRootDisplay,
                    __('filament-backup::page.label_storage_excludes') => $excludeList !== '' ? $excludeList : __('filament-backup::page.not_set'),
                ],
            ],
            [
                'title' => __('filament-backup::page.section_local'),
                'rows' => [
                    __('filament-backup::page.label_local_enabled') => $bool((bool) config('filament-backup.local.enabled', true)),
                    __('filament-backup::page.label_local_path') => $localPath,
                ],
            ],
            [
                'title' => __('filament-backup::page.section_google_drive'),
                'rows' => [
                    __('filament-backup::page.label_gdrive_enabled') => $bool((bool) config('filament-backup.google_drive.enabled', false)),
                    __('filament-backup::page.label_gdrive_credentials') => $credDisplay,
                    __('filament-backup::page.label_gdrive_folder') => $folderDisplay,
                ],
            ],
            [
                'title' => __('filament-backup::page.section_schedule'),
                'rows' => [
                    __('filament-backup::page.label_schedule_enabled') => $bool((bool) config('filament-backup.schedule.enabled', false)),
                    __('filament-backup::page.label_schedule_cron') => (string) config('filament-backup.schedule.expression', '0 2 * * *'),
                    __('filament-backup::page.label_schedule_type') => (string) config('filament-backup.schedule.type', 'both'),
                    __('filament-backup::page.label_schedule_without_overlapping') => $bool((bool) config('filament-backup.schedule.without_overlapping', true)),
                    __('filament-backup::page.label_schedule_overlap_minutes') => (string) (int) config('filament-backup.schedule.overlap_minutes', 120),
                ],
            ],
        ];
    }

    public static function canAccess(): bool
    {
        if (! auth()->check()) {
            return false;
        }

        $plugin = static::resolvePlugin();
        if ($plugin !== null && $plugin->getAuthorizeUsing() !== null) {
            return (bool) ($plugin->getAuthorizeUsing())();
        }

        if (! (bool) config('filament-backup.require_gate', false)) {
            return true;
        }

        return Gate::has('interactWithFilamentBackup')
            && Gate::allows('interactWithFilamentBackup');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backupDatabase')
                ->label(__('filament-backup::actions.backup_database'))
                ->icon('heroicon-o-circle-stack')
                ->requiresConfirmation()
                ->action(function (BackupRunner $runner, BackupNotifier $notifier): void {
                    $this->runBackupSync($runner, $notifier, 'database');
                }),
            Action::make('backupStorage')
                ->label(__('filament-backup::actions.backup_storage'))
                ->icon('heroicon-o-archive-box')
                ->requiresConfirmation()
                ->action(function (BackupRunner $runner, BackupNotifier $notifier): void {
                    $this->runBackupSync($runner, $notifier, 'storage');
                }),
            Action::make('backupBoth')
                ->label(__('filament-backup::actions.backup_full'))
                ->icon('heroicon-o-cloud-arrow-down')
                ->color('primary')
                ->requiresConfirmation()
                ->action(function (BackupRunner $runner, BackupNotifier $notifier): void {
                    $this->runBackupSync($runner, $notifier, 'both');
                }),
        ];
    }

    protected function runBackupSync(BackupRunner $runner, BackupNotifier $notifier, string $type): void
    {
        try {
            $result = $runner->run($type);
            $notifier->notifyCompleted(auth()->user(), $result);
        } catch (Throwable $e) {
            Log::error('filament-backup: '.$e->getMessage(), ['exception' => $e]);
            $notifier->notifyFailed(auth()->user(), $e);
        }
    }
}
