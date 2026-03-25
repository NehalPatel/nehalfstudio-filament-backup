<?php

namespace NehalfStudio\FilamentBackup\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use NehalfStudio\FilamentBackup\Services\BackupNotifier;
use NehalfStudio\FilamentBackup\Services\BackupRunner;
use Throwable;

class RunFilamentBackupJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout;

    public function __construct(
        public string $type,
        public ?int $userId = null,
    ) {
        $this->timeout = max(60, (int) config('filament-backup.queue_timeout_seconds', 7200));
    }

    public function handle(BackupRunner $runner, BackupNotifier $notifier): void
    {
        $user = $this->resolveUser();

        try {
            $result = $runner->run($this->type);
            $notifier->notifyCompleted($user, $result, broadcastSessionToast: false);
        } catch (Throwable $e) {
            Log::error('filament-backup: '.$e->getMessage(), ['exception' => $e]);
            $notifier->notifyFailed($user, $e, broadcastSessionToast: false);
            throw $e;
        }
    }

    protected function resolveUser(): ?Authenticatable
    {
        if ($this->userId === null) {
            return null;
        }

        $guardName = (string) config('auth.defaults.guard', 'web');
        $provider = Auth::guard($guardName)->getProvider();
        $user = $provider->retrieveById($this->userId);

        return $user instanceof Authenticatable ? $user : null;
    }
}
