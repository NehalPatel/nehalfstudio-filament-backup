<x-filament-panels::page>
    <div
        class="fi-page-content-ctn flex flex-col gap-6"
        @if($this->getPollingInterval())
            wire:poll.{{ $this->getPollingInterval() }}="$refresh"
        @endif
    >
        {{-- Immediate run notice --}}
        <div
            class="rounded-xl bg-gray-50 p-4 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10 sm:p-5"
        >
            <div class="flex gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-600/10 text-primary-600 dark:bg-primary-400/10 dark:text-primary-400" aria-hidden="true">
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                    </svg>
                </div>
                <div class="min-w-0 flex-1 space-y-2 text-sm text-gray-600 dark:text-gray-400">
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                        {{ __('filament-backup::page.heading') }}
                    </h3>
                    <p class="leading-relaxed">
                        {{ __('filament-backup::page.intro') }}
                    </p>
                    <p class="leading-relaxed">
                        {{ __('filament-backup::page.actions_intro') }}
                        {{ __('filament-backup::page.google_drive_hint') }}
                    </p>
                </div>
            </div>
        </div>

        {{-- Current configuration (read-only) --}}
        <div class="space-y-4">
            <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                <h2 class="text-lg font-semibold text-gray-950 dark:text-white">
                    {{ __('filament-backup::page.config_heading') }}
                </h2>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    {{ __('filament-backup::page.config_hint') }}
                </p>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                @foreach ($this->configSections as $section)
                    <section
                        class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
                    >
                        <header
                            class="border-b border-gray-200 px-4 py-3 dark:border-white/10"
                        >
                            <h3 class="text-sm font-semibold text-gray-950 dark:text-white">
                                {{ $section['title'] }}
                            </h3>
                        </header>
                        <dl class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach ($section['rows'] as $label => $value)
                                <div class="grid gap-1 px-4 py-3 sm:grid-cols-5 sm:gap-4">
                                    <dt
                                        class="text-xs font-medium text-gray-500 dark:text-gray-400 sm:col-span-2"
                                    >
                                        {{ $label }}
                                    </dt>
                                    <dd
                                        class="break-words text-sm text-gray-950 dark:text-white sm:col-span-3"
                                    >
                                        {{ $value }}
                                    </dd>
                                </div>
                            @endforeach
                        </dl>
                    </section>
                @endforeach
            </div>
        </div>
    </div>
</x-filament-panels::page>
