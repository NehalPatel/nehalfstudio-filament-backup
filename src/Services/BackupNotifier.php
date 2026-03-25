<?php

namespace NehalfStudio\FilamentBackup\Services;

use Filament\Notifications\Notification;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;
use Throwable;

class BackupNotifier
{
    /**
     * @param  array{database: ?string, storage: ?string}  $result
     */
    public function notifyCompleted(?Authenticatable $user, array $result, bool $broadcastSessionToast = true): void
    {
        $parts = array_filter($result);
        $body = $parts === []
            ? __('filament-backup::notifications.completed_body')
            : __('filament-backup::notifications.completed_with_files', ['files' => implode(', ', $parts)]);

        if ($broadcastSessionToast) {
            Notification::make()
                ->title(__('filament-backup::notifications.completed_title'))
                ->body($body)
                ->success()
                ->send();
        }

        $this->sendDatabaseSuccess($user, $body);
    }

    public function notifyFailed(?Authenticatable $user, Throwable $e, bool $broadcastSessionToast = true): void
    {
        if ($broadcastSessionToast) {
            Notification::make()
                ->title(__('filament-backup::notifications.failed_title'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }

        $this->sendDatabaseFailure($user, $e);
    }

    protected function sendDatabaseSuccess(?Authenticatable $user, string $body): void
    {
        if (! $user) {
            return;
        }

        try {
            Notification::make()
                ->title(__('filament-backup::notifications.completed_title'))
                ->body($body)
                ->success()
                ->sendToDatabase($user);
        } catch (Throwable $e) {
            Log::warning('filament-backup: could not send database notification (run notifications migration or check logs). '.$e->getMessage());
        }
    }

    protected function sendDatabaseFailure(?Authenticatable $user, Throwable $e): void
    {
        if (! $user) {
            return;
        }

        try {
            Notification::make()
                ->title(__('filament-backup::notifications.failed_title'))
                ->body($e->getMessage())
                ->danger()
                ->sendToDatabase($user);
        } catch (Throwable $notifyException) {
            Log::warning('filament-backup: could not send database notification (run notifications migration or check logs). '.$notifyException->getMessage());
        }
    }
}
