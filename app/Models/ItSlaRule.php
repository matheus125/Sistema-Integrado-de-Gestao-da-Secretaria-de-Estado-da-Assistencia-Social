<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItSlaRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'calendar_id',
        'name',
        'priority',
        'category_id',
        'type',
        'department_id',
        'first_response_minutes',
        'start_minutes',
        'resolution_minutes',
        'rule_priority',
        'is_fallback',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'first_response_minutes' => 'integer',
            'start_minutes' => 'integer',
            'resolution_minutes' => 'integer',
            'rule_priority' => 'integer',
            'is_fallback' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function calendar(): BelongsTo
    {
        return $this->belongsTo(ItSlaCalendar::class, 'calendar_id');
    }
}
