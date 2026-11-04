<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ItTicket extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPES = [
        'incidente' => ['label' => 'Incidente', 'icon' => 'report_problem', 'color' => 'negative'],
        'solicitacao' => ['label' => 'Solicitacao', 'icon' => 'assignment', 'color' => 'primary'],
        'acesso' => ['label' => 'Acesso', 'icon' => 'lock_open', 'color' => 'warning'],
        'manutencao' => ['label' => 'Manutencao', 'icon' => 'build', 'color' => 'orange'],
        'instalacao' => ['label' => 'Instalacao', 'icon' => 'download', 'color' => 'info'],
        'duvida' => ['label' => 'Duvida', 'icon' => 'help', 'color' => 'secondary'],
        'problema' => ['label' => 'Problema', 'icon' => 'bug_report', 'color' => 'deep-orange'],
        'mudanca' => ['label' => 'Mudanca', 'icon' => 'published_with_changes', 'color' => 'purple'],
    ];

    protected $fillable = [
        'department_id',
        'opened_by',
        'assigned_to',
        'support_team_id',
        'resolved_by',
        'closed_by',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
        'category_id',
        'service_catalog_id',
        'approval_status',
        'approval_requested_at',
        'sla_rule_id',
        'primary_asset_id',
        'preventive_maintenance_id',
        'root_cause_id',
        'type',
        'requester_name',
        'requester_phone',
        'department_name',
        'description',
        'resolution_summary',
        'requires_follow_up',
        'recurring_problem',
        'identified_cause',
        'root_cause',
        'applied_solution',
        'executed_procedure',
        'replaced_part',
        'internal_technical_note',
        'pending_reason',
        'category',
        'priority',
        'impact',
        'urgency',
        'status',
        'source',
        'is_major_incident',
        'major_incident_id',
        'whatsapp_conversation_id',
        'first_response_at',
        'first_response_due_at',
        'started_at',
        'start_due_at',
        'resolved_at',
        'closed_at',
        'last_requester_reply_at',
        'sla_due_at',
        'sla_overdue',
        'service_time_minutes',
        'reopen_count',
    ];

    protected function casts(): array
    {
        return [
            'first_response_at' => 'datetime',
            'first_response_due_at' => 'datetime',
            'started_at' => 'datetime',
            'start_due_at' => 'datetime',
            'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'approval_requested_at' => 'datetime',
            'last_requester_reply_at' => 'datetime',
            'sla_due_at' => 'datetime',
            'sla_overdue' => 'boolean',
            'service_time_minutes' => 'integer',
            'reopen_count' => 'integer',
            'requires_follow_up' => 'boolean',
            'recurring_problem' => 'boolean',
            'is_major_incident' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function supportTeam(): BelongsTo
    {
        return $this->belongsTo(ItSupportTeam::class, 'support_team_id');
    }

    public function categoryModel(): BelongsTo
    {
        return $this->belongsTo(ItTicketCategory::class, 'category_id');
    }

    public function serviceCatalog(): BelongsTo
    {
        return $this->belongsTo(ItServiceCatalog::class, 'service_catalog_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(ItTicketApproval::class, 'ticket_id');
    }

    public function slaRule(): BelongsTo
    {
        return $this->belongsTo(ItSlaRule::class, 'sla_rule_id');
    }

    public function primaryAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'primary_asset_id');
    }

    public function rootCauseCatalog(): BelongsTo
    {
        return $this->belongsTo(ItRootCause::class, 'root_cause_id');
    }

    public function majorIncident(): BelongsTo
    {
        return $this->belongsTo(self::class, 'major_incident_id');
    }

    public function incidentChildren(): HasMany
    {
        return $this->hasMany(self::class, 'major_incident_id');
    }

    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class, 'it_ticket_asset', 'ticket_id', 'asset_id')
            ->withPivot('primary')
            ->withTimestamps();
    }

    public function preventiveMaintenance(): BelongsTo
    {
        return $this->belongsTo(ItPreventiveMaintenance::class, 'preventive_maintenance_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ItTicketMessage::class, 'ticket_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(ItTicketStatusHistory::class, 'ticket_id');
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'it_ticket_followers', 'ticket_id', 'user_id')
            ->wherePivotNull('removed_at')
            ->withPivot(['added_by', 'removed_at', 'removed_by'])
            ->withTimestamps();
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(ItTicketChecklistItem::class, 'ticket_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ItTicketTask::class, 'ticket_id');
    }

    public function relationships(): HasMany
    {
        return $this->hasMany(ItTicketRelationship::class, 'ticket_id');
    }

    public function evaluation(): HasOne
    {
        return $this->hasOne(ItTicketEvaluation::class, 'ticket_id');
    }
}
