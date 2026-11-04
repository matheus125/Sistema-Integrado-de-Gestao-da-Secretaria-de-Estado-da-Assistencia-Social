<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Driver extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'cnh',
        'cnh_category',
        'cnh_expires_at',
        'phone',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'cnh_expires_at' => 'date:Y-m-d',
        ];
    }
}
