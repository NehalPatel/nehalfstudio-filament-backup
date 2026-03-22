<?php

namespace NehalfStudio\FilamentBackup\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;
use NehalfStudio\FilamentBackup\Filament\FilamentBackupPlugin;
use NehalfStudio\FilamentBackup\Jobs\RunBackupJob;
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
                ->label(__('filament-backup::actions.queue_database'))
                ->icon('heroicon-o-circle-stack')
                ->requiresConfirmation()
                ->action(function (): void {
                    RunBackupJob::dispatchForFilament('database', auth()->id());
                    Notification::make()
                        ->title(__('filament-backup::actions.queued_database_title'))
                        ->success()
                        ->send();
                }),
            Action::make('backupStorage')
                ->label(__('filament-backup::actions.queue_storage'))
                ->icon('heroicon-o-archive-box')
                ->requiresConfirmation()
                ->action(function (): void {
                    RunBackupJob::dispatchForFilament('storage', auth()->id());
                    Notification::make()
                        ->title(__('filament-backup::actions.queued_storage_title'))
                        ->success()
                        ->send();
                }),
            Action::make('backupBoth')
                ->label(__('filament-backup::actions.queue_full'))
                ->icon('heroicon-o-cloud-arrow-down')
                ->color('primary')
                ->requiresConfirmation()
                ->action(function (): void {
                    RunBackupJob::dispatchForFilament('both', auth()->id());
                    Notification::make()
                        ->title(__('filament-backup::actions.queued_full_title'))
                        ->success()
                        ->send();
                }),
        ];
    }
}
