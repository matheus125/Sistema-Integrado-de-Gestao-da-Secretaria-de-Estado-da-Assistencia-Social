<?php

namespace App\Events;

use App\Models\ItNotificationLog;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ItTicketNotificationCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly ItNotificationLog $notification)
    {
    }
}
