<x-filament::section>
    <div class="flex items-center justify-between gap-3">
        <div>
            <h3 class="text-base font-semibold">Quick Export</h3>
            <p class="text-sm text-gray-500">Download sales summary reports instantly.</p>
        </div>
        <div class="flex items-center gap-2">
            <x-filament::button
                tag="a"
                href="{{ route('admin.reports.sales-summary.today') }}"
                icon="heroicon-o-arrow-down-tray"
            >
                Today's Sales Summary
            </x-filament::button>
            <x-filament::button
                tag="a"
                color="gray"
                href="{{ route('admin.reports.sales-summary.current-month') }}"
                icon="heroicon-o-document-arrow-down"
            >
                Current Month PDF
            </x-filament::button>
        </div>
    </div>
</x-filament::section>

