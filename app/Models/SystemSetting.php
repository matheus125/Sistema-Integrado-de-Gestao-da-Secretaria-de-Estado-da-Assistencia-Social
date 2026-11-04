<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'group',
        'key',
        'label',
        'type',
        'value',
        'is_secret',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'is_secret' => 'boolean',
        ];
    }
}
