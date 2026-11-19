<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function summary(): JsonResponse
    {
        return response()->json([
            'open_inquiries' => Inquiry::query()->where('status', 'open')->count(),
            'orders_in_production' => Order::query()->where('status', 'in_production')->count(),
            'orders_awaiting_deposit' => Order::query()->where('status', 'awaiting_deposit')->count(),
            'payments_awaiting_verification' => Payment::query()
                ->where('status', Payment::STATUS_AWAITING_VERIFICATION)
                ->count(),
        ]);
    }
}
