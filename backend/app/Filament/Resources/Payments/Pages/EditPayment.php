<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Payment;
use App\Services\PaymentVerificationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPayment extends EditRecord
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approvePayment')
                ->label('Approve Payment')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool => $this->getRecord()->status === Payment::STATUS_AWAITING_VERIFICATION)
                ->requiresConfirmation()
                ->action(function (): void {
                    /** @var Payment $payment */
                    $payment = $this->getRecord();
                    app(PaymentVerificationService::class)->confirm(auth()->user(), $payment);

                    Notification::make()
                        ->title('Payment approved')
                        ->success()
                        ->send();

                    $this->getRecord()->refresh();
                }),
            Action::make('rejectPayment')
                ->label('Reject Payment')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (): bool => $this->getRecord()->status === Payment::STATUS_AWAITING_VERIFICATION)
                ->form([
                    Textarea::make('reason')
                        ->label('Reason')
                        ->rows(3)
                        ->required()
                        ->maxLength(255)
                        ->helperText('This note is sent to the customer in the concierge timeline.'),
                ])
                ->action(function (array $data): void {
                    /** @var Payment $payment */
                    $payment = $this->getRecord();
                    app(PaymentVerificationService::class)->reject($payment, (string) ($data['reason'] ?? ''));

                    Notification::make()
                        ->title('Payment rejected')
                        ->success()
                        ->send();

                    $this->getRecord()->refresh();
                }),
            Action::make('view')
                ->label('View record')
                ->icon('heroicon-o-eye')
                ->url(fn (): string => PaymentResource::getUrl('view', ['record' => $this->getRecord()])),
        ];
    }
}
