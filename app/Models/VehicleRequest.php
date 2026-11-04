<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class VehicleRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'department_id',
        'vehicle_id',
        'driver_id',
        'requested_by',
        'approved_by',
        'rejected_by',
        'cancelled_by',
        'status',
        'route_description',
        'origin',
        'destination',
        'date',
        'departure_time',
        'expected_arrival_time',
        'passengers_count',
        'responsible',
        'initial_km',
        'final_km',
        'notes',
        'rejection_reason',
        'cancellation_reason',
        'approved_at',
        'rejected_at',
        'cancelled_at',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'passengers_count' => 'integer',
            'initial_km' => 'integer',
            'final_km' => 'integer',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(VehicleRequestChecklist::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(VehicleRequestStatusHistory::class);
    }
}
