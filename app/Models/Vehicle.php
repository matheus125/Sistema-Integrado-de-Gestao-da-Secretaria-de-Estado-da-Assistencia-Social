<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'brand',
        'model',
        'year',
        'category',
        'type',
        'color',
        'plate',
        'renavam',
        'chassis',
        'fuel',
        'capacity',
        'status',
        'current_km',
        'last_maintenance_at',
        'next_licensing_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'capacity' => 'integer',
            'current_km' => 'integer',
            'last_maintenance_at' => 'date:Y-m-d',
            'next_licensing_at' => 'date:Y-m-d',
        ];
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(VehicleMaintenance::class);
    }
}
