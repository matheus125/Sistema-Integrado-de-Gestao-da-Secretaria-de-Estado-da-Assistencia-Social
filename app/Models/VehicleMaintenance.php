<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VehicleMaintenance extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'vehicle_id',
        'created_by',
        'completed_by',
        'type',
        'status',
        'scheduled_at',
        'completed_at',
        'odometer',
        'next_due_km',
        'next_due_at',
        'cost',
        'supplier',
        'description',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'date:Y-m-d',
            'completed_at' => 'date:Y-m-d',
            'next_due_at' => 'date:Y-m-d',
            'odometer' => 'integer',
            'next_due_km' => 'integer',
            'cost' => 'decimal:2',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
