<?php

namespace App\Filament\Widgets;

use App\Models\Inquiry;
use App\Models\Payment;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ActionQueuesStats extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $paymentsAwaitingVerification = Payment::query()
            ->where('status', Payment::STATUS_AWAITING_VERIFICATION)
            ->count();

        $inquiriesNeedingQuotes = Inquiry::query()
            ->whereIn('status', ['open', 'reviewing'])
            ->count();

        return [
            Stat::make(__('Payments awaiting verification'), (string) $paymentsAwaitingVerification)
                ->description(__('Requires finance/admin review'))
                ->color('warning'),
            Stat::make(__('Inquiries needing quotes'), (string) $inquiriesNeedingQuotes)
                ->description(__('Open or reviewing inquiries'))
                ->color('primary'),
        ];
    }
}
