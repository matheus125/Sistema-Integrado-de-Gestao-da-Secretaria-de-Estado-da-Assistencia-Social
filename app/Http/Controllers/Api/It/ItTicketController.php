<?php

namespace App\Http\Controllers\Api\It;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\ItTicket;
use App\Models\ItChecklistTemplate;
use App\Models\ItTicketCategory;
use App\Models\ItTicketStatusHistory;
use App\Models\ItServiceCatalog;
use App\Models\ItTicketApproval;
use App\Services\It\ItNotificationService;
use App\Services\It\ItAutomationService;
use App\Services\It\ItPriorityCalculator;
use App\Services\It\SlaService;
use App\Services\It\TechnicianAssignment;
use App\Services\It\WhatsappOutboundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

class ItTicketController extends Controller
{
    private const STATUSES = ['aberto', 'triagem', 'em_atendimento', 'aguardando_usuario', 'resolvido', 'encerrado', 'cancelado'];

    private const STATUS_FLOW = [
        'aberto' => ['triagem', 'em_atendimento', 'aguardando_usuario', 'resolvido'],
        'triagem' => ['em_atendimento', 'aguardando_usuario', 'resolvido'],
        'em_atendimento' => ['aguardando_usuario', 'resolvido'],
        'aguardando_usuario' => ['em_atendimento', 'resolvido'],
        'resolvido' => ['encerrado', 'em_atendimento'],
        'encerrado' => [],
        'cancelado' => [],
    ];

