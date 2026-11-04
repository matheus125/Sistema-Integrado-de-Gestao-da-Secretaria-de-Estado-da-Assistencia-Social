<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItChecklistTemplateItem extends Model
{
    protected $fillable = ['template_id', 'label', 'required', 'sort_order'];
    protected function casts(): array { return ['required' => 'boolean', 'sort_order' => 'integer']; }
    public function template(): BelongsTo { return $this->belongsTo(ItChecklistTemplate::class, 'template_id'); }
}
