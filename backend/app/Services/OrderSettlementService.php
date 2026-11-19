<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;

class OrderSettlementService
{
    public function confirmedDepositsMmk(Order $order): float
    {
        return (float) Payment::query()
            ->where('order_id', $order->id)
            ->where('type', Payment::TYPE_DEPOSIT)
            ->where('status', Payment::STATUS_CONFIRMED)
            ->sum('amount_mmk');
    }

    public function confirmedPaymentsMmk(Order $order): float
    {
        return (float) Payment::query()
            ->where('order_id', $order->id)
            ->where('status', Payment::STATUS_CONFIRMED)
            ->sum('amount_mmk');
    }

    public function remainingBalanceFromDepositsMmk(Order $order): float
    {
        return max(0.0, round((float) $order->total_mmk - $this->confirmedDepositsMmk($order), 4));
    }

    public function remainingBalanceMmk(Order $order): float
    {
        return max(0.0, round((float) $order->total_mmk - $this->confirmedPaymentsMmk($order), 4));
    }
}