    public function __construct(
        private readonly TechnicianAssignment $assignment,
        private readonly SlaService $slaService,
        private readonly ItNotificationService $notifications,
        private readonly ItAutomationService $automations,
        private readonly ItPriorityCalculator $priorityCalculator,
        private readonly WhatsappOutboundService $whatsapp,
    )
    {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'type' => ['nullable', Rule::in(array_keys(ItTicket::TYPES))],
            'priority' => ['nullable', Rule::in(['baixa', 'media', 'alta', 'urgente'])],
            'category' => ['nullable', 'string', 'max:120'],
            'category_id' => ['nullable', 'integer', 'exists:it_ticket_categories,id'],
            'service_catalog_id' => ['nullable', 'integer', 'exists:it_service_catalog,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'source' => ['nullable', Rule::in(['manual', 'whatsapp', 'preventiva'])],
            'sla' => ['nullable', Rule::in(['overdue', 'due_today'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'mine' => ['nullable', 'boolean'],
            'kanban' => ['nullable', 'boolean'],
        ]);
        $user = $request->user();

        $tickets = ItTicket::query()
            ->with(['department:id,name', 'assignee:id,name', 'openedBy:id,name', 'categoryModel:id,name,color,icon', 'slaRule:id,name', 'primaryAsset:id,asset_number,description,brand,model,serial_number,status'])
            ->withCount('messages')
            ->tap(fn ($query) => $this->applyTicketScope($request, $query))
            ->when($request->boolean('mine'), fn ($query) => $query->where('opened_by', $request->user()->id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($filters['priority'] ?? null, fn ($query, $priority) => $query->where('priority', $priority))
            ->when($filters['category'] ?? null, fn ($query, $category) => $query->where('category', $category))
            ->when($filters['category_id'] ?? null, fn ($query, $categoryId) => $query->where('category_id', $categoryId))
            ->when(($filters['department_id'] ?? null) && $this->hasFullAccess($request), fn ($query) => $query->where('department_id', $filters['department_id']))
            ->when($filters['assigned_to'] ?? null, fn ($query, $assignee) => $query->where('assigned_to', $assignee))
            ->when($filters['source'] ?? null, fn ($query, $source) => $query->where('source', $source))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->when($filters['sla'] ?? null, function ($query, $sla) {
                $query->whereNotIn('status', ['resolvido', 'encerrado']);
                $sla === 'overdue'
                    ? $query->where('sla_due_at', '<', now())
                    : $query->whereBetween('sla_due_at', [now(), now()->endOfDay()]);
            })
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(function ($query) use ($search) {
                $query->where('requester_name', 'like', "%{$search}%")
                    ->orWhere('requester_phone', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")
                    ->orWhere('department_name', 'like', "%{$search}%");
            }))
            ->latest();

        if ($request->boolean('kanban')) {
            $collection = $tickets->get()->map(fn (ItTicket $ticket) => $this->withAvailableTransitions($ticket));

            return response()->json([
                'data' => $collection->values(),
                'meta' => ['total' => $collection->count(), 'view' => 'kanban'],
            ]);
        }

        $tickets = $tickets->paginate($filters['per_page'] ?? 15);

        $tickets->getCollection()->transform(fn (ItTicket $ticket) => $this->withAvailableTransitions($ticket));

        return response()->json($tickets);
    }

    public function assignmentRecommendations(Request $request, ItTicket $ticket): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.visualizar'), 403);
        $this->authorizeTicketAccess($request, $ticket);

        return response()->json([
            'data' => $this->assignment->rankedTechnicians([
                'priority' => $ticket->priority,
                'type' => $ticket->type,
                'category' => $ticket->category,
                'category_id' => $ticket->category_id,
                'support_team_id' => $ticket->support_team_id,
            ]),
        ]);
    }

    public function timeline(Request $request, ItTicket $ticket): JsonResponse
    {
        $this->authorizeTicketAccess($request, $ticket);
        $ticket->load(['messages.user:id,name', 'statusHistories.changedBy:id,name', 'followers:id,name', 'tasks.assignedTo:id,name', 'checklistItems', 'relationships', 'evaluation.user:id,name']);
        if (! $this->canViewInternal($request, $ticket)) {
            $ticket->setRelation('messages', $ticket->messages->where('visibility', 'public')->values());
        }
        $attachments = Attachment::query()
            ->with('uploader:id,name')
            ->where('attachable_type', ItTicket::class)
            ->where('attachable_id', $ticket->id)
            ->get();
        $events = collect()
            ->concat($ticket->statusHistories->map(fn ($item) => ['type' => 'status', 'at' => $item->created_at, 'data' => $item]))
            ->concat($ticket->messages->map(fn ($item) => ['type' => 'message', 'at' => $item->created_at, 'data' => $item]))
            ->concat($ticket->tasks->map(fn ($item) => ['type' => 'task', 'at' => $item->updated_at, 'data' => $item]))
            ->concat($ticket->checklistItems->map(fn ($item) => ['type' => 'checklist', 'at' => $item->updated_at, 'data' => $item]))
            ->concat($attachments->map(fn ($item) => ['type' => 'attachment', 'at' => $item->created_at, 'data' => $item]))
            ->concat($ticket->evaluation ? collect([['type' => 'evaluation', 'at' => $ticket->evaluation->evaluated_at ?? $ticket->evaluation->created_at, 'data' => $ticket->evaluation]]) : collect())
            ->concat(collect([
                ['type' => 'sla', 'at' => $ticket->created_at, 'data' => ['event' => 'sla_created', 'due_at' => $ticket->sla_due_at, 'first_response_due_at' => $ticket->first_response_due_at]],
            ]))
            ->concat($request->user()->can('informatica.editar')
                ? Activity::query()->where('subject_type', ItTicket::class)->where('subject_id', $ticket->id)->latest()->get()->map(fn ($item) => ['type' => 'audit', 'at' => $item->created_at, 'data' => $item])
                : collect())
            ->sortByDesc('at')->values();
        return response()->json(['data' => $events]);
    }

    public function cancel(Request $request, ItTicket $ticket): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.editar'), 403);
        $this->authorizeTicketAccess($request, $ticket);
        abort_if(in_array($ticket->status, ['encerrado', 'cancelado'], true), 422, 'Chamado nao pode ser cancelado neste estado.');
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $from = $ticket->status;
        $ticket->update(['status' => 'cancelado', 'cancelled_at' => now(), 'cancelled_by' => $request->user()->id, 'cancellation_reason' => $data['reason']]);
        $this->recordStatusHistory($request, $ticket, $from, 'cancelado', $data['reason']);
        return response()->json($this->loadTicketRelations($ticket->fresh()));
    }

    public function bulkAction(Request $request): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.editar'), 403);
        $data = $request->validate([
            'ticket_ids' => ['required', 'array', 'min:1', 'max:100'],
            'ticket_ids.*' => ['integer', 'distinct', 'exists:it_tickets,id'],
            'action' => ['required', Rule::in(['assign', 'priority', 'category', 'team', 'close'])],
            'value' => ['nullable'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);
        if (in_array($data['action'], ['assign', 'team'], true) && ! $data['value']) {
            throw ValidationException::withMessages(['value' => 'Informe o responsável ou equipe.']);
        }
        if ($data['action'] === 'assign' && ! \App\Models\User::query()->whereKey($data['value'])->exists()) {
            throw ValidationException::withMessages(['value' => 'Técnico inválido.']);
        }
        if ($data['action'] === 'team' && ! \App\Models\ItSupportTeam::query()->whereKey($data['value'])->exists()) {
            throw ValidationException::withMessages(['value' => 'Equipe inválida.']);
        }
        if ($data['action'] === 'priority' && ! in_array($data['value'], ['baixa', 'media', 'alta', 'urgente'], true)) {
            throw ValidationException::withMessages(['value' => 'Prioridade inválida.']);
        }
        if ($data['action'] === 'category' && ! ItTicketCategory::query()->whereKey($data['value'])->where('active', true)->exists()) {
            throw ValidationException::withMessages(['value' => 'Categoria inválida.']);
        }
        $updated = 0;
        foreach (ItTicket::query()->whereIn('id', $data['ticket_ids'])->get() as $ticket) {
            $this->authorizeTicketAccess($request, $ticket);
            if ($data['action'] === 'close') {
                if (in_array($ticket->status, ['encerrado', 'cancelado'], true)) continue;
                $from = $ticket->status;
                $ticket->update(['status' => 'encerrado', 'closed_at' => now(), 'closed_by' => $request->user()->id]);
                $this->recordStatusHistory($request, $ticket, $from, 'encerrado', $data['reason'] ?? 'Encerramento em massa.');
            } else {
                $field = ['assign' => 'assigned_to', 'priority' => 'priority', 'category' => 'category_id', 'team' => 'support_team_id'][$data['action']];
                $attributes = [$field => $data['value']];
                if ($data['action'] === 'category') {
                    $attributes['category'] = ItTicketCategory::query()->find($data['value'])->name;
                }
                $ticket->update($attributes);
            }
            activity('informatica')->causedBy($request->user())->performedOn($ticket)->log('ação em massa: '.$data['action']);
            $updated++;
        }
        return response()->json(['updated' => $updated]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.cadastrar'), 403);

        $data = $this->validatedData($request);
        $data = $this->enforceDepartmentScope($request, $data);
        $data = $this->enforceRequesterInput($request, $data);
        $data['priority'] = $this->priorityCalculator->calculate($data['impact'] ?? null, $data['urgency'] ?? null, $data['priority'] ?? 'media');
        if (! empty($data['service_catalog_id'])) {
            $service = ItServiceCatalog::query()->whereKey($data['service_catalog_id'])->where('active', true)->firstOrFail();
            $data['category_id'] = $data['category_id'] ?? $service->category_id;
            $data['type'] = $data['type'] ?? $service->type;
            $data['sla_rule_id'] = $data['sla_rule_id'] ?? $service->sla_rule_id;
        }

        $ticket = DB::transaction(function () use ($request, $data) {
            $team = $this->assignment->resolveTeam($data);
            $assignedTo = $data['assigned_to'] ?? $this->assignment->nextTechnicianId($data);
            $status = $assignedTo ? 'triagem' : 'aberto';

            $ticket = ItTicket::create([
                ...$data,
                'opened_by' => $request->user()->id,
                'assigned_to' => $assignedTo,
                'support_team_id' => $data['support_team_id'] ?? $team?->id,
                'status' => $status,
                'type' => $data['type'] ?? 'incidente',
                'source' => $data['source'] ?? 'manual',
            ]);
            $this->slaService->applyToTicket($ticket)->save();
            $this->syncTicketAssets($ticket, $data);
            $this->applyChecklistTemplate($ticket);

            $ticket->messages()->create([
                'user_id' => $request->user()->id,
                'direction' => 'internal',
                'message' => 'Chamado aberto: '.$data['description'],
            ]);

            $this->recordStatusHistory($request, $ticket, null, $status, 'Chamado aberto.');
            $this->notifications->ticketEvent($ticket, 'ticket_created', "Chamado #{$ticket->id} criado.", $ticket->openedBy);
            if ($ticket->assignee) {
                $this->notifications->ticketEvent($ticket, 'ticket_assigned', "Chamado #{$ticket->id} atribuido.", $ticket->assignee);
            }

            return $this->loadTicketRelations($ticket)->loadCount('messages');
        });

        if (! empty($data['service_catalog_id'])) {
            $service = ItServiceCatalog::query()->find($data['service_catalog_id']);
            if ($service?->requires_approval) {
                $approver = $service->approval_role ? \App\Models\User::role($service->approval_role)->first() : null;
                $ticket->update(['approval_status' => 'pendente', 'approval_requested_at' => now()]);
                ItTicketApproval::create([
                    'ticket_id' => $ticket->id,
                    'service_catalog_id' => $service->id,
                    'requested_by' => $request->user()->id,
                    'approver_id' => $approver?->id,
                    'status' => 'pendente',
                ]);
            }
        }

            activity('informatica')->causedBy($request->user())->performedOn($ticket)->log('chamado aberto');
            $this->automations->run($ticket->fresh(), 'ticket_created');

        return response()->json($ticket, 201);
    }

    public function show(Request $request, ItTicket $ticket): JsonResponse
    {
        $this->authorizeTicketAccess($request, $ticket);

        $ticket = $this->withAvailableTransitions($ticket->load([
            'department:id,name',
            'assignee:id,name',
            'openedBy:id,name',
            'resolver:id,name',
            'closer:id,name',
            'supportTeam:id,name',
            'categoryModel:id,name,color,icon',
            'rootCauseCatalog:id,name',
            'slaRule:id,name,first_response_minutes,start_minutes,resolution_minutes',
            'primaryAsset.department:id,name',
            'primaryAsset.responsible:id,name',
            'assets.department:id,name',
            'assets.responsible:id,name',
            'evaluation.user:id,name',
            'messages.user:id,name',
            'statusHistories.changedBy:id,name',
            'followers:id,name,email',
            'checklistItems',
            'tasks.assignedTo:id,name',
            'relationships',
            'incidentChildren:id,status,requester_name,description',
        ]));

        if (! $this->canViewInternal($request, $ticket)) {
            $ticket->setRelation('messages', $ticket->messages->where('visibility', 'public')->values());
            $ticket->setAttribute('internal_technical_note', null);
        }

        return response()->json($ticket);
    }

    public function update(Request $request, ItTicket $ticket): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.editar'), 403);
        $this->authorizeTicketAccess($request, $ticket);
        $data = $this->validatedData($request, partial: true);
        $data = $this->enforceDepartmentScope($request, $data);
        if (array_key_exists('impact', $data) || array_key_exists('urgency', $data)) {
            $data['priority'] = $this->priorityCalculator->calculate(
                $data['impact'] ?? $ticket->impact,
                $data['urgency'] ?? $ticket->urgency,
                $data['priority'] ?? $ticket->priority ?? 'media',
            );
        }
        $beforeAssignedTo = $ticket->assigned_to;
        $ticket->update($data);
        if ($this->shouldRecalculateSla($data)) {
            $this->slaService->applyToTicket($ticket->refresh())->save();
        }
        $this->syncTicketAssets($ticket, $data);

        if (array_key_exists('assigned_to', $data) && (int) $beforeAssignedTo !== (int) $ticket->assigned_to) {
            $ticket->messages()->create([
                'user_id' => $request->user()->id,
                'direction' => 'internal',
                'message' => 'Chamado atribuido para tecnico: '.($ticket->assignee?->name ?? 'sem tecnico'),
            ]);
            if ($ticket->assignee) {
                $this->notifications->ticketEvent($ticket, 'ticket_assigned', "Chamado #{$ticket->id} atribuido.", $ticket->assignee);
            }
        }

        activity('informatica')->causedBy($request->user())->performedOn($ticket)->log('chamado atualizado');

        return response()->json($this->withAvailableTransitions($this->loadTicketRelations($ticket)->loadCount('messages')));
    }

    public function changeStatus(Request $request, ItTicket $ticket): JsonResponse
    {
        $this->authorizeTicketAccess($request, $ticket);

        $data = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
            'service_time_minutes' => ['nullable', 'integer', 'min:0'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'resolution_summary' => ['nullable', 'string', 'max:1000'],
            'root_cause_id' => ['nullable', 'integer', 'exists:it_root_causes,id'],
            'identified_cause' => ['nullable', 'string', 'max:255'],
            'root_cause' => ['nullable', 'string', 'max:1000'],
            'applied_solution' => ['nullable', 'string', 'max:1000'],
            'executed_procedure' => ['nullable', 'string', 'max:1000'],
            'replaced_part' => ['nullable', 'string', 'max:255'],
            'requires_follow_up' => ['nullable', 'boolean'],
            'recurring_problem' => ['nullable', 'boolean'],
            'internal_technical_note' => ['nullable', 'string', 'max:2000'],
        ]);
        $isRequesterReopen = $ticket->opened_by === $request->user()->id
            && $ticket->status === 'resolvido'
            && $data['status'] === 'em_atendimento';
        abort_if($request->user()->cannot('informatica.editar') && ! $isRequesterReopen, 403);
        abort_if($ticket->approval_status === 'pendente', 422, 'O chamado aguarda aprovação antes de avançar no fluxo.');

        $this->ensureStatusTransitionAllowed($ticket, $data);

        $updates = ['status' => $data['status']];
        $reason = $data['reason'] ?? null;

        if ($data['status'] === 'em_atendimento' && $ticket->first_response_at === null) {
            $updates['first_response_at'] = now();
        }
        if ($data['status'] === 'em_atendimento' && $ticket->started_at === null) {
            $updates['started_at'] = now();
        }

        if ($ticket->status === 'resolvido' && $data['status'] === 'em_atendimento') {
            $updates['reopen_count'] = $ticket->reopen_count + 1;
            $updates['resolved_at'] = null;
            $updates['resolved_by'] = null;
            $updates['resolution_summary'] = null;
            // A previous satisfaction score belongs to the closed service
            // cycle and must not block a new evaluation after re-opening.
            $ticket->evaluation()->delete();
        }

        if ($data['status'] === 'aguardando_usuario') {
            $updates['pending_reason'] = $reason;
        }

        if ($data['status'] === 'resolvido') {
            $updates['resolved_at'] = now();
            $updates['resolved_by'] = $request->user()->id;
            $updates['resolution_summary'] = $data['resolution_summary'];
            foreach (['root_cause_id', 'identified_cause', 'root_cause', 'applied_solution', 'executed_procedure', 'replaced_part', 'requires_follow_up', 'recurring_problem', 'internal_technical_note'] as $field) {
                if (array_key_exists($field, $data)) {
                    $updates[$field] = $data[$field];
                }
            }
            $updates['service_time_minutes'] = $data['service_time_minutes'] ?? $ticket->service_time_minutes;
            $updates['pending_reason'] = null;
        }

        if ($data['status'] === 'encerrado') {
            $updates['closed_at'] = now();
            $updates['closed_by'] = $request->user()->id;
        }

        $fromStatus = $ticket->status;
        $ticket->update($updates);
        $this->recordStatusHistory($request, $ticket, $fromStatus, $data['status'], $reason ?? $data['resolution_summary'] ?? null);
        $this->notifications->ticketEvent($ticket, 'status_changed', "Chamado #{$ticket->id}: {$fromStatus} para {$data['status']}.", $ticket->openedBy);
        if ($ticket->source === 'whatsapp' && filled($ticket->whatsapp_conversation_id)) {
            $this->whatsapp->send($ticket, $ticket->whatsapp_conversation_id, "Chamado #{$ticket->id}: status alterado para {$data['status']}.", 'status_changed');
        }

        $ticket->messages()->create([
            'user_id' => $request->user()->id,
            'direction' => 'internal',
            'message' => "Status alterado de {$fromStatus} para {$data['status']}".($reason ? ": {$reason}" : ''),
        ]);

        activity('informatica')->causedBy($request->user())->performedOn($ticket)->log('status de chamado alterado');
        $this->automations->run($ticket->fresh(), 'status_changed');

        return response()->json($this->withAvailableTransitions($this->loadTicketRelations($ticket)->loadCount('messages')));
    }

    public function summary(Request $request): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.visualizar'), 403);

        $scope = ItTicket::query();
        $this->applyTicketScope($request, $scope);
        if ($request->boolean('mine')) {
            $scope->where('opened_by', $request->user()->id);
        }
        $openScope = (clone $scope)->whereNotIn('status', ['resolvido', 'encerrado']);
        $monthScope = (clone $scope)->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);

        return response()->json([
            'open' => (clone $openScope)->count(),
            'overdue' => (clone $openScope)->where('sla_due_at', '<', now())->count(),
            'due_today' => (clone $openScope)->whereBetween('sla_due_at', [now(), now()->endOfDay()])->count(),
            'resolved_month' => (clone $monthScope)->where('status', 'resolvido')->count(),
            'closed_month' => (clone $monthScope)->where('status', 'encerrado')->count(),
            'avg_service_minutes' => round((float) (clone $monthScope)->where('status', 'resolvido')->avg('service_time_minutes'), 1),
            'evaluations' => [
                'avg' => round((float) DB::table('it_ticket_evaluations')->avg('rating'), 2),
                'percent' => $this->evaluationPercent($scope),
            ],
            'by_priority' => (clone $openScope)
                ->select('priority', DB::raw('count(*) as total'))
                ->groupBy('priority')
                ->pluck('total', 'priority'),
            'by_status' => (clone $scope)
                ->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status'),
            'by_type' => (clone $scope)
                ->select('type', DB::raw('count(*) as total'))
                ->groupBy('type')
                ->pluck('total', 'type'),
        ]);
    }

