<?php

namespace App\Jobs;

use App\Models\ItTicket;
use App\Services\It\WhatsappOutboundService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWhatsappMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly ItTicket $ticket,
        public readonly string $recipient,
        public readonly string $message,
        public readonly string $event = 'manual',
    ) {
    }

    public function handle(WhatsappOutboundService $service): void
    {
        $service->send($this->ticket, $this->recipient, $this->message, $this->event);
    }
}
