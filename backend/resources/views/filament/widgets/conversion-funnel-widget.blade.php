<x-filament::section
    :heading="__('Conversion funnel report')"
    :description="__('Drop-off visibility for customer journey and operations pipeline.')"
>
    <div class="mb-4 flex items-center justify-between gap-3">
        <p class="text-xs text-gray-500 dark:text-gray-400">
            {{ __('Window: last :days days', ['days' => $windowDays]) }}
        </p>
        <div class="w-44">
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="windowDays">
                    <option value="7">{{ __('Last 7 days') }}</option>
                    <option value="30">{{ __('Last 30 days') }}</option>
                    <option value="90">{{ __('Last 90 days') }}</option>
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-xl border border-gray-200 p-3 dark:border-white/10">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-primary-600 dark:text-primary-400">
                {{ __('Customer Journey Funnel') }}
            </p>
            <div class="space-y-2">
                @php
                    $maxCustomer = max(1, ...array_map(fn ($s) => (int) ($s['count'] ?? 0), $customerStages));
                @endphp
                @foreach ($customerStages as $stage)
                    @php
                        $count = (int) ($stage['count'] ?? 0);
                        $width = max(8, (int) round(($count / $maxCustomer) * 100));
                        $drop = $stage['drop_off_percent'];
                    @endphp
                    <div class="rounded-lg border border-gray-200/70 p-2 dark:border-white/10">
                        <div class="mb-1 flex items-center justify-between text-xs">
                            <span class="text-gray-700 dark:text-gray-200">{{ $stage['label'] }}</span>
                            <span class="font-semibold text-primary-600 dark:text-primary-400">{{ number_format($count) }}</span>
                        </div>
                        <div class="h-2 rounded-full bg-gray-200/70 dark:bg-white/10">
                            <div class="h-2 rounded-full bg-primary-500" style="width: {{ $width }}%"></div>
                        </div>
                        @if ($drop !== null)
                            <p class="mt-1 text-[11px] text-rose-600 dark:text-rose-400">
                                {{ __('Drop-off: :rate%', ['rate' => number_format((float) $drop, 1)]) }}
                            </p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 p-3 dark:border-white/10">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-primary-600 dark:text-primary-400">
                {{ __('Operations Funnel') }}
            </p>
            <div class="space-y-2">
                @php
                    $maxOps = max(1, ...array_map(fn ($s) => (int) ($s['count'] ?? 0), $operationsStages));
                @endphp
                @foreach ($operationsStages as $stage)
                    @php
                        $count = (int) ($stage['count'] ?? 0);
                        $width = max(8, (int) round(($count / $maxOps) * 100));
                        $drop = $stage['drop_off_percent'];
                    @endphp
                    <div class="rounded-lg border border-gray-200/70 p-2 dark:border-white/10">
                        <div class="mb-1 flex items-center justify-between text-xs">
                            <span class="text-gray-700 dark:text-gray-200">{{ $stage['label'] }}</span>
                            <span class="font-semibold text-primary-600 dark:text-primary-400">{{ number_format($count) }}</span>
                        </div>
                        <div class="h-2 rounded-full bg-gray-200/70 dark:bg-white/10">
                            <div class="h-2 rounded-full bg-amber-400" style="width: {{ $width }}%"></div>
                        </div>
                        @if ($drop !== null)
                            <p class="mt-1 text-[11px] text-rose-600 dark:text-rose-400">
                                {{ __('Drop-off: :rate%', ['rate' => number_format((float) $drop, 1)]) }}
                            </p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-filament::section>
