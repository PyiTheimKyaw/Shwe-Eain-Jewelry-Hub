<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use App\Models\User;
use App\Services\AdminAuditLogService;
use App\Services\FirebaseInquiryService;
use App\Services\InquiryQuoteCommitmentGate;
use App\Services\PricingService;
use App\Services\PushNotificationService;
use App\Services\QuoteBuilderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InquiryQuotePublishController extends Controller
{
    public function __construct(
        protected PricingService $pricing,
        protected QuoteBuilderService $quotes,
        protected FirebaseInquiryService $firebaseInquiry,
        protected AdminAuditLogService $auditLogs,
        protected PushNotificationService $push
    ) {}

    /**
     * Create a sent quote for this inquiry and publish a quote_card to Firestore.
     */
    public function __invoke(Request $request, Inquiry $inquiry): JsonResponse
    {
        $this->authorize('publishQuote', $inquiry);

        $warning = app(InquiryQuoteCommitmentGate::class)->warningForPublishing($inquiry);

        $gold = $this->pricing->currentGoldSnapshot();
        $fx = $this->pricing->currentFxSnapshot();

        if (! $gold || ! $fx) {
            return response()->json([
                'message' => 'Configure current gold price (MMK/gram) and MMK-per-USD exchange rate before publishing quotes.',
            ], 422);
        }

        $data = $request->validate([
            'valid_until' => ['nullable', 'date'],
            'notes_internal' => ['nullable', 'string'],
            'deposit_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'deposit_amount_mmk' => ['nullable', 'numeric', 'min:0'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*' => ['array'],
        ]);
        $normalizedLines = $this->quotes->normalizeQuoteLines($data['lines'], $inquiry);
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

        $customer = User::query()->findOrFail($inquiry->customer_user_id);
        if (! $customer->isCustomer()) {
            return response()->json(['message' => 'Inquiry must belong to a customer account.'], 422);
        }

        $quote = $this->quotes->createQuote(
            $customer,
            $gold,
            $fx,
            $normalizedLines,
            $inquiry->id,
            $data['notes_internal'] ?? null,
            isset($data['valid_until']) ? new \DateTimeImmutable($data['valid_until']) : null,
            'sent',
            isset($data['deposit_percentage'])
                ? (float) $data['deposit_percentage']
                : $this->quotes->readyMadeDefaultDepositPercentage($normalizedLines),
            isset($data['deposit_amount_mmk']) ? (float) $data['deposit_amount_mmk'] : null
        );

        $inquiry->update(['status' => 'quoted']);

        $quote->load(['lineItems.product', 'goldPriceSnapshot', 'exchangeRateSnapshot']);
        $this->firebaseInquiry->publishQuoteCardMessage($inquiry, $quote);
        $this->firebaseInquiry->publishTimelineLogMessage($inquiry, '--- New Quote Published ---');
        $this->firebaseInquiry->syncInquiryThreadDocument($inquiry);
        $this->push->notifyUsers(
            [$customer],
            'New quote available',
            "Your quote {$quote->quote_number} is ready for review.",
            [
                'category' => 'quote',
                'inquiry_id' => $inquiry->id,
                'quote_id' => $quote->id,
            ]
        );
        $this->auditLogs->log(
            action: 'admin.inquiry.quote_published',
            actor: $request->user(),
            target: $quote,
            category: 'admin',
            context: [
                'inquiry_id' => $inquiry->id,
                'quote_number' => $quote->quote_number,
                'line_count' => count($normalizedLines),
            ],
        );

        $payload = ['data' => $quote];
        if ($warning !== null) {
            $payload['meta'] = ['warning' => $warning];
        }

        return response()->json($payload, 201);
    }
}
