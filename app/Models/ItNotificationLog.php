<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItNotificationLog extends Model
{
    use HasFactory;

    protected $fillable = ['ticket_id', 'user_id', 'channel', 'event', 'recipient', 'message', 'status', 'metadata', 'sent_at'];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
