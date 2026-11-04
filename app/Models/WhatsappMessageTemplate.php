<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappMessageTemplate extends Model
{
    protected $fillable = ['name', 'key', 'body', 'variables', 'active'];

    protected function casts(): array
    {
        return ['variables' => 'array', 'active' => 'boolean'];
    }
}
