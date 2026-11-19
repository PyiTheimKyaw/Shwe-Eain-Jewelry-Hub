<x-filament::section
    :heading="__('Global pricing & audit')"
    :description="__('Publish a new snapshot to refresh catalog pricing and USD display immediately. Review payment confirmations in the audit log.')"
>
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex flex-wrap gap-2">
            <x-filament::button
                tag="a"
                href="{{ \App\Filament\Resources\GoldPriceSnapshots\GoldPriceSnapshotResource::getUrl('create') }}"
                icon="heroicon-o-sun"
                color="primary"
            >
                {{ __('New gold snapshot') }}
            </x-filament::button>
            <x-filament::button
                tag="a"
                href="{{ \App\Filament\Resources\ExchangeRateSnapshots\ExchangeRateSnapshotResource::getUrl('create') }}"
                icon="heroicon-o-arrows-right-left"
                outlined
            >
                {{ __('New FX snapshot') }}
            </x-filament::button>
            <x-filament::button
                tag="a"
                href="{{ \App\Filament\Resources\GoldPriceSnapshots\GoldPriceSnapshotResource::getUrl('index') }}"
                icon="heroicon-o-list-bullet"
                size="sm"
                color="gray"
            >
                {{ __('Gold history') }}
            </x-filament::button>
            <x-filament::button
                tag="a"
                href="{{ \App\Filament\Resources\ExchangeRateSnapshots\ExchangeRateSnapshotResource::getUrl('index') }}"
                icon="heroicon-o-list-bullet"
                size="sm"
                color="gray"
            >
                {{ __('FX history') }}
            </x-filament::button>
        </div>
        <div class="flex flex-wrap gap-2 border-t border-gray-200 pt-4 dark:border-white/10 lg:border-t-0 lg:pt-0">
            <x-filament::button
                tag="a"
                href="{{ \App\Filament\Resources\AuditLogs\AuditLogResource::getUrl('index') }}"
                icon="heroicon-o-clipboard-document-list"
                color="gray"
            >
                {{ __('Audit trail') }}
            </x-filament::button>
        </div>
    </div>
</x-filament::section>
