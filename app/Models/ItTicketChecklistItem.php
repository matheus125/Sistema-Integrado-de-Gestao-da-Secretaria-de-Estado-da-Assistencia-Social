<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItTicketChecklistItem extends Model
{
    use HasFactory;

    protected $fillable = ['ticket_id', 'template_item_id', 'label', 'required', 'status', 'checked_by', 'checked_at'];

    protected function casts(): array
    {
        return ['required' => 'boolean', 'checked_at' => 'datetime'];
    }
}
