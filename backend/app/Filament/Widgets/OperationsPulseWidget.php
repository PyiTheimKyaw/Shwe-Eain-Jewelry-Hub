<?php

namespace App\Filament\Widgets;

use App\Models\OrderAssignment;
use App\Models\Payment;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OperationsPulseWidget extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected static ?int $sort = 6;

    protected ?string $heading = 'Operations pulse';

    protected ?string $description = 'Immediate queue counts for admin action and workshop flow';

    protected int | string | array $columnSpan = 'full';

    protected function getPollingInterval(): ?string
    {
        return '60s';
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $pendingVerifications = Payment::query()
            ->where('status', Payment::STATUS_AWAITING_VERIFICATION)
            ->count();

        $activeProduction = OrderAssignment::query()
            ->whereIn('status', ['assigned', 'in_progress', 'blocked'])
            ->count();

        return [
            Stat::make(__('Pending verifications'), (string) $pendingVerifications)
                ->description(__('Payments awaiting finance/admin verification'))
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->chart($pendingVerifications > 0 ? [
                    max(0, $pendingVerifications - 3),
                    max(0, $pendingVerifications - 2),
                    max(0, $pendingVerifications - 1),
                    $pendingVerifications,
                ] : null)
                ->chartColor(['#F5E6C8', '#D4AF37'])
                ->color($pendingVerifications > 0 ? 'warning' : 'success')
                ->icon(Heroicon::OutlinedClipboardDocumentCheck),
            Stat::make(__('Active production'), (string) $activeProduction)
                ->description(__('Assignments currently with artisans'))
                ->descriptionIcon(Heroicon::OutlinedWrenchScrewdriver)
                ->chart($activeProduction > 0 ? [
                    max(0, $activeProduction - 2),
                    max(0, $activeProduction - 1),
                    $activeProduction,
                    $activeProduction + 1,
                ] : null)
                ->chartColor(['#1E3A5F', '#D4AF37'])
                ->color($activeProduction > 0 ? 'info' : 'success')
                ->icon(Heroicon::OutlinedSparkles),
        ];
    }
}
