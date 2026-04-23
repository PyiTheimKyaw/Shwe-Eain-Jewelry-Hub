<?php

namespace App\Filament\Resources\Inquiries\Pages;

use App\Filament\Resources\Inquiries\InquiryResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewInquiry extends ViewRecord
{
    protected static string $resource = InquiryResource::class;

    /**
     * Re-fetch the record so status/workflow match customer actions (e.g. quote decline) without a manual refresh.
     *
     * @var array<string, string>
     */
    protected array $extraBodyAttributes = [
        'wire:poll.2s' => 'pollInquiryView',
    ];

    public function pollInquiryView(): void
    {
        $this->record = $this->resolveRecord($this->record->getKey());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('chat')
                ->label(__('Open chat'))
                ->icon('heroicon-o-chat-bubble-left-right')
                ->url(fn (): string => InquiryResource::getUrl('chat', ['record' => $this->getRecord()])),
            EditAction::make(),
        ];
    }
}
