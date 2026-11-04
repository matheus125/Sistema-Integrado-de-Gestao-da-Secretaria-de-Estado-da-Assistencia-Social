<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItTicketRelationship extends Model
{
    use HasFactory;

    protected $fillable = ['ticket_id', 'related_ticket_id', 'type', 'created_by', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}
