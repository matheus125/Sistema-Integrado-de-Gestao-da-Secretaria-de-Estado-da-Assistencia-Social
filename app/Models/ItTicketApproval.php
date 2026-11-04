<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItTicketApproval extends Model
{
    protected $fillable = ['ticket_id', 'service_catalog_id', 'requested_by', 'approver_id', 'status', 'comment', 'decided_at'];
    protected function casts(): array { return ['decided_at' => 'datetime']; }
    public function ticket(): BelongsTo { return $this->belongsTo(ItTicket::class); }
    public function serviceCatalog(): BelongsTo { return $this->belongsTo(ItServiceCatalog::class); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approver_id'); }
}
