<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsappMessageLog extends Model
{
    use HasFactory;

    protected $fillable = ['ticket_id', 'recipient', 'message', 'status', 'api_message_id', 'sent_at', 'error', 'attempts', 'metadata'];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'attempts' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function ticket(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ItTicket::class, 'ticket_id');
    }
}