    private function validatedData(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        $priorityRequired = ! $partial && ! ($request->filled('impact') && $request->filled('urgency'))
            ? 'required'
            : 'sometimes';

        $data = $request->validate([
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'category_id' => ['nullable', 'integer', 'exists:it_ticket_categories,id'],
            'service_catalog_id' => ['nullable', 'integer', 'exists:it_service_catalog,id'],
            'support_team_id' => ['nullable', 'integer', 'exists:it_support_teams,id'],
            'primary_asset_id' => ['nullable', 'integer', 'exists:assets,id'],
            'root_cause_id' => ['nullable', 'integer', 'exists:it_root_causes,id'],
            'asset_ids' => ['nullable', 'array'],
            'asset_ids.*' => ['integer', 'exists:assets,id'],
            'type' => ['sometimes', Rule::in(array_keys(ItTicket::TYPES))],
            'requester_name' => [$required, 'string', 'max:160'],
            'requester_phone' => ['nullable', 'string', 'max:40'],
            'department_name' => ['nullable', 'string', 'max:160'],
            'description' => [$required, 'string'],
            'resolution_summary' => ['nullable', 'string', 'max:1000'],
            'requires_follow_up' => ['nullable', 'boolean'],
            'recurring_problem' => ['nullable', 'boolean'],
            'identified_cause' => ['nullable', 'string', 'max:255'],
            'root_cause' => ['nullable', 'string', 'max:1000'],
            'applied_solution' => ['nullable', 'string', 'max:1000'],
            'executed_procedure' => ['nullable', 'string', 'max:1000'],
            'replaced_part' => ['nullable', 'string', 'max:255'],
            'internal_technical_note' => ['nullable', 'string', 'max:2000'],
            'pending_reason' => ['nullable', 'string', 'max:1000'],
            'category' => ['nullable', 'string', 'max:120'],
            'priority' => [$priorityRequired, 'nullable', Rule::in(['baixa', 'media', 'alta', 'urgente'])],
            'impact' => ['nullable', Rule::in(['baixo', 'medio', 'alto'])],
            'urgency' => ['nullable', Rule::in(['baixa', 'media', 'alta'])],
            'status' => ['sometimes', Rule::in(['aberto', 'triagem', 'em_atendimento', 'aguardando_usuario', 'resolvido', 'encerrado'])],
            'source' => ['sometimes', Rule::in(['manual', 'whatsapp', 'preventiva'])],
            'preventive_maintenance_id' => ['nullable', 'integer', 'exists:it_preventive_maintenances,id'],
            'is_major_incident' => ['nullable', 'boolean'],
            'major_incident_id' => ['nullable', 'integer', 'exists:it_tickets,id'],
        ]);

        if (array_key_exists('category_id', $data) && $data['category_id']) {
            $data['category'] = ItTicketCategory::query()->whereKey($data['category_id'])->value('name');
        }

        if (array_key_exists('category_id', $data) && ! $data['category_id'] && ! array_key_exists('category', $data)) {
            $data['category'] = null;
        }

        return $data;
    }

