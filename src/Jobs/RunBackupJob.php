<?php

namespace NehalfStudio\FilamentBackup\Jobs;

use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use NehalfStudio\FilamentBackup\Filament\FilamentBackupPlugin;
use NehalfStudio\FilamentBackup\Services\BackupRunner;
use Throwable;

class RunBackupJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout;

    public function __construct(
        public string $type = 'both',
        public string $trigger = 'manual',
        public ?int $userId = null,
        public ?string $queueNameOverride = null,
        public ?string $queueConnectionOverride = null,
        public ?int $timeoutSecondsOverride = null,
        public bool $withoutTimeLimit = false,
    ) {
        if ($this->withoutTimeLimit) {
            $this->timeout = 86400 * 7;
        } elseif ($this->timeoutSecondsOverride !== null) {
            $this->timeout = max(60, $this->timeoutSecondsOverride);
        } else {
            $this->timeout = (int) config('filament-backup.database.timeout_seconds', 3600) + 600;
        }

        $connection = $this->queueConnectionOverride ?? config('filament-backup.queue.connection');
        $queue = $this->queueNameOverride ?? config('filament-backup.queue.queue', 'default');
        if (is_string($connection) && $connection !== '') {
            $this->onConnection($connection);
        }
        if (is_string($queue) && $queue !== '') {
            $this->onQueue($queue);
        }
    }

    public static function forSchedule(string $type): self
    {
        return new self(type: $type, trigger: 'schedule', userId: null);
    }

    /**
     * Dispatch using queue/timeout overrides from {@see FilamentBackupPlugin} when the current panel has it registered.
     */
    /**
     * @return \Illuminate\Foundation\Bus\PendingDispatch
     */
    public static function dispatchForFilament(string $type, ?int $userId)
    {
        $panel = Filament::getCurrentPanel() ?? Filament::getCurrentOrDefaultPanel();

        $queueName = null;
        $queueConn = null;
        $timeout = null;
        $noLimit = false;

        if ($panel && $panel->hasPlugin(FilamentBackupPlugin::ID)) {
            $plugin = $panel->getPlugin(FilamentBackupPlugin::ID);
            if ($plugin instanceof FilamentBackupPlugin) {
                $queueName = $plugin->getQueueName();
                $queueConn = $plugin->getQueueConnection();
                if ($plugin->isJobWithoutTimeLimit()) {
                    $noLimit = true;
                } else {
                    $timeout = $plugin->getJobTimeoutSeconds();
                }
            }
        }

        return dispatch(new self(
            type: $type,
            trigger: 'filament',
            userId: $userId,
            queueNameOverride: $queueName,
            queueConnectionOverride: $queueConn,
            timeoutSecondsOverride: $timeout,
            withoutTimeLimit: $noLimit,
        ));
    }

    public function handle(BackupRunner $runner): void
    {
        try {
            $result = $runner->run($this->type);
            $this->notifySuccess($result);
        } catch (Throwable $e) {
            $this->notifyFailure($e);
            Log::error('filament-backup: '.$e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }

    /**
     * @param  array{database: ?string, storage: ?string}  $result
     */
    protected function notifySuccess(array $result): void
    {
        $user = $this->resolveUser();
        if (! $user) {
            return;
        }

        $parts = array_filter($result);
        $body = $parts === [] ? __('filament-backup::notifications.completed_body') : __('filament-backup::notifications.completed_with_files', ['files' => implode(', ', $parts)]);

        Notification::make()
            ->title(__('filament-backup::notifications.completed_title'))
            ->body($body)
            ->success()
            ->sendToDatabase($user);
    }

    protected function notifyFailure(Throwable $e): void
    {
        $user = $this->resolveUser();
        if (! $user) {
            return;
        }

        Notification::make()
            ->title(__('filament-backup::notifications.failed_title'))
            ->body($e->getMessage())
            ->danger()
            ->sendToDatabase($user);
    }

    protected function resolveUser(): ?Authenticatable
    {
        if ($this->userId === null) {
            return null;
        }

        $model = config('auth.providers.users.model');
        if (! is_string($model) || ! class_exists($model) || ! is_subclass_of($model, Model::class)) {
            return null;
        }

        /** @var class-string<Model&Authenticatable> $model */
        $user = $model::query()->find($this->userId);

        return $user instanceof Authenticatable ? $user : null;
    }
}
