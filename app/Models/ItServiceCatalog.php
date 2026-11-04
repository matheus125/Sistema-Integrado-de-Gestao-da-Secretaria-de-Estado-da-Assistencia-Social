<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItServiceCatalog extends Model
{
    protected $table = 'it_service_catalog';
    protected $fillable = ['name', 'key', 'description', 'category_id', 'type', 'sla_rule_id', 'checklist_template_id', 'requires_approval', 'approval_role', 'form_fields', 'active', 'created_by'];
    protected function casts(): array { return ['form_fields' => 'array', 'requires_approval' => 'boolean', 'active' => 'boolean']; }
    public function category(): BelongsTo { return $this->belongsTo(ItTicketCategory::class, 'category_id'); }
    public function slaRule(): BelongsTo { return $this->belongsTo(ItSlaRule::class, 'sla_rule_id'); }
    public function checklistTemplate(): BelongsTo { return $this->belongsTo(ItChecklistTemplate::class, 'checklist_template_id'); }
}
