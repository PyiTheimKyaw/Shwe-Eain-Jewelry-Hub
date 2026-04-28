<?php

namespace App\Services;

use App\Models\Inquiry;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Quote;
use Carbon\Carbon;

/**
 * Aggregates real DB metrics for Filament dashboard charts (command center).
 */
class DashboardAnalyticsService
{
    /**
     * Last N calendar months of confirmed payment revenue (verified_at), MMK + USD.
     *
     * @return array{labels: list<string>, mmk: list<float>, usd: list<float>}
     */
    public function monthlyRevenueSeries(int $months = 12): array
    {
        $start = Carbon::now()->subMonths(max(1, $months) - 1)->startOfMonth();

        $payments = Payment::query()
            ->where('status', Payment::STATUS_CONFIRMED)
            ->whereNotNull('verified_at')
            ->where('verified_at', '>=', $start)
            ->with(['order.exchangeRateSnapshot'])
            ->get();

        $mmkByYm = [];
        $usdByYm = [];
        foreach ($payments as $payment) {
            $verified = $payment->verified_at;
            if ($verified === null) {
                continue;
            }
            $ym = $verified->format('Y-m');
            $amt = (float) $payment->amount_mmk;
            $mmkByYm[$ym] = ($mmkByYm[$ym] ?? 0.0) + $amt;
            $rate = (float) ($payment->order?->exchangeRateSnapshot?->mmk_per_usd ?? 0);
            if ($rate > 0) {
                $usdByYm[$ym] = ($usdByYm[$ym] ?? 0.0) + ($amt / $rate);
            }
        }

        $labels = [];
        $mmk = [];
        $usd = [];
        $cursor = $start->copy()->startOfMonth();
        for ($i = 0; $i < $months; $i++) {
            $key = $cursor->format('Y-m');
            $labels[] = $cursor->format('M Y');
            $mmk[] = (float) ($mmkByYm[$key] ?? 0.0);
            $usd[] = (float) ($usdByYm[$key] ?? 0.0);
            $cursor->addMonth();
        }

        return ['labels' => $labels, 'mmk' => $mmk, 'usd' => $usd];
    }

    /**
     * Order counts by operational stage for doughnut chart.
     *
     * @return array{labels: list<string>, data: list<int>, colors: list<string>}
     */
    public function orderStageDistribution(): array
    {
        $pipeline = fn () => Order::query()->where('status', '!=', 'cancelled');

        $pending = $pipeline()->where('status', 'awaiting_deposit')->count();
        $production = $pipeline()->whereIn('status', ['deposit_confirmed', 'in_production'])->count();
        $qc = $pipeline()->where('status', 'pending_qc')->count();
        $ready = $pipeline()->whereIn('status', ['ready', 'shipped', 'completed'])->count();

        return [
            'labels' => ['Pending', 'Production', 'QC', 'Ready'],
            'data' => [$pending, $production, $qc, $ready],
            'colors' => ['#D4AF37', '#1E3A5F', '#64748B', '#F5E6C8'],
        ];
    }

    /**
     * Proxy funnel: new orders / new inquiries per month (last 6 months), as %.
     *
     * @return array{labels: list<string>, rates: list<float>}
     */
    public function inquiryOrderConversionSeries(int $months = 6): array
    {
        $start = Carbon::now()->subMonths(max(1, $months) - 1)->startOfMonth();

        $inquiryCounts = [];
        foreach (Inquiry::query()->where('created_at', '>=', $start)->get(['created_at']) as $inquiry) {
            $ym = $inquiry->created_at?->format('Y-m');
            if ($ym === null) {
                continue;
            }
            $inquiryCounts[$ym] = ($inquiryCounts[$ym] ?? 0) + 1;
        }

        $orderCounts = [];
        foreach (Order::query()->where('created_at', '>=', $start)->get(['created_at']) as $order) {
            $ym = $order->created_at?->format('Y-m');
            if ($ym === null) {
                continue;
            }
            $orderCounts[$ym] = ($orderCounts[$ym] ?? 0) + 1;
        }

        $labels = [];
        $rates = [];
        $cursor = $start->copy()->startOfMonth();
        for ($i = 0; $i < $months; $i++) {
            $key = $cursor->format('Y-m');
            $labels[] = $cursor->format('M Y');
            $inq = (int) ($inquiryCounts[$key] ?? 0);
            $ord = (int) ($orderCounts[$key] ?? 0);
            $rates[] = $inq > 0 ? round(($ord / $inq) * 100, 1) : 0.0;
            $cursor->addMonth();
        }

        return ['labels' => $labels, 'rates' => $rates];
    }

