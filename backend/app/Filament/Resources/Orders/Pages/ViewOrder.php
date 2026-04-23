<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\User;
use App\Services\FirebaseInquiryService;
use App\Services\OrderLifecycleService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Validation\ValidationException;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('assignArtisan')
                ->label('Assign artisan')
                ->icon('heroicon-o-user-plus')
                ->visible(fn (): bool => in_array($this->getRecord()->status, ['deposit_confirmed', 'in_production'], true))
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

