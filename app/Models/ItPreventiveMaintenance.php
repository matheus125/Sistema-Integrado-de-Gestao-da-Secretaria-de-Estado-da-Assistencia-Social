<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ItPreventiveMaintenance extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'asset_id', 'department_id', 'created_by', 'last_ticket_id', 'name', 'description',
        'frequency_days', 'last_maintenance_at', 'next_maintenance_at', 'lead_days',
        'status', 'auto_create_ticket', 'last_generated_at',
    ];

    protected function casts(): array
    {
        return [
            'last_maintenance_at' => 'date:Y-m-d',
            'next_maintenance_at' => 'date:Y-m-d',
            'last_generated_at' => 'datetime',
            'auto_create_ticket' => 'boolean',
        ];
    }

    public function asset(): BelongsTo { return $this->belongsTo(Asset::class); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function lastTicket(): BelongsTo { return $this->belongsTo(ItTicket::class, 'last_ticket_id'); }
    public function tickets(): HasMany { return $this->hasMany(ItTicket::class, 'preventive_maintenance_id'); }
}
