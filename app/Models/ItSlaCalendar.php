<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItSlaCalendar extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'weekdays',
        'starts_at',
        'ends_at',
        'break_starts_at',
        'break_ends_at',
        'count_weekends',
        'holidays',
        'exceptions',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'weekdays' => 'array',
            'count_weekends' => 'boolean',
            'holidays' => 'array',
            'exceptions' => 'array',
            'active' => 'boolean',
        ];
    }

    public function rules(): HasMany
    {
        return $this->hasMany(ItSlaRule::class, 'calendar_id');
    }
}
