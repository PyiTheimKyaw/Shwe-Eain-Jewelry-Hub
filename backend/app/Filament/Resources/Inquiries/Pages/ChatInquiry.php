<?php

namespace App\Filament\Resources\Inquiries\Pages;

use App\Filament\Resources\Inquiries\InquiryResource;
use App\Models\Inquiry;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentProfile;
use App\Models\Quote;
use App\Models\User;
use App\Services\AdminAuditLogService;
use App\Services\FirebaseInquiryService;
use App\Services\InquiryPipelinePresenter;
use App\Services\InquiryQuoteCommitmentGate;
use App\Services\PricingService;
use App\Services\QuoteBuilderService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class ChatInquiry extends Page
{
    use InteractsWithRecord;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $resource = InquiryResource::class;

    protected string $view = 'filament.resources.inquiries.chat-inquiry';

    public string $firebaseToken = '';

    public string $threadId = '';

    public bool $showChat = false;

    /** @var array<string, mixed> */
    public array $pipeline = [];

    /** @var array<int, array{weight_grams: string, labor_fee_mmk: string, gem_price_mmk: string, product_id: string, custom_title: string, metal_type: string, stone_details: string}> */
    public array $quoteFormLines = [];

    public ?string $quoteValidUntil = null;

    public ?string $quoteNotes = null;

    public ?int $selectedPaymentProfileId = null;

    /**
     * Active quote card data for admin chat (MySQL), same shape as Firestore quote_card.
     *
     * @var array<string, mixed>|null
     */
    public ?array $adminQuoteCardPayload = null;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(static::getResource()::canView($this->getRecord()), 403);

        /** @var Inquiry $inquiry */
        $inquiry = $this->getRecord();

        $this->threadId = (string) ($inquiry->firebase_thread_id ?? '');

        $this->quoteFormLines = [
            [
                'weight_grams' => '',
                'labor_fee_mmk' => '',
                'gem_price_mmk' => '',
                'product_id' => '',
                'custom_title' => '',
                'metal_type' => '',
                'stone_details' => '',
            ],
        ];

        $this->refreshPipeline();
        $this->dispatch('jh-admin-static-refresh');

        $firebase = app(FirebaseInquiryService::class);
        $webKey = trim((string) config('services.firebase.web_api_key'));
        $projectId = trim((string) config('services.firebase.project_id'));
        $hasWeb = $webKey !== '' && $projectId !== '';

        if ($firebase->isConfigured() && $hasWeb && $inquiry->firebase_thread_id) {
            try {
                $user = Auth::user();
                if ($user !== null) {
                    $this->firebaseToken = $firebase->mintChatToken($user, $inquiry);
                    $this->showChat = true;
                }
            } catch (\Throwable) {
                $this->showChat = false;
            }
        }

        $this->selectedPaymentProfileId = PaymentProfile::query()
            ->active()
            ->ordered()
            ->value('id');
    }

    public function refreshPipeline(): void
    {
        /** @var Inquiry $inquiry */
        $inquiry = $this->getRecord();
        $inquiry->refresh();
        $this->pipeline = app(InquiryPipelinePresenter::class)->present($inquiry);
        $this->adminQuoteCardPayload = $this->buildAdminQuoteCardPayload($inquiry);
    }

    public function pollChatSync(): void
    {
        $this->refreshPipeline();
        $this->dispatch('jh-admin-static-refresh');
    }

    /** Informational: confirmed deposit exists (publish is still allowed). */
    public function getQuotePublishWarningProperty(): ?string
    {
        return app(InquiryQuoteCommitmentGate::class)->warningForPublishing($this->getRecord());
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function buildAdminQuoteCardPayload(Inquiry $inquiry): ?array
    {
        $qid = $this->pipeline['active_quote_id'] ?? null;
        if ($qid === null) {
            return null;
        }

        $quote = Quote::query()->with('lineItems')->find($qid);
        if ($quote === null || ! in_array($quote->status, ['sent', 'accepted'], true)) {
            return null;
        }

        $lines = [];
        foreach ($quote->lineItems as $line) {
            $lines[] = [
                'title' => (string) ($line->custom_title ?? 'Item'),
                'metal_mmk' => (string) $line->metal_value_mmk,
                'labor_mmk' => (string) $line->labor_fee_mmk,
                'gems_mmk' => (string) $line->gem_price_mmk,
                'line_total_mmk' => (string) $line->line_total_mmk,
            ];
        }

        return [
            'type' => 'quote_card',
            'sender_role' => 'admin',
            'quote_id' => $quote->id,
            'quote_number' => $quote->quote_number,
            'total_mmk' => (string) $quote->total_mmk,
            'lines' => $lines,
        ];
    }

    public function addQuoteLine(): void
    {
        $this->quoteFormLines[] = [
            'weight_grams' => '',
            'labor_fee_mmk' => '',
            'gem_price_mmk' => '',
            'product_id' => '',
            'custom_title' => '',
            'metal_type' => '',
            'stone_details' => '',
        ];
    }

    public function removeQuoteLine(int $index): void
    {
        if (count($this->quoteFormLines) <= 1) {
            return;
        }
        unset($this->quoteFormLines[$index]);
        $this->quoteFormLines = array_values($this->quoteFormLines);
    }

    public function publishQuoteFromConsole(): void
    {
        /** @var Inquiry $inquiry */
        $inquiry = $this->getRecord();
        Gate::authorize('publishQuote', $inquiry);

        $publishWarning = app(InquiryQuoteCommitmentGate::class)->warningForPublishing($inquiry);

        foreach ($this->quoteFormLines as $k => $row) {
            if (isset($row['product_id']) && $row['product_id'] === '') {
                $this->quoteFormLines[$k]['product_id'] = null;
            }
            if (isset($row['labor_fee_mmk']) && $row['labor_fee_mmk'] === '') {
                $this->quoteFormLines[$k]['labor_fee_mmk'] = null;
            }
            if (isset($row['gem_price_mmk']) && $row['gem_price_mmk'] === '') {
                $this->quoteFormLines[$k]['gem_price_mmk'] = null;
            }
            if (isset($row['custom_title']) && $row['custom_title'] === '') {
                $this->quoteFormLines[$k]['custom_title'] = null;
            }
            if (isset($row['metal_type']) && $row['metal_type'] === '') {
                $this->quoteFormLines[$k]['metal_type'] = null;
            }
            if (isset($row['stone_details']) && $row['stone_details'] === '') {
                $this->quoteFormLines[$k]['stone_details'] = null;
            }
        }
        if ($this->quoteValidUntil === '') {
            $this->quoteValidUntil = null;
        }
        if ($this->quoteNotes === '') {
            $this->quoteNotes = null;
        }

        $pricing = app(PricingService::class);
        $gold = $pricing->currentGoldSnapshot();
        $fx = $pricing->currentFxSnapshot();

        if (! $gold || ! $fx) {
            Notification::make()
                ->title(__('Configure gold and FX rates in Pricing first'))
                ->danger()
                ->send();

            return;
        }

        $validator = Validator::make([
            'quoteFormLines' => $this->quoteFormLines,
            'quoteValidUntil' => $this->quoteValidUntil,
            'quoteNotes' => $this->quoteNotes,
        ], [
            'quoteFormLines' => ['required', 'array', 'min:1'],
            'quoteFormLines.*.weight_grams' => ['required', 'numeric', 'min:0'],
            'quoteFormLines.*.labor_fee_mmk' => ['nullable', 'numeric', 'min:0'],
            'quoteFormLines.*.gem_price_mmk' => ['nullable', 'numeric', 'min:0'],
            'quoteFormLines.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'quoteFormLines.*.custom_title' => ['nullable', 'string', 'max:255'],
            'quoteFormLines.*.metal_type' => ['nullable', 'string', 'max:100'],
            'quoteFormLines.*.stone_details' => ['nullable', 'string', 'max:1000'],
            'quoteValidUntil' => ['nullable', 'date'],
            'quoteNotes' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            Notification::make()
                ->title(__('Quote form has invalid values'))
                ->body((string) $validator->errors()->first())
                ->danger()
                ->send();

            $this->js('document.getElementById("jh-dialog-quote")?.showModal();');

            return;
        }

        $validated = $validator->validated();

        $lines = [];
        foreach ($validated['quoteFormLines'] as $row) {
            $lines[] = [
                'weight_grams' => $row['weight_grams'],
                'labor_fee_mmk' => $row['labor_fee_mmk'] ?? null,
                'gem_price_mmk' => $row['gem_price_mmk'] ?? null,
                'product_id' => isset($row['product_id']) && $row['product_id'] !== '' && $row['product_id'] !== null
                    ? (int) $row['product_id']
                    : null,
                'custom_title' => $row['custom_title'] ?? null,
                'metal_type' => $row['metal_type'] ?? null,
                'stone_details' => $row['stone_details'] ?? null,
            ];
        }

        $customer = User::query()->findOrFail($inquiry->customer_user_id);
        if (! $customer->isCustomer()) {
            Notification::make()->title(__('Invalid customer on inquiry'))->danger()->send();

            return;
        }

        try {
            $quote = app(QuoteBuilderService::class)->createQuote(
                $customer,
                $gold,
                $fx,
                $lines,
                $inquiry->id,
                $validated['quoteNotes'] ?? null,
                isset($validated['quoteValidUntil']) ? new \DateTimeImmutable($validated['quoteValidUntil']) : null,
                'sent'
            );

            $inquiry->update(['status' => 'quoted']);

            $quote->load(['lineItems.product', 'goldPriceSnapshot', 'exchangeRateSnapshot']);
            $firebase = app(FirebaseInquiryService::class);
            $firebase->publishQuoteCardMessage($inquiry, $quote);
            $firebase->publishTimelineLogMessage($inquiry, '--- New Quote Published ---');
            $firebase->syncInquiryThreadDocument($inquiry);
            app(AdminAuditLogService::class)->log(
                action: 'admin.inquiry.quote_published',
                actor: Auth::user(),
                target: $quote,
                category: 'admin',
                context: [
                    'inquiry_id' => $inquiry->id,
                    'quote_number' => $quote->quote_number,
                    'line_count' => count($lines),
                ],
            );
        } catch (\Throwable $e) {
            report($e);
            Notification::make()
                ->title(__('Failed to publish quote'))
                ->body($e->getMessage())
                ->danger()
                ->send();
            $this->js('document.getElementById("jh-dialog-quote")?.showModal();');

            return;
        }

        $this->refreshPipeline();
        $this->dispatch('jh-admin-static-refresh');

        if ($publishWarning !== null) {
            Notification::make()
                ->title($publishWarning)
                ->warning()
                ->send();
        }

        Notification::make()
            ->title(__('Quote sent to the customer chat'))
            ->success()
            ->send();

        $this->js(<<<'JS'
            document.getElementById("jh-dialog-quote")?.close();
            [0, 200, 600].forEach(function (ms) {
                setTimeout(function () {
                    window.jhAdminChatRefreshStatic?.();
                }, ms);
            });
            JS);

    }

    public function sendPaymentInstructionsToChat(): void
    {
        /** @var Inquiry $inquiry */
        $inquiry = $this->getRecord();

        Gate::authorize('update', $inquiry);

        if (! PaymentProfile::query()->active()->exists()) {
            Notification::make()
                ->title(__('Create an active payment profile first'))
                ->body(__('Go to Settings → Payment profiles.'))
                ->warning()
                ->send();

            return;
        }

        $this->validate([
            'selectedPaymentProfileId' => ['required', 'integer', 'exists:payment_profiles,id'],
        ]);

        $profile = PaymentProfile::query()
            ->where('is_active', true)
            ->whereKey($this->selectedPaymentProfileId)
            ->first();

        if ($profile === null) {
            Notification::make()
                ->title(__('Select a valid payment profile'))
                ->danger()
                ->send();

            return;
        }

        $order = Order::query()
            ->whereHas('quote', fn ($q) => $q->where('inquiry_id', $inquiry->id))
            ->orderByDesc('id')
            ->first();

        if ($order === null) {
            Notification::make()
                ->title(__('No order linked to this inquiry yet'))
                ->warning()
                ->send();

            return;
        }

        Gate::authorize('sendPaymentInstructions', $order);

        if ($order->status !== 'awaiting_deposit') {
            Notification::make()
                ->title(__('Order is not awaiting deposit'))
                ->warning()
                ->send();

            return;
        }

        if ($order->deposit_amount_mmk === null) {
            Notification::make()
                ->title(__('Order has no deposit amount'))
                ->danger()
                ->send();

            return;
        }

        $existingPayment = Payment::query()
            ->where('order_id', $order->id)
            ->where('type', Payment::TYPE_DEPOSIT)
            ->first();
        if ($existingPayment !== null &&
            ! in_array($existingPayment->status, [Payment::STATUS_PENDING_PROOF, Payment::STATUS_REJECTED], true)
        ) {
            Notification::make()
                ->title(__('Cannot resend payment instructions'))
                ->body(__('Deposit verification is already in progress or confirmed.'))
                ->warning()
                ->send();

            return;
        }

        $order->deposit_instructions_sent_at = now();
        $order->save();

        Payment::query()->updateOrCreate(
            [
                'order_id' => $order->id,
                'type' => Payment::TYPE_DEPOSIT,
            ],
            [
                'payment_profile_id' => $profile->id,
                'amount_mmk' => $order->deposit_amount_mmk,
                'currency' => 'MMK',
                'status' => Payment::STATUS_PENDING_PROOF,
            ]
        );

        $firebase = app(FirebaseInquiryService::class);
        $firebase->publishPaymentInfoCard(
            $inquiry,
            $order->fresh(),
            $profile
        );
        $firebase->publishTimelineLogMessage($inquiry, '--- Payment Instructions Provided ---');
        $firebase->syncInquiryThreadDocument($inquiry);
        app(AdminAuditLogService::class)->log(
            action: 'admin.order.payment_instructions_sent',
            actor: Auth::user(),
            target: $order,
            category: 'workflow',
            context: [
                'inquiry_id' => $inquiry->id,
                'payment_profile_id' => $profile->id,
                'deposit_amount_mmk' => (string) $order->deposit_amount_mmk,
            ],
        );

        $this->refreshPipeline();

        Notification::make()
            ->title(__('Payment instructions sent to chat'))
            ->success()
            ->send();

        $this->js(<<<'JS'
            document.getElementById("jh-dialog-payment")?.close();
            [0, 200, 600].forEach(function (ms) {
                setTimeout(function () {
                    window.jhAdminChatRefreshStatic?.();
                }, ms);
            });
            JS);

    }

    public function getTitle(): string|Htmlable
    {
        return __('Concierge chat');
    }

    public function getBreadcrumb(): string
    {
        return __('Chat');
    }
}
