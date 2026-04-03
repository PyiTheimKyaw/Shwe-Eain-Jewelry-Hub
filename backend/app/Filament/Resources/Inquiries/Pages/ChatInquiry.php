<?php

namespace App\Filament\Resources\Inquiries\Pages;

use App\Filament\Resources\Inquiries\InquiryResource;
use App\Models\Inquiry;
use App\Services\FirebaseInquiryService;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class ChatInquiry extends Page
{
    use InteractsWithRecord;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $resource = InquiryResource::class;

    protected string $view = 'filament.resources.inquiries.chat-inquiry';

    public string $firebaseToken = '';

    public string $threadId = '';

    public bool $showChat = false;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(static::getResource()::canView($this->getRecord()), 403);

        /** @var Inquiry $inquiry */
        $inquiry = $this->getRecord();

        $this->threadId = (string) ($inquiry->firebase_thread_id ?? '');

        $firebase = app(FirebaseInquiryService::class);
        $webKey = trim((string) config('services.firebase.web_api_key'));
        $projectId = trim((string) config('services.firebase.project_id'));
        $hasWeb = $webKey !== '' && $projectId !== '';

        if ($firebase->isConfigured() && $hasWeb && $inquiry->firebase_thread_id) {
            try {
                $this->firebaseToken = $firebase->mintChatToken(auth()->user(), $inquiry);
                $this->showChat = true;
            } catch (\Throwable) {
                $this->showChat = false;
            }
        }
    }

    public function getTitle(): string|Htmlable
    {
        return __('Inquiry chat');
    }

    public function getBreadcrumb(): string
    {
        return __('Chat');
    }
}
