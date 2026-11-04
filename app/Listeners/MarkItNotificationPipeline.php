<?php

namespace App\Listeners;

use App\Events\ItTicketNotificationCreated;
use App\Notifications\ItTicketNotification;
use Illuminate\Support\Facades\Notification;

class MarkItNotificationPipeline
{
    public function handle(ItTicketNotificationCreated $event): void
    {
        $notification = $event->notification;
        if ($notification->recipient) {
            Notification::route('mail', $notification->recipient)
                ->notify(new ItTicketNotification($notification->event, $notification->message, (int) $notification->ticket_id));
        }
        $notification->update(['metadata' => array_merge($notification->metadata ?: [], [
            'pipeline' => 'event_listener',
            'delivery' => $notification->recipient ? 'queued_mail' : 'system_only',
            'processed_at' => now()->toISOString(),
        ])]);
    }
}