    /**
     * SLA-style breach counts (simple age thresholds on active pipeline orders).
     */
    public function slaBreachCount(): int
    {
        $now = Carbon::now();
        $settings = app(BusinessConfigService::class);
        $paymentRiskHours = max(1, $settings->paymentRiskHours());
        $orderRiskHours = max(1, $settings->orderRiskHours());

        $paymentStale = Payment::query()
            ->where('status', Payment::STATUS_AWAITING_VERIFICATION)
            ->where('submitted_at', '<', $now->copy()->subHours($paymentRiskHours))
            ->count();

        $depositStale = Order::query()
            ->where('status', 'awaiting_deposit')
            ->where('created_at', '<', $now->copy()->subHours($orderRiskHours))
            ->count();

        $preProdStale = Order::query()
            ->where('status', 'deposit_confirmed')
            ->where('updated_at', '<', $now->copy()->subHours($orderRiskHours))
            ->count();

        $productionStale = Order::query()
            ->where('status', 'in_production')
            ->where('updated_at', '<', $now->copy()->subHours($orderRiskHours))
            ->count();

        return $paymentStale + $depositStale + $preProdStale + $productionStale;
    }

    /**
     * Dual conversion funnels (customer journey + operations), with stage-to-stage drop-off.
     *
     * @return array{
     *   window_days: int,
     *   customer: array<int, array{label:string,count:int,drop_off_percent:float|null}>,
     *   operations: array<int, array{label:string,count:int,drop_off_percent:float|null}>
     * }
     */
    public function conversionFunnels(int $days = 30): array
    {
        $windowDays = max(1, $days);
        $since = Carbon::now()->subDays($windowDays)->startOfDay();

        $catalogLinkedInquiries = Inquiry::query()
            ->where('created_at', '>=', $since)
            ->whereNotNull('product_id')
            ->count();
        $inquiries = Inquiry::query()
            ->where('created_at', '>=', $since)
            ->count();
        $quotesAccepted = Quote::query()
            ->where('created_at', '>=', $since)
            ->where('status', 'accepted')
            ->count();
        $depositConfirmed = Order::query()
            ->where('created_at', '>=', $since)
            ->whereIn('status', ['deposit_confirmed', 'in_production', 'pending_qc', 'ready', 'shipped', 'completed'])
            ->count();

        $quotesSent = Quote::query()
            ->where('created_at', '>=', $since)
            ->whereIn('status', ['sent', 'accepted'])
            ->count();
        $ready = Order::query()
            ->where('created_at', '>=', $since)
            ->whereIn('status', ['ready', 'shipped', 'completed'])
            ->count();
        $completed = Order::query()
            ->where('created_at', '>=', $since)
            ->where('status', 'completed')
            ->count();

        $customerStages = [
            ['label' => 'Catalog-linked inquiry', 'count' => $catalogLinkedInquiries],
            ['label' => 'Inquiry created', 'count' => $inquiries],
            ['label' => 'Quote accepted', 'count' => $quotesAccepted],
            ['label' => 'Deposit confirmed', 'count' => $depositConfirmed],
        ];
        $operationsStages = [
            ['label' => 'Inquiry created', 'count' => $inquiries],
            ['label' => 'Quote sent', 'count' => $quotesSent],
            ['label' => 'Deposit confirmed', 'count' => $depositConfirmed],
            ['label' => 'Ready', 'count' => $ready],
            ['label' => 'Completed', 'count' => $completed],
        ];

        return [
            'window_days' => $windowDays,
            'customer' => $this->withDropOff($customerStages),
            'operations' => $this->withDropOff($operationsStages),
        ];
    }

    /**
     * @param  array<int, array{label:string,count:int}>  $stages
     * @return array<int, array{label:string,count:int,drop_off_percent:float|null}>
     */
    private function withDropOff(array $stages): array
    {
        $out = [];
        foreach ($stages as $idx => $stage) {
            if ($idx === 0) {
                $out[] = [
                    'label' => $stage['label'],
                    'count' => $stage['count'],
                    'drop_off_percent' => null,
                ];
                continue;
            }
            $prev = max(0, (int) ($stages[$idx - 1]['count'] ?? 0));
            $curr = max(0, (int) ($stage['count'] ?? 0));
            $drop = $prev > 0 ? round(max(0, (($prev - $curr) / $prev) * 100), 1) : null;
            $out[] = [
                'label' => $stage['label'],
                'count' => $curr,
                'drop_off_percent' => $drop,
            ];
        }

        return $out;
    }
}
