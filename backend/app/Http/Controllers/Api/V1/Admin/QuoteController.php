<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use App\Models\Quote;
use App\Models\User;
use App\Services\AdminAuditLogService;
use App\Services\InquiryQuoteCommitmentGate;
use App\Services\PricingService;
use App\Services\QuoteBuilderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class QuoteController extends Controller
{
    public function __construct(
        protected PricingService $pricing,
        protected QuoteBuilderService $quotes,
        protected AdminAuditLogService $auditLogs
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
            'deposit_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'deposit_amount_mmk' => ['nullable', 'numeric', 'min:0'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*' => ['array'],
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

        $normalizedLines = $this->quotes->normalizeQuoteLines($data['lines'], $inquiry ?? null);
        $lineValidator = Validator::make(
            ['lines' => $normalizedLines],
            [
                'lines' => ['required', 'array', 'min:1'],
                'lines.*.product_id' => ['nullable', 'exists:products,id'],
                'lines.*.custom_title' => ['nullable', 'string', 'max:255'],
                'lines.*.metal_type' => ['nullable', 'string', 'max:100'],
                'lines.*.stone_details' => ['nullable', 'string', 'max:1000'],
                'lines.*.weight_grams' => ['required', 'numeric', 'min:0'],
                'lines.*.labor_fee_mmk' => ['nullable', 'numeric', 'min:0'],
                'lines.*.gem_price_mmk' => ['nullable', 'numeric', 'min:0'],
            ]
        );
        $lineValidator->validate();

        $quote = $this->quotes->createQuote(
            $customer,
            $gold,
            $fx,
            $normalizedLines,
            $data['inquiry_id'] ?? null,
            $data['notes_internal'] ?? null,
            isset($data['valid_until']) ? new \DateTimeImmutable($data['valid_until']) : null,
            'draft',
            isset($data['deposit_percentage'])
                ? (float) $data['deposit_percentage']
                : $this->quotes->readyMadeDefaultDepositPercentage($normalizedLines),
            isset($data['deposit_amount_mmk']) ? (float) $data['deposit_amount_mmk'] : null
        );

        if (! empty($data['inquiry_id'])) {
            Inquiry::query()->whereKey($data['inquiry_id'])->update(['status' => 'quoted']);
        }
        $this->auditLogs->log(
            action: 'admin.quote.created',
            actor: $request->user(),
            target: $quote,
            category: 'admin',
            context: [
                'inquiry_id' => $data['inquiry_id'] ?? null,
                'status' => $quote->status,
                'line_count' => count($normalizedLines),
            ],
        );

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
            $this->auditLogs->log(
                action: 'admin.quote.status_updated',
                actor: $request->user(),
                target: $quote,
                category: 'admin',
                context: [
                    'new_status' => $data['status'],
                    'inquiry_id' => $quote->inquiry_id,
                ],
            );
        }

        $payload = ['data' => $quote->fresh()->load('lineItems')];
        if ($warning !== null) {
            $payload['meta'] = ['warning' => $warning];
        }

        return response()->json($payload);
    }
}
