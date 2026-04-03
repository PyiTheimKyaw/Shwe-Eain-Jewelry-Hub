<?php

namespace App\Filament\Resources\Inquiries\Pages;

use App\Filament\Resources\Inquiries\InquiryResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewInquiry extends ViewRecord
{
    protected static string $resource = InquiryResource::class;

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
