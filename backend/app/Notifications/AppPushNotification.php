<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class AppPushNotification extends Notification
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public string $title,
        public string $body,
        public array $data = []
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            ...$this->data,
        ];
    }
}
