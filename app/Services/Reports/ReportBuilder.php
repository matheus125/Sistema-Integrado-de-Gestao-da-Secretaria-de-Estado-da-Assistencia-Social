<?php

namespace App\Services\Reports;

use App\Exceptions\ReportExportLimitExceeded;
use App\Models\ItTicket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReportBuilder
{
    private ?int $exportRowLimit = null;

    public const REPORTS = [
        'transport_requests' => 'Solicitacoes de transporte',
        'transport_maintenances' => 'Manutencoes da frota',
        'stock_movements' => 'Movimentacoes de estoque',
        'assets' => 'Patrimonios',
        'it_tickets' => 'Chamados de informatica',
        'it_sla' => 'Cumprimento de SLA',
        'it_technicians' => 'Desempenho por tecnico',
        'it_departments' => 'Chamados por setor',
        'it_categories' => 'Chamados por categoria',
        'it_types' => 'Chamados por tipo',
        'it_recurrence' => 'Reincidencia de chamados',
        'it_assets' => 'Chamados por equipamento',
        'it_evaluations' => 'Avaliacoes de atendimento',
        'it_root_causes' => 'Causas raiz',
        'it_metrics' => 'Indicadores MTTA e MTTR',
        'it_backlog' => 'Backlog de chamados',
        'it_aging' => 'Aging do backlog',
        'audit_logs' => 'Auditoria',
    ];

    public function build(Request $request): array
    {
        $data = $request->validate([
            'report' => ['required', Rule::in(array_keys(self::REPORTS))],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:80'],
            'type' => ['nullable', Rule::in(array_keys(ItTicket::TYPES))],
        ]);

        return $this->buildValidatedReport($request, $data);
    }

    public function buildForExport(User $user, array $filters, int $limit): array
    {
        if ($limit < 1) {
            throw new ReportExportLimitExceeded($limit);
        }

        $request = Request::create('/api/reports/exports', 'POST', $filters);
        $request->setUserResolver(fn () => $user);
        $this->exportRowLimit = $limit;

        try {
            return $this->buildValidatedReport($request, $filters);
        } finally {
            $this->exportRowLimit = null;
        }
    }

    private function buildValidatedReport(Request $request, array $data): array
    {
        return match ($data['report']) {
            'stock_movements' => $this->stockMovements($request, $data),
            'transport_maintenances' => $this->transportMaintenances($request, $data),
            'assets' => $this->assets($request, $data),
            'it_tickets' => $this->itTickets($request, $data),
            'it_sla' => $this->itSla($request, $data),
            'it_technicians' => $this->itTechnicians($request, $data),
            'it_departments' => $this->itDepartments($request, $data),
            'it_categories' => $this->itCategories($request, $data),
            'it_types' => $this->itTypes($request, $data),
            'it_recurrence' => $this->itRecurrence($request, $data),
            'it_assets' => $this->itAssets($request, $data),
            'it_evaluations' => $this->itEvaluations($request, $data),
            'it_root_causes' => $this->itRootCauses($request, $data),
            'it_metrics' => $this->itMetrics($request, $data),
            'it_backlog' => $this->itBacklog($request, $data),
            'it_aging' => $this->itAging($request, $data),
            'audit_logs' => $this->auditLogs($request, $data),
            default => $this->transportRequests($request, $data),
        };
    }

    private function transportRequests(Request $request, array $filters): array
    {
        abort_if($request->user()->cannot('transporte.visualizar'), 403);

        $rows = DB::table('vehicle_requests')
            ->leftJoin('departments', 'departments.id', '=', 'vehicle_requests.department_id')
            ->leftJoin('vehicles', 'vehicles.id', '=', 'vehicle_requests.vehicle_id')
            ->leftJoin('drivers', 'drivers.id', '=', 'vehicle_requests.driver_id')
            ->whereNull('vehicle_requests.deleted_at')
            ->when(! $this->hasFullModuleAccess($request, 'transporte'), fn ($query) => $query->where('vehicle_requests.department_id', $request->user()->department_id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('vehicle_requests.status', $status))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('vehicle_requests.date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('vehicle_requests.date', '<=', $date))
            ->orderByDesc('vehicle_requests.date')
            ->when($this->exportRowLimit !== null, fn ($query) => $query->limit($this->exportRowLimit + 1))
            ->get([
                'vehicle_requests.date',
                'departments.name as department',
                'vehicles.plate',
                'drivers.name as driver',
                'vehicle_requests.origin',
                'vehicle_requests.destination',
                'vehicle_requests.status',
                'vehicle_requests.responsible',
                DB::raw('CASE WHEN vehicle_requests.final_km IS NOT NULL AND vehicle_requests.initial_km IS NOT NULL AND vehicle_requests.final_km >= vehicle_requests.initial_km THEN vehicle_requests.final_km - vehicle_requests.initial_km ELSE 0 END as km_percorrido'),
            ]);

        return $this->report('Solicitacoes de transporte', ['Data', 'Setor', 'Placa', 'Motorista', 'Origem', 'Destino', 'Status', 'Responsavel', 'KM percorrido'], $rows);
    }

    private function transportMaintenances(Request $request, array $filters): array
    {
        abort_if($request->user()->cannot('transporte.visualizar'), 403);

        $rows = DB::table('vehicle_maintenances')
            ->join('vehicles', 'vehicles.id', '=', 'vehicle_maintenances.vehicle_id')
            ->leftJoin('users as completed_users', 'completed_users.id', '=', 'vehicle_maintenances.completed_by')
            ->whereNull('vehicle_maintenances.deleted_at')
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('vehicle_maintenances.status', $status))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('vehicle_maintenances.scheduled_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('vehicle_maintenances.scheduled_at', '<=', $date))
            ->orderByDesc('vehicle_maintenances.scheduled_at')
            ->when($this->exportRowLimit !== null, fn ($query) => $query->limit($this->exportRowLimit + 1))
            ->get([
                'vehicle_maintenances.scheduled_at',
                'vehicles.plate',
                'vehicle_maintenances.type',
                'vehicle_maintenances.status',
                'vehicle_maintenances.description',
                'vehicle_maintenances.odometer',
                'vehicle_maintenances.next_due_km',
                'vehicle_maintenances.next_due_at',
                'vehicle_maintenances.cost',
                'vehicle_maintenances.supplier',
                'completed_users.name as completed_by',
            ]);

        return $this->report('Manutencoes da frota', ['Agendamento', 'Placa', 'Tipo', 'Status', 'Descricao', 'KM', 'Proximo KM', 'Proxima data', 'Custo', 'Fornecedor', 'Concluido por'], $rows);
    }

    private function stockMovements(Request $request, array $filters): array
    {
        abort_if($request->user()->cannot('materiais-patrimonio.visualizar'), 403);

        $rows = DB::table('stock_movements')
            ->join('products', 'products.id', '=', 'stock_movements.product_id')
            ->leftJoin('departments', 'departments.id', '=', 'stock_movements.department_id')
            ->leftJoin('users', 'users.id', '=', 'stock_movements.responsible_id')
            ->when(! $this->hasFullModuleAccess($request, 'materiais-patrimonio'), fn ($query) => $query->where('stock_movements.department_id', $request->user()->department_id))
            ->when($filters['status'] ?? null, fn ($query, $type) => $query->where('stock_movements.type', $type))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('stock_movements.movement_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('stock_movements.movement_date', '<=', $date))
            ->orderByDesc('stock_movements.movement_date')
            ->when($this->exportRowLimit !== null, fn ($query) => $query->limit($this->exportRowLimit + 1))
            ->get([
                'stock_movements.movement_date',
                'stock_movements.type',
                'products.internal_code',
                'products.description',
                'stock_movements.quantity',
                'departments.name as department',
                'users.name as responsible',
            ]);

        return $this->report('Movimentacoes de estoque', ['Data', 'Tipo', 'Codigo', 'Produto', 'Quantidade', 'Setor', 'Responsavel'], $rows);
    }

    private function assets(Request $request, array $filters): array
    {
        abort_if($request->user()->cannot('materiais-patrimonio.visualizar'), 403);

        $rows = DB::table('assets')
            ->leftJoin('departments', 'departments.id', '=', 'assets.department_id')
            ->leftJoin('users', 'users.id', '=', 'assets.responsible_id')
            ->whereNull('assets.deleted_at')
            ->when(! $this->hasFullModuleAccess($request, 'materiais-patrimonio'), fn ($query) => $query->where('assets.department_id', $request->user()->department_id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('assets.status', $status))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('assets.acquired_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('assets.acquired_at', '<=', $date))
            ->orderBy('assets.description')
            ->when($this->exportRowLimit !== null, fn ($query) => $query->limit($this->exportRowLimit + 1))
            ->get([
                'assets.asset_number',
                'assets.description',
                'assets.category',
                'assets.brand',
                'assets.model',
                'departments.name as department',
                'users.name as responsible',
                'assets.status',
                'assets.value',
            ]);

        return $this->report('Patrimonios', ['Numero', 'Descricao', 'Categoria', 'Marca', 'Modelo', 'Setor', 'Responsavel', 'Status', 'Valor'], $rows);
    }

    private function itTickets(Request $request, array $filters): array
    {
        abort_if($request->user()->cannot('informatica.visualizar'), 403);

        $rows = DB::table('it_tickets')
            ->leftJoin('departments', 'departments.id', '=', 'it_tickets.department_id')
            ->leftJoin('users', 'users.id', '=', 'it_tickets.assigned_to')
            ->leftJoin('it_sla_rules', 'it_sla_rules.id', '=', 'it_tickets.sla_rule_id')
            ->leftJoin('it_ticket_evaluations', 'it_ticket_evaluations.ticket_id', '=', 'it_tickets.id')
            ->leftJoin('it_root_causes', 'it_root_causes.id', '=', 'it_tickets.root_cause_id')
            ->whereNull('it_tickets.deleted_at')
            ->when(! $this->hasFullModuleAccess($request, 'informatica'), function ($query) use ($request) {
                $query->where(function ($query) use ($request) {
                    $query->where('it_tickets.assigned_to', $request->user()->id)
                        ->orWhere('it_tickets.opened_by', $request->user()->id)
                        ->orWhere('it_tickets.department_id', $request->user()->department_id);
                });
            })
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('it_tickets.status', $status))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('it_tickets.type', $type))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('it_tickets.created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('it_tickets.created_at', '<=', $date))
            ->orderByDesc('it_tickets.created_at')
            ->when($this->exportRowLimit !== null, fn ($query) => $query->limit($this->exportRowLimit + 1))
            ->get([
                'it_tickets.created_at',
                'it_tickets.requester_name',
                'departments.name as department',
                'it_tickets.type',
                'it_tickets.category',
                'it_tickets.priority',
                'it_tickets.status',
                'users.name as assigned_to',
                'it_tickets.sla_due_at',
                'it_sla_rules.name as sla_rule',
                'it_tickets.first_response_at',
                'it_tickets.resolved_at',
                'it_tickets.service_time_minutes',
                'it_tickets.resolution_summary',
                'it_root_causes.name as root_cause_catalog',
                'it_tickets.identified_cause',
                'it_tickets.root_cause',
                'it_tickets.applied_solution',
                'it_tickets.executed_procedure',
                'it_tickets.replaced_part',
                'it_tickets.requires_follow_up',
                'it_tickets.recurring_problem',
                'it_ticket_evaluations.rating',
                'it_ticket_evaluations.comment as evaluation_comment',
            ]);

        return $this->report('Chamados de informatica', ['Abertura', 'Solicitante', 'Setor', 'Tipo', 'Categoria', 'Prioridade', 'Status', 'Tecnico', 'SLA', 'Regra SLA', 'Primeira resposta', 'Resolucao', 'Tempo atendimento', 'Resumo solucao', 'Causa catalogada', 'Causa identificada', 'Causa raiz', 'Solucao aplicada', 'Procedimento', 'Peca', 'Acompanhamento', 'Recorrente', 'Nota', 'Comentario avaliacao'], $rows);
    }

    private function itSla(Request $request, array $filters): array
    {
        $rows = $this->itTicketBase($request, $filters)
            ->when($this->exportRowLimit !== null, fn ($query) => $query->limit($this->exportRowLimit + 1))
            ->get(['it_tickets.id', 'it_tickets.status', 'it_tickets.sla_overdue', 'it_tickets.sla_due_at', 'it_tickets.resolved_at']);
        $this->ensureWithinExportLimit($rows);
        $total = $rows->count();
        $overdue = $rows->filter(fn ($row) => (bool) $row->sla_overdue || ($row->sla_due_at && now()->gt($row->sla_due_at) && ! in_array($row->status, ['resolvido', 'encerrado'], true)))->count();
        $compliant = max(0, $total - $overdue);

        return $this->report('Cumprimento de SLA', ['Indicador', 'Quantidade', 'Percentual'], [
            ['Chamados analisados', $total, $total ? '100%' : '0%'],
            ['Dentro do SLA', $compliant, $total ? round($compliant / $total * 100, 1).'%' : '0%'],
            ['Fora do SLA', $overdue, $total ? round($overdue / $total * 100, 1).'%' : '0%'],
        ]);
    }

    private function itTechnicians(Request $request, array $filters): array
    {
        $rows = $this->itTicketBase($request, $filters)
            ->leftJoin('users', 'users.id', '=', 'it_tickets.assigned_to')
            ->whereNotNull('it_tickets.assigned_to')
            ->groupBy('it_tickets.assigned_to', 'users.name')
            ->orderByDesc('total')
            ->when($this->exportRowLimit !== null, fn ($query) => $query->limit($this->exportRowLimit + 1))
            ->get(['users.name as technician', DB::raw('COUNT(it_tickets.id) as total'), DB::raw("SUM(CASE WHEN it_tickets.status IN ('resolvido','encerrado') THEN 1 ELSE 0 END) as resolved"), DB::raw('ROUND(AVG(it_tickets.service_time_minutes), 1) as avg_service_minutes')]);

        return $this->report('Desempenho por tecnico', ['Tecnico', 'Chamados', 'Resolvidos', 'Tempo medio (min)'], $rows);
    }

    private function itDepartments(Request $request, array $filters): array
    {
        $rows = $this->itTicketBase($request, $filters)
            ->leftJoin('departments', 'departments.id', '=', 'it_tickets.department_id')
            ->groupBy('it_tickets.department_id', 'departments.name')
            ->orderByDesc('total')
            ->when($this->exportRowLimit !== null, fn ($query) => $query->limit($this->exportRowLimit + 1))
            ->get([DB::raw("COALESCE(departments.name, 'Sem setor') as department"), DB::raw('COUNT(it_tickets.id) as total'), DB::raw("SUM(CASE WHEN it_tickets.status IN ('resolvido','encerrado') THEN 1 ELSE 0 END) as resolved")]);

        return $this->report('Chamados por setor', ['Setor', 'Chamados', 'Resolvidos'], $rows);
    }

    private function itCategories(Request $request, array $filters): array
    {
        $rows = $this->itTicketBase($request, $filters)->groupBy('it_tickets.category')->orderByDesc('total')->when($this->exportRowLimit !== null, fn ($query) => $query->limit($this->exportRowLimit + 1))->get([DB::raw("COALESCE(it_tickets.category, 'Sem categoria') as category"), DB::raw('COUNT(it_tickets.id) as total')]);

        return $this->report('Chamados por categoria', ['Categoria', 'Chamados'], $rows);
    }

    private function itTypes(Request $request, array $filters): array
    {
        $rows = $this->itTicketBase($request, $filters)->groupBy('it_tickets.type')->orderByDesc('total')->when($this->exportRowLimit !== null, fn ($query) => $query->limit($this->exportRowLimit + 1))->get(['it_tickets.type', DB::raw('COUNT(it_tickets.id) as total')]);

        return $this->report('Chamados por tipo', ['Tipo', 'Chamados'], $rows);
    }

    private function itRecurrence(Request $request, array $filters): array
    {
        $rows = $this->itTicketBase($request, $filters)
            ->leftJoin('assets', 'assets.id', '=', 'it_tickets.primary_asset_id')
            ->leftJoin('departments', 'departments.id', '=', 'it_tickets.department_id')
            ->where(function ($query) {
                $query->where('it_tickets.recurring_problem', true)->orWhereNotNull('it_tickets.identified_cause');
            })
            ->groupBy('assets.asset_number', 'it_tickets.category', 'departments.name', 'it_tickets.requester_name', 'it_tickets.identified_cause')
            ->orderByDesc('total')
            ->when($this->exportRowLimit !== null, fn ($query) => $query->limit($this->exportRowLimit + 1))
            ->get([
                DB::raw("COALESCE(assets.asset_number, 'Sem equipamento') as asset_number"),
                DB::raw("COALESCE(it_tickets.category, 'Sem categoria') as category"),
                DB::raw("COALESCE(departments.name, 'Sem setor') as department"),
                DB::raw("COALESCE(it_tickets.requester_name, 'Sem solicitante') as requester_name"),
                DB::raw("COALESCE(it_tickets.identified_cause, 'Sem causa') as identified_cause"),
                DB::raw('COUNT(it_tickets.id) as total'),
            ]);

        $grouped = $rows->map(fn ($row) => [$row->asset_number, $row->category, $row->department, $row->requester_name, $row->identified_cause, (int) $row->total]);

        return $this->report('Reincidencia de chamados', ['Equipamento', 'Categoria', 'Setor', 'Solicitante', 'Causa', 'Ocorrencias'], $grouped);
    }

    private function itAssets(Request $request, array $filters): array
    {
        $rows = $this->itTicketBase($request, $filters)
            ->join('assets', 'assets.id', '=', 'it_tickets.primary_asset_id')
            ->groupBy('assets.id', 'assets.asset_number', 'assets.description')
            ->orderByDesc('total')
            ->when($this->exportRowLimit !== null, fn ($query) => $query->limit($this->exportRowLimit + 1))
            ->get(['assets.asset_number', 'assets.description', DB::raw('COUNT(it_tickets.id) as total'), DB::raw("SUM(CASE WHEN it_tickets.status IN ('resolvido','encerrado') THEN 1 ELSE 0 END) as resolved")]);

        return $this->report('Chamados por equipamento', ['Patrimonio', 'Equipamento', 'Chamados', 'Resolvidos'], $rows);
    }

    private function itEvaluations(Request $request, array $filters): array
    {
        $rows = $this->itTicketBase($request, $filters)
            ->join('it_ticket_evaluations', 'it_ticket_evaluations.ticket_id', '=', 'it_tickets.id')
            ->leftJoin('users', 'users.id', '=', 'it_tickets.assigned_to')
            ->orderByDesc('it_ticket_evaluations.created_at')
            ->when($this->exportRowLimit !== null, fn ($query) => $query->limit($this->exportRowLimit + 1))
            ->get(['it_tickets.id', 'it_tickets.requester_name', 'users.name as technician', 'it_ticket_evaluations.rating', 'it_ticket_evaluations.comment', 'it_ticket_evaluations.created_at']);

        return $this->report('Avaliacoes de atendimento', ['Chamado', 'Solicitante', 'Tecnico', 'Nota', 'Comentario', 'Data'], $rows);
    }

    private function itRootCauses(Request $request, array $filters): array
    {
        $rows = $this->itTicketBase($request, $filters)->leftJoin('it_root_causes', 'it_root_causes.id', '=', 'it_tickets.root_cause_id')->where(function ($query) {
            $query->whereNotNull('it_tickets.root_cause_id')->orWhereNotNull('it_tickets.root_cause')->orWhereNotNull('it_tickets.identified_cause');
        })->groupBy('it_root_causes.name', 'it_tickets.root_cause', 'it_tickets.identified_cause')->orderByDesc('total')->when($this->exportRowLimit !== null, fn ($query) => $query->limit($this->exportRowLimit + 1))->get([DB::raw("COALESCE(it_root_causes.name, it_tickets.root_cause, it_tickets.identified_cause, 'Sem causa') as cause"), DB::raw('COUNT(it_tickets.id) as total'), DB::raw('ROUND(AVG(it_tickets.service_time_minutes), 1) as avg_service_minutes')]);

        return $this->report('Causas raiz', ['Causa', 'Chamados', 'Tempo medio (min)'], $rows);
    }

    private function itMetrics(Request $request, array $filters): array
    {
        $base = $this->itTicketBase($request, $filters);
        $driver = DB::connection()->getDriverName();
        $mttaExpression = $driver === 'sqlite' ? '(julianday(first_response_at) - julianday(created_at)) * 1440' : '(TIMESTAMPDIFF(MICROSECOND, created_at, first_response_at) / 60000000.0)';
        $mttrExpression = $driver === 'sqlite' ? '(julianday(resolved_at) - julianday(created_at)) * 1440' : '(TIMESTAMPDIFF(MICROSECOND, created_at, resolved_at) / 60000000.0)';
        $aggregate = (clone $base)->selectRaw("AVG(CASE WHEN first_response_at IS NOT NULL THEN {$mttaExpression} END) as mtta, AVG(CASE WHEN resolved_at IS NOT NULL THEN {$mttrExpression} END) as mttr, AVG(service_time_minutes) as service_avg, SUM(CASE WHEN first_response_at IS NOT NULL THEN 1 ELSE 0 END) as response_count, SUM(CASE WHEN resolved_at IS NOT NULL THEN 1 ELSE 0 END) as resolved_count, COUNT(*) as total")->first();

        return $this->report('Indicadores MTTA e MTTR', ['Indicador', 'Valor', 'Chamados considerados'], [
            ['MTTA (minutos)', round((float) $aggregate->mtta, 1), (int) $aggregate->response_count],
            ['MTTR (minutos)', round((float) $aggregate->mttr, 1), (int) $aggregate->resolved_count],
            ['Tempo medio de atendimento (minutos)', round((float) $aggregate->service_avg, 1), (int) $aggregate->total],
        ]);
    }

    private function itBacklog(Request $request, array $filters): array
    {
        $rows = $this->itTicketBase($request, $filters)->whereNotIn('it_tickets.status', ['resolvido', 'encerrado', 'cancelado'])->groupBy('it_tickets.status')->orderByDesc('total')->when($this->exportRowLimit !== null, fn ($query) => $query->limit($this->exportRowLimit + 1))->get(['it_tickets.status', DB::raw('COUNT(it_tickets.id) as total')]);

        return $this->report('Backlog de chamados', ['Status', 'Quantidade'], $rows);
    }

    private function itAging(Request $request, array $filters): array
    {
        $rows = $this->itTicketBase($request, $filters)->whereNotIn('it_tickets.status', ['resolvido', 'encerrado', 'cancelado'])->when($this->exportRowLimit !== null, fn ($query) => $query->limit($this->exportRowLimit + 1))->get(['created_at']);
        $this->ensureWithinExportLimit($rows);
        $buckets = ['0-2 dias' => 0, '3-7 dias' => 0, '8-30 dias' => 0, '31-60 dias' => 0, '61+ dias' => 0];
        foreach ($rows as $row) {
            $days = Carbon::parse($row->created_at)->diffInDays(now());
            $bucket = $days <= 2 ? '0-2 dias' : ($days <= 7 ? '3-7 dias' : ($days <= 30 ? '8-30 dias' : ($days <= 60 ? '31-60 dias' : '61+ dias')));
            $buckets[$bucket]++;
        }

        return $this->report('Aging do backlog', ['Faixa', 'Quantidade'], collect($buckets)->map(fn ($total, $bucket) => [$bucket, $total]));
    }

    private function itTicketBase(Request $request, array $filters)
    {
        return DB::table('it_tickets')
            ->whereNull('it_tickets.deleted_at')
            ->when(! $this->hasFullModuleAccess($request, 'informatica'), function ($query) use ($request) {
                $query->where(fn ($q) => $q->where('it_tickets.assigned_to', $request->user()->id)->orWhere('it_tickets.opened_by', $request->user()->id)->orWhere('it_tickets.department_id', $request->user()->department_id));
            })
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('it_tickets.status', $status))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('it_tickets.type', $type))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('it_tickets.created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('it_tickets.created_at', '<=', $date));
    }

    private function auditLogs(Request $request, array $filters): array
    {
        abort_if($request->user()->cannot('auditoria.visualizar'), 403);

        $rows = DB::table('activity_log')
            ->leftJoin('users', 'users.id', '=', 'activity_log.causer_id')
            ->when($filters['status'] ?? null, fn ($query, $module) => $query->where('activity_log.log_name', $module))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('activity_log.created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('activity_log.created_at', '<=', $date))
            ->orderByDesc('activity_log.created_at')
            ->when($this->exportRowLimit !== null, fn ($query) => $query->limit($this->exportRowLimit + 1))
            ->get([
                'activity_log.created_at',
                'activity_log.log_name',
                'activity_log.description',
                'users.name as causer',
                'activity_log.subject_type',
                'activity_log.subject_id',
            ]);

        return $this->report('Auditoria', ['Data/hora', 'Modulo', 'Acao', 'Usuario', 'Tipo registro', 'ID registro'], $rows);
    }

    private function hasFullModuleAccess(Request $request, string $module): bool
    {
        return $request->user()->hasRole('Administrador Geral') || $request->user()->can("{$module}.aprovar");
    }

    public function canView(Request $request, string $report): bool
    {
        return match ($report) {
            'transport_requests', 'transport_maintenances' => $request->user()->can('transporte.visualizar'),
            'stock_movements', 'assets' => $request->user()->can('materiais-patrimonio.visualizar'),
            'it_tickets', 'it_sla', 'it_technicians', 'it_departments', 'it_categories', 'it_types', 'it_recurrence', 'it_assets', 'it_evaluations', 'it_root_causes', 'it_metrics', 'it_backlog', 'it_aging' => $request->user()->can('informatica.visualizar'),
            'audit_logs' => $request->user()->can('auditoria.visualizar'),
            default => false,
        };
    }

    private function report(string $title, array $headings, iterable $rows): array
    {
        $rows = collect($rows)->map(fn ($row) => array_values((array) $row));
        $this->ensureWithinExportLimit($rows);

        return [
            'title' => $title,
            'headings' => $headings,
            'rows' => $rows->all(),
        ];
    }

    private function ensureWithinExportLimit(iterable $rows): void
    {
        if ($this->exportRowLimit !== null && count($rows) > $this->exportRowLimit) {
            throw new ReportExportLimitExceeded($this->exportRowLimit);
        }
    }
}
