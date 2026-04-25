<?php

namespace App\Services;

use App\Models\PushDeviceToken;
use App\Notifications\AppPushNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\CloudMessage;

class PushNotificationService
{
    /** @param  array<string, string>  $data */
    public function notifyUsers(iterable $users, string $title, string $body, array $data = []): void
    {
        $usersCollection = collect($users)->filter();
        if ($usersCollection->isEmpty()) {
            return;
        }

        Notification::send($usersCollection, new AppPushNotification($title, $body, $data));

        $userIds = $usersCollection->pluck('id')->filter()->values();
        if ($userIds->isEmpty()) {
            return;
        }

        $tokens = PushDeviceToken::query()
            ->whereIn('user_id', $userIds)
            ->where('is_active', true)
            ->pluck('token')
            ->unique()
            ->values();

        if ($tokens->isEmpty()) {
            return;
        }

        $messaging = app(FirebaseInquiryService::class)->messaging();
        if ($messaging === null) {
            return;
        }

        foreach ($tokens as $token) {
            try {
                $message = CloudMessage::fromArray([
                    'token' => (string) $token,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => $this->stringifyData($data),
                ]);

                $messaging->send($message);
            } catch (MessagingException|\Throwable $e) {
                Log::warning('push_notification.send_failed', [
                    'token' => substr((string) $token, 0, 12).'***',
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    protected function stringifyData(array $data): array
    {
        $payload = [];
        foreach ($data as $key => $value) {
            $payload[(string) $key] = is_scalar($value)
                ? (string) $value
                : (json_encode($value) ?: '');
        }

        return $payload;
    }
}
