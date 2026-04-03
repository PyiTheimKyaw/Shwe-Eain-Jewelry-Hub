<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ExchangeRateSnapshot;
use App\Models\GoldPriceSnapshot;
use App\Services\PricingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PricingController extends Controller
{
    public function __construct(
        protected PricingService $pricing
    ) {}

    public function goldCurrent(): JsonResponse
    {
        $snap = $this->pricing->currentGoldSnapshot();
        $standards = $this->pricing->burmeseMassStandards();

        return response()->json([
            'data' => $snap ? [
                'id' => $snap->id,
                'price_mmk_per_gram' => $snap->price_mmk_per_gram,
                'effective_from' => $snap->effective_from?->toIso8601String(),
                'effective_to' => $snap->effective_to?->toIso8601String(),
            ] : null,
            'meta' => [
                'configured' => $snap !== null,
                'weight_standards' => $standards,
            ],
        ]);
    }

    public function goldHistory(Request $request): JsonResponse
    {
        $items = GoldPriceSnapshot::query()
            ->orderByDesc('effective_from')
            ->paginate($request->integer('per_page', 30));

        return response()->json($items);
    }

    public function storeGold(Request $request): JsonResponse
    {
        $data = $request->validate([
            'price_mmk_per_gram' => ['required', 'numeric', 'min:0'],
            'effective_from' => ['nullable', 'date'],
        ]);

        $from = isset($data['effective_from']) ? Carbon::parse($data['effective_from']) : null;

        $snap = $this->pricing->recordGoldPrice(
            $data['price_mmk_per_gram'],
            $from,
            $request->user()->id
        );

        return response()->json(['data' => $snap], 201);
    }

    public function fxCurrent(): JsonResponse
    {
        $snap = $this->pricing->currentFxSnapshot();
        $standards = $this->pricing->burmeseMassStandards();

        return response()->json([
            'data' => $snap ? [
                'id' => $snap->id,
                'mmk_per_usd' => $snap->mmk_per_usd,
                'effective_from' => $snap->effective_from?->toIso8601String(),
                'effective_to' => $snap->effective_to?->toIso8601String(),
            ] : null,
            'meta' => [
                'configured' => $snap !== null,
                'weight_standards' => $standards,
            ],
        ]);
    }

    public function fxHistory(Request $request): JsonResponse
    {
        $items = ExchangeRateSnapshot::query()
            ->orderByDesc('effective_from')
            ->paginate($request->integer('per_page', 30));

        return response()->json($items);
    }

    public function storeFx(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mmk_per_usd' => ['required', 'numeric', 'min:0'],
            'effective_from' => ['nullable', 'date'],
        ]);

        $from = isset($data['effective_from']) ? Carbon::parse($data['effective_from']) : null;

        $snap = $this->pricing->recordExchangeRate(
            $data['mmk_per_usd'],
            $from,
            $request->user()->id
        );

        return response()->json(['data' => $snap], 201);
    }
}
