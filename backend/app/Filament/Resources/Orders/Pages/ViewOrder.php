<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Inquiry;
use App\Models\InquiryAttachment;
use App\Models\InquiryImageCandidate;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentProfile;
use App\Models\Quote;
use App\Models\User;
use App\Services\FirebaseInquiryService;
use App\Services\InquiryImageCandidateService;
use App\Services\MediaStorageService;
use App\Services\OrderSettlementService;
use App\Services\PaymentVerificationService;
use App\Services\OrderLifecycleService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadPdfReport')
                ->label('Download PDF report')
                ->icon('heroicon-o-document-arrow-down')
                ->url(fn (): string => route('admin.exports.orders.pdf', ['order' => $this->getRecord()]))
                ->openUrlInNewTab(),
            Action::make('openLinkedProduct')
                ->label('Open linked product')
                ->icon('heroicon-o-cube')
                ->visible(function (): bool {
                    /** @var Order $order */
                    $order = $this->getRecord();

                    return $order->items()
                        ->whereNotNull('product_id')
                        ->exists();
                })
                ->url(function (): string {
                    /** @var Order $order */
                    $order = $this->getRecord();
                    $productId = (int) ($order->items()
                        ->whereNotNull('product_id')
                        ->orderByDesc('id')
                        ->value('product_id') ?? 0);

                    return ProductResource::getUrl('edit', ['record' => $productId]);
                })
                ->openUrlInNewTab(),
            Action::make('downloadFinalReceipt')
                ->label('Download final receipt')
                ->icon('heroicon-o-document-check')
                ->visible(function (): bool {
                    /** @var Order $order */
                    $order = $this->getRecord();

                    return $order->receipts()
                        ->get()
                        ->contains(fn ($r): bool => (string) ($r->meta_json['payment_type'] ?? '') === Payment::TYPE_FINAL);
                })
                ->url(fn (): string => route('admin.exports.orders.final-receipt', ['order' => $this->getRecord()]))
                ->openUrlInNewTab(),
            Action::make('recordFinalPayment')
                ->label('Record final payment')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(function (): bool {
                    /** @var Order $order */
                    $order = $this->getRecord();

                    if ($order->isReadyMadeFulfillment()) {
                        return in_array($order->status, ['awaiting_final_settlement', 'ready'], true);
                    }

                    return $order->status === 'ready';
                })
                ->disabled(function (): bool {
                    /** @var Order $order */
                    $order = $this->getRecord();
                    $remaining = app(OrderSettlementService::class)->remainingBalanceFromDepositsMmk($order);

                    return $remaining <= 0;
                })
                ->form([
                    TextInput::make('amount_mmk')
                        ->label('Final payment amount (MMK)')
                        ->numeric()
                        ->default(function (): string {
                            /** @var Order $order */
                            $order = $this->getRecord();
                            $remaining = app(OrderSettlementService::class)->remainingBalanceFromDepositsMmk($order);

                            return number_format($remaining, 4, '.', '');
                        })
                        ->required(),
                    Select::make('payment_profile_id')
                        ->label('Payment profile')
                        ->options(
                            PaymentProfile::query()
                                ->active()
                                ->ordered()
                                ->pluck('name', 'id')
                                ->all()
                        )
                        ->searchable()
                        ->required(fn (Get $get): bool => ($get('payment_method') ?? 'profile') !== 'cash')
                        ->visible(fn (Get $get): bool => ($get('payment_method') ?? 'profile') !== 'cash'),
                    Select::make('payment_method')
                        ->label('Collection method')
                        ->options([
                            'profile' => 'Bank / Wallet Transfer',
                            'cash' => 'Cash Payment',
                        ])
                        ->default('profile')
                        ->required()
                        ->native(false)
                        ->live()
                        ->helperText('Choose Cash Payment for in-store cash collection.'),
                    Textarea::make('notes_internal')
                        ->label('Internal notes')
                        ->rows(3),
                    TextInput::make('settlement_notice')
                        ->label('Settlement status')
                        ->default(function (): string {
                            /** @var Order $order */
                            $order = $this->getRecord();
                            $remaining = app(OrderSettlementService::class)->remainingBalanceFromDepositsMmk($order);

                            if ($remaining <= 0) {
                                return 'This order is already fully settled.';
                            }

                            return 'Remaining settlement balance: '.number_format($remaining, 2).' MMK';
                        })
                        ->disabled()
                        ->dehydrated(false),
                ])
                ->action(function (array $data): void {
                    /** @var Order $order */
                    $order = $this->getRecord();
                    $actor = Auth::user();
                    if ($actor === null) {
                        return;
                    }
                    $allowedStatuses = $order->isReadyMadeFulfillment()
                        ? ['awaiting_final_settlement', 'ready']
                        : ['ready'];
                    if (! in_array($order->status, $allowedStatuses, true)) {
                        Notification::make()
                            ->title('Order is not ready for pickup')
                            ->warning()
                            ->send();

                        return;
                    }

                    $remaining = app(OrderSettlementService::class)->remainingBalanceFromDepositsMmk($order);
                    if ($remaining <= 0) {
                        Notification::make()
                            ->title('Order already fully settled')
                            ->body('No additional final payment is required for this order.')
                            ->warning()
                            ->send();

                        return;
                    }

                    $payment = Payment::create([
                        'order_id' => $order->id,
                        'payment_profile_id' => (($data['payment_method'] ?? 'profile') === 'cash')
                            ? null
                            : (int) $data['payment_profile_id'],
                        'type' => Payment::TYPE_FINAL,
                        'amount_mmk' => (float) $data['amount_mmk'],
                        'currency' => 'MMK',
                        'status' => Payment::STATUS_AWAITING_VERIFICATION,
                        'submitted_at' => now(),
                        'notes_internal' => $data['notes_internal'] ?? null,
                    ]);

                    app(PaymentVerificationService::class)->confirm($actor, $payment);

                    Notification::make()
                        ->title('Final payment recorded and verified')
                        ->success()
                        ->send();

                    $this->getRecord()->refresh();
                }),
            Action::make('markReadyForPickup')
                ->label('Mark as Ready for Pickup')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(function (): bool {
                    /** @var Order $order */
                    $order = $this->getRecord();
                    if (! $order->isReadyMadeFulfillment()) {
                        return false;
                    }
                    if (! in_array($order->status, ['awaiting_final_settlement', 'deposit_confirmed'], true)) {
                        return false;
                    }

                    return app(OrderSettlementService::class)->remainingBalanceMmk($order) <= 0;
                })
                ->requiresConfirmation()
                ->action(function (): void {
                    /** @var Order $order */
                    $order = $this->getRecord();
                    try {
                        app(OrderLifecycleService::class)->markReadyForPickup($order);
                        Notification::make()
                            ->title('Ready-made order marked as ready for pickup')
                            ->success()
                            ->send();
                    } catch (ValidationException $e) {
                        Notification::make()
                            ->title('Cannot mark ready')
                            ->body((string) $e->getMessage())
                            ->danger()
                            ->send();
                    }
                    $this->getRecord()->refresh();
                }),
            Action::make('assignArtisan')
                ->label('Assign artisan')
                ->icon('heroicon-o-user-plus')
                ->visible(function (): bool {
                    /** @var Order $order */
                    $order = $this->getRecord();

                    return ! $order->isReadyMadeFulfillment()
                        && in_array($order->status, ['deposit_confirmed', 'in_production'], true);
                })
                ->form([
                    Select::make('artisan_user_id')
                        ->label('Artisan')
                        ->options(
                            User::query()
                                ->where('role', 'artisan')
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all()
                        )
                        ->searchable()
                        ->required(),
                    Select::make('task_scope')
                        ->options([
                            'full_order' => 'Full order',
                            'line_item' => 'Line item',
                        ])
                        ->default('full_order')
                        ->required(),
                    Select::make('order_item_id')
                        ->label('Order item')
                        ->options(fn (): array => $this->getRecord()->items()->pluck('id', 'id')->all())
                        ->visible(fn (callable $get): bool => $get('task_scope') === 'line_item')
                        ->required(fn (callable $get): bool => $get('task_scope') === 'line_item'),
                    Textarea::make('instructions')
                        ->label('Special instructions')
                        ->required(fn (callable $get): bool => $get('task_scope') === 'line_item')
                        ->rows(3),
                    DateTimePicker::make('target_deadline_at')
                        ->label('Target deadline')
                        ->seconds(false),
                    Select::make('priority_level')
                        ->label('Priority')
                        ->options([
                            'low' => 'Low',
                            'normal' => 'Normal',
                            'high' => 'High',
                            'urgent' => 'Urgent',
                        ])
                        ->default('normal')
                        ->required(),
                    CheckboxList::make('reference_image_ids')
                        ->label('Reference images for artisan')
                        ->options(function (): array {
                            /** @var Order $order */
                            $order = $this->getRecord();
                            $inquiry = $this->resolveInquiry($order);
                            if ($inquiry === null) {
                                return [];
                            }
                            $this->syncInitialAttachmentsToCandidates($inquiry);

                            return InquiryImageCandidate::query()
                                ->where('inquiry_id', $inquiry->id)
                                ->orderByDesc('uploaded_at')
                                ->orderByDesc('id')
                                ->limit(24)
                                ->get()
                                ->mapWithKeys(function (InquiryImageCandidate $candidate): array {
                                    $when = $candidate->uploaded_at?->format('m/d H:i') ?? '-';
                                    $role = strtoupper((string) ($candidate->sender_role ?? 'unknown'));
                                    $source = str_replace('_', ' ', (string) $candidate->source);
                                    $url = (string) ($candidate->url ?? '');
                                    if ($url === '' && filled($candidate->storage_path)) {
                                        $url = app(MediaStorageService::class)->publicUrl((string) $candidate->storage_path);
                                    }
                                    $safeUrl = e($url);

                                    return [
                                        $candidate->id => new HtmlString(
                                            '<div style="display:flex; flex-direction:column; gap:6px;">'
                                            .'<div style="width:100%; aspect-ratio:1/1; border-radius:8px; overflow:hidden; border:1px solid rgba(212,175,55,.35); background:rgba(30,41,59,.6);">'
                                            .'<img src="'.$safeUrl.'" alt="reference '.$candidate->id.'" style="width:100%; height:100%; object-fit:cover;" />'
                                            .'</div>'
                                            .'<div style="font-size:11px; line-height:1.2;">#'.$candidate->id.' · '.$role.' · '.$when.'</div>'
                                            .'<div style="font-size:10px; opacity:.8; text-transform:capitalize;">'.$source.'</div>'
                                            .'</div>'
                                        ),
                                    ];
                                })
                                ->all();
                        })
                        ->default(function (): array {
                            /** @var Order $order */
                            $order = $this->getRecord();
                            $inquiry = $this->resolveInquiry($order);
                            if ($inquiry === null) {
                                return [];
                            }
                            $this->syncInitialAttachmentsToCandidates($inquiry);

                            return InquiryImageCandidate::query()
                                ->where('inquiry_id', $inquiry->id)
                                ->where('sender_role', 'customer')
                                ->orderByDesc('uploaded_at')
                                ->orderByDesc('id')
                                ->limit(4)
                                ->pluck('id')
                                ->map(fn ($id): int => (int) $id)
                                ->all();
                        })
                        ->columns(4)
                        ->allowHtml()
                        ->helperText('Click thumbnails to select references. Recent customer images are pre-selected.')
                        ->searchable(function (): bool {
                            /** @var Order $order */
                            $order = $this->getRecord();
                            $inquiry = $this->resolveInquiry($order);
                            if ($inquiry === null) {
                                return false;
                            }
                            $this->syncInitialAttachmentsToCandidates($inquiry);

                            return InquiryImageCandidate::query()
                                ->where('inquiry_id', $inquiry->id)
                                ->count() > 10;
                        }),
                    FileUpload::make('new_reference_uploads')
                        ->label('Upload New')
                        ->helperText('Add blueprint/reference images from computer.')
                        ->image()
                        ->multiple()
                        ->maxFiles(6)
                        ->storeFiles(false),
                ])
                ->action(function (array $data): void {
                    /** @var Order $order */
                    $order = $this->getRecord();
                    try {
                        $referenceImageIds = array_values(array_map('intval', $data['reference_image_ids'] ?? []));
                        $uploads = $data['new_reference_uploads'] ?? [];
                        $inquiry = $this->resolveInquiry($order);
                        if ($inquiry !== null && is_array($uploads) && $uploads !== []) {
                            $media = app(MediaStorageService::class);
                            $candidateService = app(InquiryImageCandidateService::class);
                            foreach ($uploads as $upload) {
                                if (! $upload instanceof TemporaryUploadedFile) {
                                    continue;
                                }
                                $stored = $media->storeImage(
                                    $upload,
                                    'uploads/inquiry-chat-'.$inquiry->id,
                                );
                                $candidate = $candidateService->createFromUpload(
                                    inquiry: $inquiry,
                                    source: 'assignment_modal_upload',
                                    senderRole: 'admin',
                                    storagePath: $stored['storage_path'] ?? null,
                                    url: $stored['url'] ?? null,
                                    metadata: [
                                        'upload_via' => 'assign_artisan_modal',
                                        'order_id' => $order->id,
                                    ],
                                );
                                $referenceImageIds[] = (int) $candidate->id;
                            }
                            $referenceImageIds = array_values(array_unique($referenceImageIds));
                        }

                        app(OrderLifecycleService::class)->assignArtisan(
                            $order,
                            (int) $data['artisan_user_id'],
                            (string) $data['task_scope'],
                            isset($data['order_item_id']) ? (int) $data['order_item_id'] : null,
                            $data['instructions'] ?? null,
                            $data['target_deadline_at'] ?? null,
                            $data['priority_level'] ?? 'normal',
                            $referenceImageIds
                        );
                        Notification::make()
                            ->title('Artisan assigned')
                            ->success()
                            ->send();
                    } catch (ValidationException $e) {
                        Notification::make()
                            ->title('Cannot assign artisan')
                            ->body((string) $e->getMessage())
                            ->danger()
                            ->send();
                    }
                    $this->getRecord()->refresh();
                }),
            Action::make('qcApprove')
                ->label('QC approve')
                ->icon('heroicon-o-shield-check')
                ->color('success')
                ->visible(fn (): bool => $this->getRecord()->status === 'pending_qc')
                ->requiresConfirmation()
                ->action(function (): void {
                    /** @var Order $order */
                    $order = $this->getRecord();
                    try {
                        $approved = app(OrderLifecycleService::class)->approveQc($order);
                        $inquiry = $approved->resolveInquiry();
                        if ($inquiry !== null) {
                            $firebase = app(FirebaseInquiryService::class);
                            $firebase->publishSystemTextMessage(
                                $inquiry,
                                'Your order has passed Quality Control and is now Ready for Collection!'
                            );
                            $firebase->syncInquiryThreadDocument($inquiry);
                        }
                        Notification::make()
                            ->title('Order is ready')
                            ->success()
                            ->send();
                    } catch (ValidationException $e) {
                        Notification::make()
                            ->title('QC approve blocked')
                            ->body((string) $e->getMessage())
                            ->danger()
                            ->send();
                    }
                    $this->getRecord()->refresh();
                }),
        ];
    }

    private function resolveInquiryId(Order $order): ?int
    {
        if ($order->quote?->inquiry_id !== null) {
            return (int) $order->quote->inquiry_id;
        }
        if ($order->quote_id === null) {
            return null;
        }

        $id = Quote::query()
            ->whereKey($order->quote_id)
            ->value('inquiry_id');

        return is_numeric($id) ? (int) $id : null;
    }

    private function resolveInquiry(Order $order): ?Inquiry
    {
        $order->loadMissing('quote.inquiry');
        if ($order->quote?->inquiry instanceof Inquiry) {
            return $order->quote->inquiry;
        }

        $inquiryId = $this->resolveInquiryId($order);
        if ($inquiryId === null) {
            return null;
        }

        return Inquiry::query()->find($inquiryId);
    }

    private function syncInitialAttachmentsToCandidates(Inquiry $inquiry): void
    {
        $attachments = InquiryAttachment::query()
            ->where('inquiry_id', $inquiry->id)
            ->get(['id', 'storage_path', 'created_at']);
        if ($attachments->isEmpty()) {
            return;
        }

        $existingPaths = InquiryImageCandidate::query()
            ->where('inquiry_id', $inquiry->id)
            ->whereNotNull('storage_path')
            ->pluck('storage_path')
            ->map(fn ($path): string => (string) $path)
            ->all();
        $existingPathLookup = array_fill_keys($existingPaths, true);

        foreach ($attachments as $attachment) {
            $path = (string) ($attachment->storage_path ?? '');
            if ($path === '' || isset($existingPathLookup[$path])) {
                continue;
            }

            InquiryImageCandidate::query()->create([
                'inquiry_id' => $inquiry->id,
                'source' => 'initial_inquiry_attachment',
                'sender_role' => 'customer',
                'storage_path' => $path,
                'url' => app(MediaStorageService::class)->publicUrl($path),
                'metadata_json' => [
                    'origin' => 'inquiry_attachments',
                    'inquiry_attachment_id' => $attachment->id,
                ],
                'uploaded_at' => $attachment->created_at ?? now(),
            ]);
        }
    }
}
