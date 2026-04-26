<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Order;
use App\Models\Payment;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('order_number')
                    ->label('Order number')
                    ->copyable(),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('customer.email')
                    ->label('Customer email')
                    ->placeholder('-'),
                TextEntry::make('customer.customerProfile.phone')
                    ->label('Customer phone')
                    ->placeholder('-'),
                TextEntry::make('customer.customerProfile.address_json')
                    ->label('Customer address')
                    ->state(fn (Order $record): string => self::formatAddress($record))
                    ->placeholder('-'),
                TextEntry::make('customer.name')
                    ->label('Customer')
                    ->placeholder('-'),
                TextEntry::make('quote.quote_number')
                    ->label('Source quote')
                    ->placeholder('-'),
                TextEntry::make('workflow_stage')
                    ->label('Workflow stage')
                    ->state(fn (Order $record): string => self::workflowLabel($record)),
                TextEntry::make('deposit_amount_mmk')
                    ->label('Deposit due (MMK)')
                    ->money('MMK')
                    ->placeholder('-'),
                TextEntry::make('confirmed_payments_mmk')
                    ->label('Confirmed payments (MMK)')
                    ->state(fn (Order $record): string => self::money(self::confirmedPayments($record)))
                    ->placeholder('-'),
                TextEntry::make('remaining_balance_mmk')
                    ->label('Remaining balance (MMK)')
                    ->state(fn (Order $record): string => self::money(self::remainingBalance($record)))
                    ->placeholder('-'),
                TextEntry::make('total_mmk')
                    ->label('Total (MMK)')
                    ->money('MMK')
                    ->placeholder('-'),
                TextEntry::make('items_summary')
                    ->label('Item specs and pricing')
                    ->state(fn (Order $record): string => self::itemSummary($record))
                    ->columnSpanFull()
                    ->placeholder('-'),
                TextEntry::make('assignment_summary')
                    ->label('Artisan assignment')
                    ->state(fn (Order $record): string => self::assignmentSummary($record))
                    ->columnSpanFull()
                    ->placeholder('-'),
                TextEntry::make('payment_ledger')
                    ->label('Payment ledger')
                    ->state(fn (Order $record): string => self::paymentLedger($record))
                    ->columnSpanFull()
                    ->placeholder('-'),
                TextEntry::make('workflow_timeline')
                    ->label('Workflow timeline')
                    ->state(fn (Order $record): string => self::workflowTimeline($record))
                    ->columnSpanFull()
                    ->placeholder('-'),
                TextEntry::make('shipping_notes')
                    ->columnSpanFull()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }

    private static function formatAddress(Order $order): string
    {
        $address = $order->customer?->customerProfile?->address_json;
        if (! is_array($address) || $address === []) {
            return '-';
        }

        $parts = array_values(array_filter(array_map(
            fn (mixed $v): string => is_scalar($v) ? trim((string) $v) : '',
            $address
        )));

        return $parts === [] ? '-' : implode(', ', $parts);
    }

    private static function money(float|int|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return number_format((float) $value, 2).' MMK';
    }

    private static function confirmedPayments(Order $order): float
    {
        return (float) $order->payments
            ->where('status', Payment::STATUS_CONFIRMED)
            ->sum('amount_mmk');
    }

    private static function remainingBalance(Order $order): float
    {
        return max(0.0, (float) $order->total_mmk - self::confirmedPayments($order));
    }

    private static function itemSummary(Order $order): string
    {
        if ($order->items->isEmpty()) {
            return '-';
        }

        return $order->items
            ->map(function ($item): string {
                $title = trim((string) ($item->title_snapshot ?: ($item->product?->name ?? 'Custom item')));

                return sprintf(
                    '%s | %sg | Metal %s | Labor %s | Gems %s | Line total %s',
                    $title,
                    number_format((float) $item->weight_grams, 2),
                    self::money($item->metal_value_mmk),
                    self::money($item->labor_fee_mmk),
                    self::money($item->gem_price_mmk),
                    self::money($item->line_total_mmk),
                );
            })
            ->implode("\n");
    }

    private static function assignmentSummary(Order $order): string
    {
        if ($order->assignments->isEmpty()) {
            return 'No assignments yet.';
        }

        return $order->assignments
            ->map(function ($assignment): string {
                $artisan = $assignment->artisan?->name ?? 'Unknown artisan';
                $item = $assignment->orderItem?->title_snapshot;
                $scope = $assignment->task_scope === 'line_item' && $item
                    ? "Line item: {$item}"
                    : 'Full order';
                $note = trim((string) ($assignment->instructions ?? ''));
                $notePart = $note !== '' ? " | {$note}" : '';

                return sprintf(
                    '%s | %s | Status: %s%s',
                    $artisan,
                    $scope,
                    $assignment->status,
                    $notePart
                );
            })
            ->implode("\n");
    }

    private static function paymentLedger(Order $order): string
    {
        if ($order->payments->isEmpty()) {
            return 'No payments yet.';
        }

        return $order->payments
            ->sortByDesc('id')
            ->map(function ($payment): string {
                $verifiedBy = $payment->verifiedBy?->name ? " | Verified by {$payment->verifiedBy->name}" : '';
                $verifiedAt = $payment->verified_at ? " @ {$payment->verified_at->format('Y-m-d H:i')}" : '';
                $reason = $payment->rejection_reason ? " | Reason: {$payment->rejection_reason}" : '';
                $receipt = $payment->receipt?->receipt_number ? " | Receipt {$payment->receipt->receipt_number}" : '';

                return sprintf(
                    '%s %s | %s | %s%s%s%s',
                    strtoupper((string) $payment->type),
                    self::money($payment->amount_mmk),
                    strtoupper((string) $payment->currency),
                    $payment->status,
                    $verifiedBy.$verifiedAt,
                    $reason,
                    $receipt
                );
            })
            ->implode("\n");
    }

    private static function workflowTimeline(Order $order): string
    {
        if ($order->progressEvents->isEmpty()) {
            return 'No progress events yet.';
        }

        return $order->progressEvents
            ->sortByDesc('created_at')
            ->map(function ($event): string {
                $artisan = $event->artisan?->name ?? 'System';
                $note = trim((string) ($event->note ?? ''));
                $notePart = $note !== '' ? " | {$note}" : '';

                return sprintf(
                    '%s | %s | %s%s',
                    $event->created_at?->format('Y-m-d H:i') ?? '-',
                    strtoupper((string) $event->stage),
                    $artisan,
                    $notePart
                );
            })
            ->implode("\n");
    }

    private static function workflowLabel(Order $order): string
    {
        $status = (string) $order->status;
        $isReadyMade = $order->isReadyMadeFulfillment();

        if ($isReadyMade) {
            return match ($status) {
                'awaiting_deposit' => 'Awaiting payment (Ready-made)',
                'deposit_confirmed' => 'Deposit Confirmed (Ready-made)',
                'awaiting_final_settlement' => 'Deposit Confirmed (Ready-made)',
                'ready' => 'Ready for pickup',
                'completed' => 'Fully paid',
                default => ucfirst(str_replace('_', ' ', $status)),
            };
        }

        return match ($status) {
            'awaiting_deposit' => 'Awaiting deposit',
            'deposit_confirmed' => 'Deposit confirmed',
            'in_production' => 'In production',
            'pending_qc' => 'Pending quality control',
            'ready' => 'Ready for collection',
            'shipped' => 'Shipped',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }
}

