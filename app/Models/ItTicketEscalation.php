<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItTicketEscalation extends Model
{
    use HasFactory;

    protected $fillable = ['ticket_id', 'rule_id', 'level', 'status', 'escalated_at', 'metadata'];

    protected function casts(): array
    {
        return ['escalated_at' => 'datetime', 'metadata' => 'array'];
    }
}