    private function hasFullAccess(Request $request): bool
    {
        return $request->user()->hasRole('Administrador Geral') || $request->user()->can('informatica.aprovar');
    }

    private function enforceDepartmentScope(Request $request, array $data): array
    {
        if (! $this->hasFullAccess($request)) {
            $data['department_id'] = $request->user()->department_id;
        }

        return $data;
    }

    private function enforceRequesterInput(Request $request, array $data): array
    {
        // Self-service users may open and follow their own requests, but
        // cannot impersonate technical workflow fields at creation time.
        if ($request->user()->can('informatica.editar')) {
            return $data;
        }

        foreach ([
            'assigned_to', 'support_team_id', 'status', 'source',
            'resolution_summary', 'root_cause_id', 'identified_cause',
            'root_cause', 'applied_solution', 'executed_procedure',
            'replaced_part', 'requires_follow_up', 'recurring_problem',
            'internal_technical_note', 'pending_reason', 'is_major_incident',
            'major_incident_id',
        ] as $field) {
            unset($data[$field]);
        }

        return $data;
    }

    private function authorizeTicketAccess(Request $request, ItTicket $ticket): void
    {
        abort_unless($request->user()->can('view', $ticket), 403);
    }

    private function applyChecklistTemplate(ItTicket $ticket): void
    {
        $template = ItChecklistTemplate::query()
            ->where('active', true)
            ->where(function ($query) use ($ticket) {
                $query->where(function ($query) use ($ticket) {
                    $query->where('category_id', $ticket->category_id)->where('type', $ticket->type);
                })->orWhere(function ($query) use ($ticket) {
                    $query->where('category_id', $ticket->category_id)->whereNull('type');
                })->orWhere(function ($query) use ($ticket) {
                    $query->whereNull('category_id')->where('type', $ticket->type);
                });
            })
            ->with('items')
            ->orderByRaw('case when category_id is not null and type is not null then 0 when category_id is not null then 1 when type is not null then 2 else 3 end')
            ->first();

        if (! $template) {
            return;
        }

        $ticket->checklistItems()->createMany($template->items->map(fn ($item) => [
            'template_item_id' => $item->id,
            'label' => $item->label,
            'required' => $item->required,
            'status' => 'pendente',
        ])->all());
    }

