<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItTicketEvaluation extends Model
{
    use HasFactory;

    protected $fillable = ['ticket_id', 'user_id', 'rating', 'comment', 'evaluated_at'];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'evaluated_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(ItTicket::class, 'ticket_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
