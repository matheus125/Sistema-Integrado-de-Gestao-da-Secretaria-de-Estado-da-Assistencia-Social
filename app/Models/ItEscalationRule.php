<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItEscalationRule extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'remaining_percent', 'minutes_after_due', 'level', 'notify_technician', 'notify_manager', 'mark_overdue', 'active'];

    protected function casts(): array
    {
        return [
            'remaining_percent' => 'integer',
            'minutes_after_due' => 'integer',
            'notify_technician' => 'boolean',
            'notify_manager' => 'boolean',
            'mark_overdue' => 'boolean',
            'active' => 'boolean',
        ];
    }
}
