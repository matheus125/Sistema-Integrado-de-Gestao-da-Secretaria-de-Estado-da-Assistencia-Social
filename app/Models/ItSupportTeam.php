<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ItSupportTeam extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'description', 'max_active_tickets', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'max_active_tickets' => 'integer'];
    }

    public function technicians(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'it_support_team_user', 'team_id', 'user_id')
            ->withPivot(['specialties', 'available', 'unavailable_until', 'max_active_tickets'])
            ->withTimestamps();
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ItTicketCategory::class, 'it_category_support_team', 'team_id', 'category_id')->withTimestamps();
    }
}
