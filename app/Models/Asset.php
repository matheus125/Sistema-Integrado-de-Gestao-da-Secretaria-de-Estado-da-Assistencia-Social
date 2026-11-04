<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'department_id',
        'responsible_id',
        'asset_number',
        'description',
        'category',
        'brand',
        'model',
        'serial_number',
        'acquired_at',
        'value',
        'invoice_number',
        'location',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'acquired_at' => 'date:Y-m-d',
            'value' => 'decimal:2',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(AssetMovement::class);
    }

    public function tickets(): BelongsToMany
    {
        return $this->belongsToMany(ItTicket::class, 'it_ticket_asset', 'asset_id', 'ticket_id')
            ->withPivot('primary')
            ->withTimestamps();
    }
}
