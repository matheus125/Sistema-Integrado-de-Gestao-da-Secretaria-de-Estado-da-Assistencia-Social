<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItChecklistTemplate extends Model
{
    protected $fillable = ['category_id', 'type', 'name', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function category(): BelongsTo { return $this->belongsTo(ItTicketCategory::class, 'category_id'); }
    public function items(): HasMany { return $this->hasMany(ItChecklistTemplateItem::class, 'template_id')->orderBy('sort_order'); }
}
