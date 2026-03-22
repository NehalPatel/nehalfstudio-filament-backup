<x-filament-panels::page>
    <div
        class="fi-page-content-ctn flex flex-col gap-8"
        @if($this->getPollingInterval())
            wire:poll.{{ $this->getPollingInterval() }}="$refresh"
        @endif
    >
        {{-- Intro card --}}
        <div
            class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-white to-gray-50 p-6 shadow-md ring-1 ring-gray-950/5 dark:from-gray-900 dark:to-gray-950 dark:ring-white/10 sm:p-8"
        >
            <div
                class="pointer-events-none absolute -right-8 -top-8 h-32 w-32 rounded-full bg-primary-500/10 dark:bg-primary-400/10"
                aria-hidden="true"
            ></div>
            <div class="relative flex flex-col gap-4 sm:flex-row sm:items-start sm:gap-6">
                <div
                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary-600 text-white shadow-lg shadow-primary-600/25 dark:bg-primary-500 dark:shadow-primary-500/20"
                    aria-hidden="true"
                >
                    <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                    </svg>
                </div>
                <div class="min-w-0 flex-1 space-y-3">
                    <h3 class="text-lg font-bold tracking-tight text-gray-950 dark:text-white">
                        {{ __('filament-backup::page.heading') }}
                    </h3>
                    <p class="text-sm leading-relaxed text-gray-600 dark:text-gray-300">
                        {{ __('filament-backup::page.intro') }}
                    </p>
                    <p class="text-sm leading-relaxed text-gray-600 dark:text-gray-300">
                        <span class="font-medium text-gray-950 dark:text-white">{{ __('filament-backup::page.actions_intro') }}</span>
                        {{ __('filament-backup::page.google_drive_hint') }}
                    </p>
                </div>
            </div>
        </div>

        {{-- Configuration cards --}}
        <div class="space-y-6">
            <div class="flex flex-col gap-2 border-b border-gray-200 pb-4 dark:border-white/10 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-xl font-bold tracking-tight text-gray-950 dark:text-white">
                        {{ __('filament-backup::page.config_heading') }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ __('filament-backup::page.config_subheading') }}
                    </p>
                </div>
                <p class="max-w-xl text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                    {{ __('filament-backup::page.config_hint') }}
                </p>
            </div>

            <div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($this->configSections as $section)
                    @php
                        $accentBar = match ($section['accent'] ?? 'slate') {
                            'primary' => 'border-l-primary-600 dark:border-l-primary-500',
                            'sky' => 'border-l-sky-500 dark:border-l-sky-400',
                            'emerald' => 'border-l-emerald-500 dark:border-l-emerald-400',
                            'amber' => 'border-l-amber-500 dark:border-l-amber-400',
                            'violet' => 'border-l-violet-500 dark:border-l-violet-400',
                            default => 'border-l-slate-400 dark:border-l-slate-500',
                        };
                    @endphp
                    <article
                        class="flex flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900 {{ $accentBar }} border-l-4"
                    >
                        <header class="border-b border-gray-100 bg-gray-50/80 px-5 py-4 dark:border-white/5 dark:bg-white/5">
                            <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                                {{ $section['title'] }}
                            </h3>
                        </header>
                        <div class="grid flex-1 gap-3 p-4 sm:grid-cols-1">
                            @foreach ($section['rows'] as $row)
                                @php
                                    $present = $row['present'] ?? 'default';
                                @endphp
                                <div
                                    class="rounded-xl border border-gray-100 bg-gray-50/50 p-3.5 dark:border-white/5 dark:bg-white/[0.03]"
                                >
                                    <div
                                        class="mb-1.5 text-[0.65rem] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400"
                                    >
                                        {{ $row['label'] }}
                                    </div>
                                    <div class="min-h-[1.25rem] break-words">
                                        @switch($present)
                                            @case('yes')
                                                <span
                                                    class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-800 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-400/10 dark:text-emerald-300 dark:ring-emerald-400/30"
                                                >
                                                    {{ $row['value'] }}
                                                </span>
                                                @break
                                            @case('no')
                                                <span
                                                    class="inline-flex items-center rounded-md bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-700 ring-1 ring-inset ring-gray-500/10 dark:bg-white/10 dark:text-gray-300 dark:ring-white/20"
                                                >
                                                    {{ $row['value'] }}
                                                </span>
                                                @break
                                            @case('path')
                                                <code
                                                    class="block text-xs font-medium leading-relaxed text-gray-900 dark:text-gray-100"
                                                >{{ $row['value'] }}</code>
                                                @break
                                            @case('code')
                                                <code
                                                    class="block rounded-md bg-gray-900/5 px-2 py-1.5 text-xs font-mono font-medium text-gray-900 dark:bg-white/10 dark:text-gray-100"
                                                >{{ $row['value'] }}</code>
                                                @break
                                            @case('scroll')
                                                <div
                                                    class="max-h-28 overflow-y-auto rounded-md bg-gray-900/[0.04] px-2 py-1.5 text-xs leading-relaxed text-gray-800 dark:bg-white/5 dark:text-gray-200"
                                                >
                                                    {{ $row['value'] }}
                                                </div>
                                                @break
                                            @case('muted')
                                                <span class="text-sm italic text-gray-400 dark:text-gray-500">{{ $row['value'] }}</span>
                                                @break
                                            @case('success')
                                                <span class="text-sm font-medium text-emerald-700 dark:text-emerald-400">{{ $row['value'] }}</span>
                                                @break
                                            @case('danger')
                                                <span class="text-sm font-medium text-red-700 dark:text-red-400">{{ $row['value'] }}</span>
                                                @break
                                            @default
                                                <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ $row['value'] }}</span>
                                        @endswitch
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </div>
</x-filament-panels::page>
