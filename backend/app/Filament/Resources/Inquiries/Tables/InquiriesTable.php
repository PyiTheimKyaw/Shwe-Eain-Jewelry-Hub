<?php

namespace App\Filament\Resources\Inquiries\Tables;

use App\Models\Inquiry;
use App\Services\InquiryPipelinePresenter;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;

class InquiriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->poll('1s')
            ->columns([
                TextColumn::make('response_state')
                    ->label('Response')
                    ->badge()
                    ->getStateUsing(function (Inquiry $record): string {
                        $latestQuoteStatus = $record->quotes()->latest('id')->value('status');
                        $isUnanswered = in_array($record->status, ['open', 'reviewing'], true)
                            && ! in_array($latestQuoteStatus, ['sent', 'accepted'], true)
                            && $record->created_at?->diffInHours(now()) >= 2;

                        return $isUnanswered ? 'Unanswered' : 'Responded';
                    })
                    ->color(fn (string $state): string => $state === 'Unanswered' ? 'danger' : 'success'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(function (Inquiry $record): string {
                        $pipeline = app(InquiryPipelinePresenter::class)->present($record);
                        $step = (string) ($pipeline['step'] ?? 'consulting');

                        return match ($step) {
                            InquiryPipelinePresenter::STEP_QUOTED => 'quoted',
                            InquiryPipelinePresenter::STEP_AWAITING_DEPOSIT => 'awaiting_deposit',
                            InquiryPipelinePresenter::STEP_PRE_PRODUCTION => 'deposit_confirmed',
                            InquiryPipelinePresenter::STEP_IN_PRODUCTION => 'in_production',
                            InquiryPipelinePresenter::STEP_PENDING_QC => 'pending_qc',
                            InquiryPipelinePresenter::STEP_READY => 'ready',
                            default => 'consulting',
                        };
                    })
                    ->formatStateUsing(fn (string $state): string => str($state)->replace('_', ' ')->title())
                    ->color(fn (string $state): string => match ($state) {
                        'quoted' => 'warning',
                        'awaiting_deposit' => 'warning',
                        'deposit_confirmed' => 'info',
                        'in_production' => 'primary',
                        'pending_qc' => 'gray',
                        'ready' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('quote_status')
                    ->label('Quote')
                    ->badge()
                    ->getStateUsing(fn (Inquiry $record): string => ucfirst((string) ($record->quotes()->latest('id')->value('status') ?? 'none')))
                    ->color(fn (string $state): string => match (strtolower($state)) {
                        'draft' => 'gray',
                        'sent' => 'warning',
                        'accepted' => 'success',
                        'void', 'expired' => 'danger',
                        default => 'info',
                    }),
                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('message')
                    ->limit(40)
                    ->tooltip(fn ($state) => $state),
                TextColumn::make('product.title')
                    ->label('Product')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('id')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Filter::make('status')
                    ->form([
                        Select::make('status')
                            ->options([
                                'open' => 'Open',
                                'reviewing' => 'Reviewing',
                                'quoted' => 'Quoted',
                                'in_production' => 'In production',
                                'ready' => 'Ready',
                                'closed' => 'Closed',
                            ]),
                    ])
                    ->query(function ($query, array $data) {
                        if (! empty($data['status'])) {
                            $query->where('status', $data['status']);
                        }

                        return $query;
                    }),
                Filter::make('created_window')
                    ->label('Created window')
                    ->form([
                        DatePicker::make('created_from')->native(false),
                        DatePicker::make('created_until')->native(false),
                    ])
                    ->query(function ($query, array $data) {
                        if (! empty($data['created_from'])) {
                            $query->whereDate('created_at', '>=', $data['created_from']);
                        }
                        if (! empty($data['created_until'])) {
                            $query->whereDate('created_at', '<=', $data['created_until']);
                        }

                        return $query;
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    //
                ]),
            ]);
    }
}
