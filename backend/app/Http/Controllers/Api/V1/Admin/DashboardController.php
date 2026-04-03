<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use App\Models\Order;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function summary(): JsonResponse
    {
        return response()->json([
            'open_inquiries' => Inquiry::query()->where('status', 'open')->count(),
            'orders_in_production' => Order::query()->where('status', 'in_production')->count(),
            'orders_pending' => Order::query()->where('status', 'pending')->count(),
        ]);
    }
}
