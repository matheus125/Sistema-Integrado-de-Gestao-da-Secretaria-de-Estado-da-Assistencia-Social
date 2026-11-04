<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItResponseTemplate extends Model
{
    protected $fillable = ['name', 'key', 'body', 'created_by', 'user_id', 'support_team_id', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function supportTeam(): BelongsTo { return $this->belongsTo(ItSupportTeam::class); }
}
