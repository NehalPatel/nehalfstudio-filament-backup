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
use NehalfStudio\FilamentBackup\Services\GoogleDriveDestination;
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
     * @param  'default'|'yes'|'no'|'path'|'code'|'scroll'|'muted'|'success'|'danger'  $present
     * @return array{label: string, value: string, present: string}
     */
    protected function configItem(string $label, string $value, string $present = 'default'): array
    {
        return ['label' => $label, 'value' => $value, 'present' => $present];
    }

    /**
     * @return array<int, array{title: string, accent: string, rows: array<int, array{label: string, value: string, present: string}>}>
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
        $credPresent = 'muted';
        if (is_string($credPath) && trim($credPath) !== '') {
            if (is_file($credPath)) {
                $credMeta = GoogleDriveDestination::credentialsFileKind($credPath);
                $credTypeLabel = match ($credMeta['kind']) {
                    'service_account' => __('filament-backup::page.credentials_type_service_account'),
                    'oauth' => __('filament-backup::page.credentials_type_oauth'),
                    'invalid' => __('filament-backup::page.credentials_type_invalid'),
                    'unknown' => __('filament-backup::page.credentials_type_unknown'),
                    default => '',
                };
                $credDisplay = __('filament-backup::page.credentials_file_ok', ['file' => basename($credPath)]);
                if ($credTypeLabel !== '') {
                    $credDisplay .= ' — '.$credTypeLabel;
                }
                $credPresent = in_array($credMeta['kind'], ['invalid', 'unknown'], true) ? 'danger' : 'success';
            } else {
                $credDisplay = __('filament-backup::page.credentials_file_missing', ['file' => basename($credPath)]);
                $credPresent = 'danger';
            }
        }

        $oauthRefreshRow = null;
        if (is_string($credPath) && trim($credPath) !== '' && is_file($credPath)) {
            $kind = GoogleDriveDestination::credentialsFileKind($credPath)['kind'];
            $refreshToken = (string) config('filament-backup.google_drive.oauth_refresh_token', '');
            if ($kind === 'oauth') {
                $oauthRefreshRow = $this->configItem(
                    __('filament-backup::page.label_gdrive_refresh'),
                    trim($refreshToken) !== ''
                        ? __('filament-backup::page.refresh_token_configured')
                        : __('filament-backup::page.refresh_token_missing'),
                    trim($refreshToken) !== '' ? 'success' : 'danger',
                );
            } elseif ($kind === 'service_account') {
                $oauthRefreshRow = $this->configItem(
                    __('filament-backup::page.label_gdrive_refresh'),
                    __('filament-backup::page.refresh_token_na_service_account'),
                    'muted',
                );
            }
        }

        $excludes = config('filament-backup.storage.exclude', []);
        $excludeList = is_array($excludes) ? implode(', ', $excludes) : '';

        $bool = fn (bool $v): string => $v
            ? __('filament-backup::page.bool_yes')
            : __('filament-backup::page.bool_no');

        $boolPresent = fn (bool $v): string => $v ? 'yes' : 'no';

        $folderId = config('filament-backup.google_drive.folder_id');
        $folderDisplay = (is_string($folderId) && trim($folderId) !== '')
            ? $folderId
            : __('filament-backup::page.not_configured');
        $folderPresent = (is_string($folderId) && trim($folderId) !== '') ? 'code' : 'muted';

        $dateFolderLine = 'YYYYMMDD / '.app(LocalDestination::class)->dateFolderName();

        return [
            [
                'title' => __('filament-backup::page.section_general'),
                'accent' => 'slate',
                'rows' => [
                    $this->configItem(
                        __('filament-backup::page.label_retention'),
                        (string) (int) config('filament-backup.retention_count', 3),
                        'default'
                    ),
                    $this->configItem(
                        __('filament-backup::page.label_require_gate'),
                        $bool((bool) config('filament-backup.require_gate', false)),
                        $boolPresent((bool) config('filament-backup.require_gate', false))
                    ),
                ],
            ],
            [
                'title' => __('filament-backup::page.section_database'),
                'accent' => 'primary',
                'rows' => [
                    $this->configItem(__('filament-backup::page.label_db_connection'), $dbConnectionLabel, 'default'),
                    $this->configItem(
                        __('filament-backup::page.label_db_gzip'),
                        $bool((bool) config('filament-backup.database.gzip', true)),
                        $boolPresent((bool) config('filament-backup.database.gzip', true))
                    ),
                    $this->configItem(
                        __('filament-backup::page.label_mysqldump_path'),
                        (string) config('filament-backup.database.mysqldump_path', 'mysqldump'),
                        'path'
                    ),
                    $this->configItem(
                        __('filament-backup::page.label_db_timeout'),
                        (string) (int) config('filament-backup.database.timeout_seconds', 3600),
                        'default'
                    ),
                ],
            ],
            [
                'title' => __('filament-backup::page.section_storage'),
                'accent' => 'sky',
                'rows' => [
                    $this->configItem(__('filament-backup::page.label_storage_root'), $storageRootDisplay, 'path'),
                    $this->configItem(
                        __('filament-backup::page.label_storage_excludes'),
                        $excludeList !== '' ? $excludeList : __('filament-backup::page.not_set'),
                        $excludeList !== '' ? 'scroll' : 'muted'
                    ),
                ],
            ],
            [
                'title' => __('filament-backup::page.section_local'),
                'accent' => 'emerald',
                'rows' => [
                    $this->configItem(
                        __('filament-backup::page.label_local_enabled'),
                        $bool((bool) config('filament-backup.local.enabled', true)),
                        $boolPresent((bool) config('filament-backup.local.enabled', true))
                    ),
                    $this->configItem(__('filament-backup::page.label_local_path'), $localPath, 'path'),
                    $this->configItem(__('filament-backup::page.label_backup_date_folders'), $dateFolderLine, 'code'),
                ],
            ],
            [
                'title' => __('filament-backup::page.section_google_drive'),
                'accent' => 'amber',
                'rows' => [
                    $this->configItem(
                        __('filament-backup::page.label_gdrive_enabled'),
                        $bool((bool) config('filament-backup.google_drive.enabled', false)),
                        $boolPresent((bool) config('filament-backup.google_drive.enabled', false))
                    ),
                    $this->configItem(__('filament-backup::page.label_gdrive_credentials'), $credDisplay, $credPresent),
                    ...($oauthRefreshRow !== null ? [$oauthRefreshRow] : []),
                    $this->configItem(__('filament-backup::page.label_gdrive_folder'), $folderDisplay, $folderPresent),
                    $this->configItem(__('filament-backup::page.label_backup_date_folders'), $dateFolderLine, 'code'),
                ],
            ],
            [
                'title' => __('filament-backup::page.section_schedule'),
                'accent' => 'violet',
                'rows' => [
                    $this->configItem(
                        __('filament-backup::page.label_schedule_enabled'),
                        $bool((bool) config('filament-backup.schedule.enabled', false)),
                        $boolPresent((bool) config('filament-backup.schedule.enabled', false))
                    ),
                    $this->configItem(
                        __('filament-backup::page.label_schedule_cron'),
                        (string) config('filament-backup.schedule.expression', '0 2 * * *'),
                        'code'
                    ),
                    $this->configItem(
                        __('filament-backup::page.label_schedule_type'),
                        (string) config('filament-backup.schedule.type', 'both'),
                        'default'
                    ),
                    $this->configItem(
                        __('filament-backup::page.label_schedule_without_overlapping'),
                        $bool((bool) config('filament-backup.schedule.without_overlapping', true)),
                        $boolPresent((bool) config('filament-backup.schedule.without_overlapping', true))
                    ),
                    $this->configItem(
                        __('filament-backup::page.label_schedule_overlap_minutes'),
                        (string) (int) config('filament-backup.schedule.overlap_minutes', 120),
                        'default'
                    ),
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
