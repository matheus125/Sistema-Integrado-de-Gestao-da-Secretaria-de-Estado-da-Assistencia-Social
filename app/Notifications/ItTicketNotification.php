<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ItTicketNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $event,
        public readonly string $message,
        public readonly int $ticketId,
    ) {
        $this->onQueue(config('notifications.queue', 'notifications'));
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Help Desk: chamado #{$this->ticketId}")
            ->line($this->message)
            ->action('Abrir chamado', url("/informatica?ticket={$this->ticketId}"));
    }

    public function toArray(object $notifiable): array
    {
        return ['event' => $this->event, 'ticket_id' => $this->ticketId, 'message' => $this->message];
    }
}
