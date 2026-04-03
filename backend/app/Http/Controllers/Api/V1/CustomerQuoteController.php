<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Quote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerQuoteController extends Controller
{
    public function mine(Request $request): JsonResponse
    {
        $items = Quote::query()
            ->where('customer_user_id', $request->user()->id)
            ->with('lineItems.product')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($items);
    }

    public function show(Request $request, Quote $quote): JsonResponse
    {
        $this->authorize('view', $quote);

        $quote->load(['lineItems.product', 'goldPriceSnapshot', 'exchangeRateSnapshot', 'inquiry']);

        return response()->json(['data' => $quote]);
    }
}
