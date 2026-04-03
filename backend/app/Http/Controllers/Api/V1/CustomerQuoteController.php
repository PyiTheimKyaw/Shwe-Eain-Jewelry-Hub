<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use App\Models\Quote;
use App\Services\FirebaseInquiryService;
use App\Services\OrderBuilderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerQuoteController extends Controller
{
    public function __construct(
        protected OrderBuilderService $orders,
        protected FirebaseInquiryService $firebaseInquiry
    ) {}

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

    public function accept(Request $request, Quote $quote): JsonResponse
    {
        $this->authorize('accept', $quote);

        if ($quote->valid_until !== null && $quote->valid_until->isPast()) {
            return response()->json(['message' => 'This quote has expired.'], 422);
        }

        $order = DB::transaction(function () use ($quote) {
            $locked = Quote::query()->lockForUpdate()->findOrFail($quote->id);

            if ($locked->valid_until !== null && $locked->valid_until->isPast()) {
                abort(422, 'This quote has expired.');
            }

            if ($locked->status !== 'sent') {
                abort(422, 'Only a sent quote can be accepted.');
            }

            $locked->update(['status' => 'accepted']);

            return $this->orders->createFromQuote($locked->fresh(['lineItems']));
        });

        $order->load(['items', 'quote']);

        if ($quote->inquiry_id) {
            $inquiry = Inquiry::query()->find($quote->inquiry_id);
            if ($inquiry !== null) {
                $this->firebaseInquiry->syncInquiryThreadDocument($inquiry);
            }
        }

        return response()->json([
            'data' => [
                'quote' => $quote->fresh(['lineItems']),
                'order' => $order,
            ],
        ]);
    }

    public function decline(Request $request, Quote $quote): JsonResponse
    {
        $this->authorize('decline', $quote);

        DB::transaction(function () use ($quote) {
            $quote->refresh();

            if ($quote->status !== 'sent') {
                abort(422, 'Only a sent quote can be declined.');
            }

            $quote->update(['status' => 'void']);

            if ($quote->inquiry_id) {
                Inquiry::query()->whereKey($quote->inquiry_id)->update(['status' => 'reviewing']);
            }
        });

        if ($quote->inquiry_id) {
            $inquiry = Inquiry::query()->find($quote->inquiry_id);
            if ($inquiry !== null) {
                $this->firebaseInquiry->syncInquiryThreadDocument($inquiry);
            }
        }

        return response()->json(['data' => $quote->fresh(['lineItems'])]);
    }
}
