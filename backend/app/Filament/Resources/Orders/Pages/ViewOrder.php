<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentProfile;
use App\Models\User;
use App\Services\FirebaseInquiryService;
use App\Services\OrderSettlementService;
use App\Services\PaymentVerificationService;
use App\Services\OrderLifecycleService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

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
                ])
                ->action(function (array $data): void {
                    /** @var Order $order */
                    $order = $this->getRecord();
                    try {
                        app(OrderLifecycleService::class)->assignArtisan(
                            $order,
                            (int) $data['artisan_user_id'],
                            (string) $data['task_scope'],
                            isset($data['order_item_id']) ? (int) $data['order_item_id'] : null,
                            $data['instructions'] ?? null
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
}
