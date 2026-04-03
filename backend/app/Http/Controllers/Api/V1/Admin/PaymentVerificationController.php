<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PaymentVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentVerificationController extends Controller
{
    public function __construct(
        protected PaymentVerificationService $payments
    ) {}

    public function confirm(Request $request, Payment $payment): JsonResponse
    {
        $this->authorize('verify', $payment);

        $data = $this->payments->confirm($request->user(), $payment);

        return response()->json(['data' => $data]);
    }

    public function reject(Request $request, Payment $payment): JsonResponse
    {
        $this->authorize('verify', $payment);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $fresh = $this->payments->reject($payment, $data['reason'] ?? null);

        return response()->json(['data' => $fresh]);
    }
}
