<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Notification;

class QuoteRevisedAcceptedForArtisan extends Notification
{
    public function __construct(
        public int $inquiryId,
        public Order $order
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
            'title' => 'Quote revised',
            'body' => 'A new quote was accepted for an inquiry you are assigned to. Check the order for design changes.',
            'inquiry_id' => $this->inquiryId,
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
        ];
    }
}
