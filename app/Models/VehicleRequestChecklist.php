<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleRequestChecklist extends Model
{
    protected $fillable = [
        'vehicle_request_id',
        'checked_by',
        'type',
        'fuel_level',
        'km',
        'documents_ok',
        'tires_ok',
        'lights_ok',
        'bodywork_ok',
        'tools_ok',
        'has_damage',
        'damages_description',
        'observations',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'fuel_level' => 'integer',
            'km' => 'integer',
            'documents_ok' => 'boolean',
            'tires_ok' => 'boolean',
            'lights_ok' => 'boolean',
            'bodywork_ok' => 'boolean',
            'tools_ok' => 'boolean',
            'has_damage' => 'boolean',
            'checked_at' => 'datetime',
        ];
    }

    public function vehicleRequest(): BelongsTo
    {
        return $this->belongsTo(VehicleRequest::class);
    }

    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }
}
