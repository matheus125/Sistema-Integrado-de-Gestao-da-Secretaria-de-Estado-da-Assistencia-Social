<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItAutomationRule extends Model
{
    protected $fillable = ['name', 'event', 'conditions', 'actions', 'active', 'created_by'];
    protected function casts(): array { return ['conditions' => 'array', 'actions' => 'array', 'active' => 'boolean']; }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
