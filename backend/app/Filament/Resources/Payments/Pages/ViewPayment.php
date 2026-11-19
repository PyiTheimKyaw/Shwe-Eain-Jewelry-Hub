<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Payment;
use App\Services\PaymentVerificationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadPdfReport')
                ->label('Download PDF report')
                ->icon('heroicon-o-document-arrow-down')
                ->url(fn (): string => route('admin.exports.payments.pdf', ['payment' => $this->getRecord()]))
                ->openUrlInNewTab(),
            Action::make('approvePayment')
                ->label('Approve Payment')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool => $this->getRecord()->status === Payment::STATUS_AWAITING_VERIFICATION)
                ->requiresConfirmation()
                ->action(function (): void {
                    /** @var Payment $payment */
                    $payment = $this->getRecord();
                    $user = Auth::user();
                    if ($user === null) {
                        return;
                    }
                    app(PaymentVerificationService::class)->confirm($user, $payment);

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
            Action::make('edit')
                ->label('Edit record')
                ->icon('heroicon-o-pencil-square')
                ->url(fn (): string => PaymentResource::getUrl('edit', ['record' => $this->getRecord()])),
        ];
    }
}