    private function applyTicketScope(Request $request, $query): void
    {
        $user = $request->user();

        $query->when(! $this->hasFullAccess($request), function ($query) use ($user) {
            $query->where(function ($query) use ($user) {
                $query->where('assigned_to', $user->id)
                    ->orWhere('opened_by', $user->id)
                    ->orWhere('department_id', $user->department_id);
            });
        });
    }

    private function ensureStatusTransitionAllowed(ItTicket $ticket, array $data): void
    {
        $nextStatus = $data['status'];

        if ($nextStatus === $ticket->status) {
            return;
        }

        if (! in_array($nextStatus, self::STATUS_FLOW[$ticket->status] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => ["Nao e possivel alterar de {$ticket->status} para {$nextStatus}."],
            ]);
        }

        if ($nextStatus === 'aguardando_usuario' && blank($data['reason'] ?? null)) {
            throw ValidationException::withMessages([
                'reason' => ['Informe o motivo da pendencia com o usuario.'],
            ]);
        }

        if ($nextStatus === 'resolvido' && blank($data['resolution_summary'] ?? null)) {
            throw ValidationException::withMessages([
                'resolution_summary' => ['Informe o resumo da solucao aplicada.'],
            ]);
        }

        if ($nextStatus === 'resolvido') {
            if ($ticket->tasks()->where('required', true)->whereNotIn('status', ['concluida', 'cancelada'])->exists()) {
                throw ValidationException::withMessages([
                    'status' => ['Conclua as tarefas obrigatorias antes de resolver o chamado.'],
                ]);
            }
            if ($ticket->checklistItems()->where('required', true)->where('status', '!=', 'concluido')->exists()) {
                throw ValidationException::withMessages([
                    'status' => ['Conclua os itens obrigatorios do checklist antes de resolver o chamado.'],
                ]);
            }
        }
    }

    private function withAvailableTransitions(ItTicket $ticket): ItTicket
    {
        $ticket->setAttribute('available_transitions', self::STATUS_FLOW[$ticket->status] ?? []);

        return $ticket;
    }

    private function recordStatusHistory(Request $request, ItTicket $ticket, ?string $fromStatus, string $toStatus, ?string $reason = null): void
    {
        ItTicketStatusHistory::create([
            'ticket_id' => $ticket->id,
            'changed_by' => $request->user()?->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'reason' => $reason,
            'metadata' => [
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ],
        ]);
    }

    private function loadTicketRelations(ItTicket $ticket): ItTicket
    {
        return $ticket->load([
            'department:id,name',
            'assignee:id,name,email',
            'openedBy:id,name,email',
            'categoryModel:id,name,color,icon',
            'supportTeam:id,name',
            'rootCauseCatalog:id,name',
            'slaRule:id,name',
            'primaryAsset:id,department_id,responsible_id,asset_number,description,brand,model,serial_number,status',
            'primaryAsset.department:id,name',
            'primaryAsset.responsible:id,name',
        ]);
    }

    private function canViewInternal(Request $request, ItTicket $ticket): bool
    {
        return $this->hasFullAccess($request) || $ticket->assigned_to === $request->user()->id || $request->user()->can('informatica.editar');
    }

    private function shouldRecalculateSla(array $data): bool
    {
        return collect(['priority', 'category_id', 'type', 'department_id'])->contains(fn ($field) => array_key_exists($field, $data));
    }

    private function syncTicketAssets(ItTicket $ticket, array $data): void
    {
        $assetIds = collect($data['asset_ids'] ?? [])
            ->push($data['primary_asset_id'] ?? null)
            ->filter()
            ->unique()
            ->values();

        if ($assetIds->isEmpty() && ! array_key_exists('asset_ids', $data) && ! array_key_exists('primary_asset_id', $data)) {
            return;
        }

        $ticket->assets()->sync($assetIds->mapWithKeys(fn ($id) => [(int) $id => ['primary' => (int) $id === (int) ($data['primary_asset_id'] ?? 0)]])->all());
    }

    private function evaluationPercent($scope): float
    {
        $resolved = (clone $scope)->whereIn('status', ['resolvido', 'encerrado'])->count();
        if ($resolved === 0) {
            return 0.0;
        }

        $evaluated = DB::table('it_ticket_evaluations')
            ->join('it_tickets', 'it_tickets.id', '=', 'it_ticket_evaluations.ticket_id')
            ->whereNull('it_tickets.deleted_at')
            ->whereIn('it_tickets.id', (clone $scope)->select('it_tickets.id'))
            ->count();

        return round(($evaluated / $resolved) * 100, 1);
    }
}
