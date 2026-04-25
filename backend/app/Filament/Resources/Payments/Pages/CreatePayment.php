<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Payment;
use App\Models\Order;
use App\Services\OrderSettlementService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CreatePayment extends CreateRecord
{
    protected static string $resource = PaymentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $paymentMethod = (string) ($data['payment_method'] ?? 'profile');
        if ($paymentMethod === 'cash') {
            $data['payment_profile_id'] = null;
        } elseif (empty($data['payment_profile_id'])) {
            throw ValidationException::withMessages([
                'payment_profile_id' => 'Payment profile is required unless Cash Payment is selected.',
            ]);
        }
        unset($data['payment_method']);

        $orderId = isset($data['order_id']) ? (int) $data['order_id'] : null;
        if (
            $orderId !== null
            && ($data['type'] ?? null) === Payment::TYPE_FINAL
        ) {
            $order = Order::query()->find($orderId);
            if ($order !== null) {
                $remaining = app(OrderSettlementService::class)
                    ->remainingBalanceFromDepositsMmk($order);
                if ($remaining <= 0) {
                    throw ValidationException::withMessages([
                        'order_id' => 'This order is already fully settled. Final payment cannot be recorded again.',
                    ]);
                }
            }
        }

        if (($data['status'] ?? null) === Payment::STATUS_CONFIRMED) {
            $data['verified_at'] = now();
            $data['verified_by_user_id'] = Auth::id();
        }

        if (($data['status'] ?? null) === Payment::STATUS_AWAITING_VERIFICATION && empty($data['submitted_at'])) {
            $data['submitted_at'] = now();
        }

        return $data;
    }
}

