<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use App\Models\Quote;
use App\Services\FirebaseInquiryService;
use App\Services\OrderBuilderService;
use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class CustomerQuoteController extends Controller
{
    public function __construct(
        protected OrderBuilderService $orders,
        protected FirebaseInquiryService $firebaseInquiry,
        protected PushNotificationService $push
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

        if ($this->isQuoteExpired($quote)) {
            return response()->json(['message' => 'This quote has expired.'], 422);
        }

        $order = DB::transaction(function () use ($quote) {
            $locked = Quote::query()->lockForUpdate()->findOrFail($quote->id);

            if ($this->isQuoteExpired($locked)) {
                abort(422, 'This quote has expired.');
            }

            if ($locked->status !== 'sent') {
                abort(422, 'Only a sent quote can be accepted.');
            }

            return $this->orders->acceptSentQuoteAndCreateOrder($locked);
        });

        $order->load(['items', 'quote']);

        if ($quote->inquiry_id) {
            $inquiry = Inquiry::query()->find($quote->inquiry_id);
            if ($inquiry !== null) {
                $this->firebaseInquiry->publishTimelineLogMessage($inquiry, '--- Quote Accepted by Customer ---');
                $this->firebaseInquiry->syncInquiryThreadDocument($inquiry);
            }
        }

        $admins = \App\Models\User::query()->where('role', UserRole::Admin->value)->get();
        $this->push->notifyUsers(
            $admins,
            'Quote accepted',
            "Customer accepted quote {$quote->quote_number}.",
            [
                'category' => 'quote',
                'quote_id' => $quote->id,
                'order_id' => $order->id,
            ]
        );

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
                $this->firebaseInquiry->publishTimelineLogMessage(
                    $inquiry,
                    '--- Quote was declined by Customer ---'
                );
                $this->firebaseInquiry->syncInquiryThreadDocument($inquiry);
            }
        }

        $admins = \App\Models\User::query()->where('role', UserRole::Admin->value)->get();
        $this->push->notifyUsers(
            $admins,
            'Quote declined',
            "Customer declined quote {$quote->quote_number}.",
            [
                'category' => 'quote',
                'quote_id' => $quote->id,
                'inquiry_id' => $quote->inquiry_id,
            ]
        );

        return response()->json(['data' => $quote->fresh(['lineItems'])]);
    }

    private function isQuoteExpired(Quote $quote): bool
    {
        if ($quote->valid_until === null) {
            return false;
        }

        $cutoff = Carbon::instance($quote->valid_until);
        // Admin forms often submit date-only valid-until values; treat those as end-of-day.
        if ($cutoff->format('H:i:s') === '00:00:00') {
            $cutoff = $cutoff->endOfDay();
        }

        return $cutoff->isPast();
    }
}
