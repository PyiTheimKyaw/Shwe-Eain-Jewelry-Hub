<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use App\Models\Quote;
use App\Models\User;
use App\Services\InquiryQuoteCommitmentGate;
use App\Services\PricingService;
use App\Services\QuoteBuilderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuoteController extends Controller
{
    public function __construct(
        protected PricingService $pricing,
        protected QuoteBuilderService $quotes
    ) {}

    public function index(Request $request): JsonResponse
    {
        $q = Quote::query()->with(['customer', 'lineItems'])->orderByDesc('created_at');

        if ($request->filled('status')) {
            $q->where('status', $request->string('status'));
        }

        return response()->json($q->paginate(30));
    }

    public function show(Quote $quote): JsonResponse
    {
        $quote->load(['customer', 'lineItems.product', 'goldPriceSnapshot', 'exchangeRateSnapshot', 'inquiry']);

        return response()->json(['data' => $quote]);
    }

    public function store(Request $request): JsonResponse
    {
        $gold = $this->pricing->currentGoldSnapshot();
        $fx = $this->pricing->currentFxSnapshot();

        if (! $gold || ! $fx) {
            return response()->json([
                'message' => 'Configure current gold price (MMK/gram) and MMK-per-USD exchange rate before creating quotes.',
            ], 422);
        }

        $data = $request->validate([
            'customer_user_id' => ['required', 'exists:users,id'],
            'inquiry_id' => ['nullable', 'exists:inquiries,id'],
            'valid_until' => ['nullable', 'date'],
            'notes_internal' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['nullable', 'exists:products,id'],
            'lines.*.custom_title' => ['nullable', 'string', 'max:255'],
            'lines.*.weight_grams' => ['required', 'numeric', 'min:0'],
            'lines.*.labor_fee_mmk' => ['nullable', 'numeric', 'min:0'],
            'lines.*.gem_price_mmk' => ['nullable', 'numeric', 'min:0'],
        ]);

        $customer = User::query()->findOrFail($data['customer_user_id']);
        if (! $customer->isCustomer()) {
            return response()->json(['message' => 'Quotes can only target customer accounts.'], 422);
        }

        $warning = null;
        if (! empty($data['inquiry_id'])) {
            $inquiry = Inquiry::query()->findOrFail($data['inquiry_id']);
            $warning = app(InquiryQuoteCommitmentGate::class)->warningForPublishing($inquiry);
        }

        $quote = $this->quotes->createQuote(
            $customer,
            $gold,
            $fx,
            $data['lines'],
            $data['inquiry_id'] ?? null,
            $data['notes_internal'] ?? null,
            isset($data['valid_until']) ? new \DateTimeImmutable($data['valid_until']) : null
        );

        if (! empty($data['inquiry_id'])) {
            Inquiry::query()->whereKey($data['inquiry_id'])->update(['status' => 'quoted']);
        }

        $payload = ['data' => $quote];
        if ($warning !== null) {
            $payload['meta'] = ['warning' => $warning];
        }

        return response()->json($payload, 201);
    }

    public function update(Request $request, Quote $quote): JsonResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', 'in:draft,sent,accepted,expired,void'],
        ]);

        $warning = null;
        if (isset($data['status'])) {
            if ($data['status'] === 'sent' && $quote->status !== 'sent' && $quote->inquiry_id !== null) {
                $inquiry = Inquiry::query()->find($quote->inquiry_id);
                $warning = app(InquiryQuoteCommitmentGate::class)->warningForPublishing($inquiry);
            }
            $quote->update(['status' => $data['status']]);
        }

        $payload = ['data' => $quote->fresh()->load('lineItems')];
        if ($warning !== null) {
            $payload['meta'] = ['warning' => $warning];
        }

        return response()->json($payload);
    }
}
