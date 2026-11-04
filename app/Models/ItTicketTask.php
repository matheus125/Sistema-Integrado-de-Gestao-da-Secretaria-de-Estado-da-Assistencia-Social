<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItTicketTask extends Model
{
    use HasFactory;

    protected $fillable = ['ticket_id', 'assigned_to', 'title', 'description', 'due_at', 'status', 'priority', 'required', 'completed_at', 'completed_by'];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'required' => 'boolean', 'completed_at' => 'datetime'];
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
