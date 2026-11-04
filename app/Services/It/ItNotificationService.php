<?php

namespace App\Services\It;

use App\Events\ItTicketNotificationCreated;
use App\Models\ItNotificationLog;
use App\Models\ItTicket;
use App\Models\User;

class ItNotificationService
{
    public function ticketEvent(ItTicket $ticket, string $event, string $message, ?User $recipient = null, array $metadata = []): ItNotificationLog
    {
        $notification = ItNotificationLog::create([
            'ticket_id' => $ticket->id,
            'user_id' => $recipient?->id,
            'channel' => 'system',
            'event' => $event,
            'recipient' => $recipient?->email,
            'message' => $message,
            'status' => 'registered',
            'metadata' => $metadata,
        ]);

        ItTicketNotificationCreated::dispatch($notification);

        return $notification->refresh();
    }
}
