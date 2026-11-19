<?php

namespace App\Services;

use App\Models\Inquiry;
use App\Models\Order;
use App\Models\Payment;

/**
 * Non-blocking advisor: admins may always publish; optional warnings only.
 */
class InquiryQuoteCommitmentGate
{
    public const WARNING_CONFIRMED_DEPOSIT = 'A deposit is already confirmed. A new quote will recalculate the balance.';

    /**
     * Shown when publishing a new customer-facing quote while a confirmed deposit exists on an active order for this inquiry.
     */
    public function warningForPublishing(?Inquiry $inquiry): ?string
    {
        if ($inquiry === null) {
            return null;
        }

        $orderIds = Order::query()
            ->whereHas('quote', fn ($q) => $q->where('inquiry_id', $inquiry->id))
            ->where('status', '!=', 'cancelled')
            ->pluck('id');

        if ($orderIds->isEmpty()) {
            return null;
        }

        $hasConfirmed = Payment::query()
            ->whereIn('order_id', $orderIds)
            ->where('type', Payment::TYPE_DEPOSIT)
            ->where('status', Payment::STATUS_CONFIRMED)
            ->exists();

        return $hasConfirmed ? self::WARNING_CONFIRMED_DEPOSIT : null;
    }
}
