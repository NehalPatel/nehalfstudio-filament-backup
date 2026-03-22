<x-filament-panels::page>
    <div
        class="space-y-4 text-sm text-gray-600 dark:text-gray-400"
        @if($this->getPollingInterval())
            wire:poll.{{ $this->getPollingInterval() }}="$refresh"
        @endif
    >
        <div>
            <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                {{ __('filament-backup::page.heading') }}
            </h3>
            <p class="mt-2">
                {{ __('filament-backup::page.queue_worker_line1') }}
                <code class="rounded bg-gray-100 px-1 py-0.5 text-xs dark:bg-white/10">php artisan queue:work</code>.
                {{ __('filament-backup::page.queue_worker_line2') }}
                <code class="rounded bg-gray-100 px-1 py-0.5 text-xs dark:bg-white/10">config/filament-backup.php</code>
                {{ __('filament-backup::page.queue_worker_line3') }}
            </p>
        </div>
        <p>
            {{ __('filament-backup::page.actions_intro') }}
            {{ __('filament-backup::page.google_drive_hint') }}
        </p>
    </div>
</x-filament-panels::page>
