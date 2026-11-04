<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ItTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $departmentId = $user->hasRole('Administrador Geral') ? null : $user->department_id;
        $payload = [
            'charts' => [],
            'work_items' => [],
        ];

        if ($user->can('transporte.visualizar')) {
            $payload['transportes'] = [
                'veiculos' => DB::table('vehicles')->whereNull('deleted_at')->count(),
                'solicitacoes_abertas' => $this->departmentScope('vehicle_requests', $departmentId)->whereNotIn('status', ['concluida', 'cancelada'])->count(),
                'manutencao_proxima' => DB::table('vehicles')->whereDate('last_maintenance_at', '<=', now()->addDays(30))->count(),
            ];
            $payload['charts']['solicitacoes_transporte'] = $this->statusChart(
                $this->departmentScope('vehicle_requests', $departmentId)->whereNull('deleted_at'),
                'status',
                [
                    'solicitacao' => 'Solicitacao',
                    'analise' => 'Analise',
                    'aprovada' => 'Aprovada',
                    'em_deslocamento' => 'Em deslocamento',
                    'concluida' => 'Concluida',
                    'cancelada' => 'Cancelada',
                    'rejeitada' => 'Rejeitada',
                ]
            );
        }

        if ($user->can('materiais-patrimonio.visualizar')) {
            $payload['materiais_patrimonio'] = [
                'produtos_abaixo_minimo' => DB::table('products')->whereColumn('quantity', '<=', 'minimum_quantity')->whereNull('deleted_at')->count(),
                'patrimonios_ativos' => $this->departmentScope('assets', $departmentId)->where('status', 'ativo')->whereNull('deleted_at')->count(),
            ];
            $payload['charts']['patrimonios'] = $this->statusChart(
                $this->departmentScope('assets', $departmentId)->whereNull('deleted_at'),
                'status',
                [
                    'ativo' => 'Ativo',
                    'manutencao' => 'Manutencao',
                    'baixado' => 'Baixado',
                    'inativo' => 'Inativo',
                ]
            );
        }

        if ($user->can('informatica.visualizar')) {
            $ticketScope = $this->itTicketScope($request);
            $payload['informatica'] = [
                'chamados_abertos' => (clone $ticketScope)->whereNotIn('status', ['resolvido', 'encerrado'])->count(),
                'sla_vencendo' => (clone $ticketScope)->whereBetween('sla_due_at', [now(), now()->addDay()])->count(),
            ];
            $payload['charts']['chamados_por_prioridade'] = $this->statusChart(
                $this->itTicketScope($request),
                'priority',
                [
                    'baixa' => 'Baixa',
                    'media' => 'Media',
                    'alta' => 'Alta',
                    'urgente' => 'Urgente',
                ]
            );
            $payload['charts']['chamados_por_tipo'] = $this->statusChart(
                $this->itTicketScope($request),
                'type',
                collect(ItTicket::TYPES)->map(fn (array $meta) => $meta['label'])->all()
            );
        }

        $payload['work_items'] = $this->workItems($request, $departmentId);

        return response()->json($payload);
    }

    private function departmentScope(string $table, ?int $departmentId): \Illuminate\Database\Query\Builder
    {
        $query = DB::table($table);

        if ($departmentId !== null) {
            $query->where("{$table}.department_id", $departmentId);
        }

        return $query;
    }

    /**
     * @param array<string, string> $labels
     * @return array<int, array{key: string, label: string, value: int}>
     */
    private function statusChart(\Illuminate\Database\Query\Builder $query, string $column, array $labels): array
    {
        $counts = $query
            ->select($column, DB::raw('count(*) as total'))
            ->groupBy($column)
            ->pluck('total', $column);

        return collect($labels)
            ->map(fn (string $label, string $key) => [
                'key' => $key,
                'label' => $label,
                'value' => (int) ($counts[$key] ?? 0),
            ])
            ->values()
            ->all();
    }

    private function itTicketScope(Request $request): \Illuminate\Database\Query\Builder
    {
        $user = $request->user();

        return DB::table('it_tickets')
            ->whereNull('deleted_at')
            ->when(! $user->hasRole('Administrador Geral') && ! $user->can('informatica.aprovar'), function ($query) use ($user) {
                $query->where(function ($query) use ($user) {
                    $query->where('assigned_to', $user->id)
                        ->orWhere('opened_by', $user->id)
                        ->orWhere('department_id', $user->department_id);
                });
            });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function workItems(Request $request, ?int $departmentId): array
    {
        $items = collect();
        $user = $request->user();

        if ($user->can('transporte.visualizar')) {
            $items = $items->merge($this->transportWorkItems($departmentId));
        }

        if ($user->can('informatica.visualizar')) {
            $items = $items->merge($this->itWorkItems($request));
        }

        return $items
            ->sortByDesc('created_at')
            ->take(8)
            ->values()
            ->map(fn (array $item) => collect($item)->except('created_at')->all())
            ->all();
    }

    private function transportWorkItems(?int $departmentId): Collection
    {
        return $this->departmentScope('vehicle_requests', $departmentId)
            ->leftJoin('departments', 'departments.id', '=', 'vehicle_requests.department_id')
            ->whereNull('vehicle_requests.deleted_at')
            ->whereNotIn('vehicle_requests.status', ['concluida', 'cancelada', 'rejeitada'])
            ->latest('vehicle_requests.created_at')
            ->limit(8)
            ->get([
                'vehicle_requests.id',
                'vehicle_requests.status',
                'vehicle_requests.origin',
                'vehicle_requests.destination',
                'vehicle_requests.date',
                'vehicle_requests.created_at',
                'departments.name as department',
            ])
            ->map(fn ($request) => [
                'id' => "transport-{$request->id}",
                'module' => 'Transporte',
                'title' => "Solicitacao de veiculo #{$request->id}",
                'detail' => "{$request->department}: {$request->origin} para {$request->destination} em ".\Illuminate\Support\Carbon::parse($request->date)->format('d/m/Y'),
                'status' => $this->humanStatus((string) $request->status),
                'icon' => 'route',
                'color' => 'primary',
                'badgeColor' => $this->badgeColor((string) $request->status),
                'to' => '/transporte',
                'created_at' => $request->created_at,
            ]);
    }

    private function itWorkItems(Request $request): Collection
    {
        return $this->itTicketScope($request)
            ->leftJoin('departments', 'departments.id', '=', 'it_tickets.department_id')
            ->whereNotIn('it_tickets.status', ['resolvido', 'encerrado'])
            ->latest('it_tickets.created_at')
            ->limit(8)
            ->get([
                'it_tickets.id',
                'it_tickets.requester_name',
                'it_tickets.category',
                'it_tickets.type',
                'it_tickets.priority',
                'it_tickets.status',
                'it_tickets.sla_due_at',
                'it_tickets.created_at',
                'departments.name as department',
            ])
            ->map(fn ($ticket) => [
                'id' => "it-{$ticket->id}",
                'module' => 'Informatica',
                'title' => "Chamado #{$ticket->id} - {$ticket->requester_name}",
                'detail' => trim("{$this->humanStatus((string) $ticket->type)} - {$ticket->department}: {$ticket->category}"),
                'status' => $this->humanStatus((string) $ticket->status),
                'icon' => 'desktop_windows',
                'color' => $ticket->priority === 'urgente' ? 'negative' : 'secondary',
                'badgeColor' => $this->badgeColor((string) $ticket->status),
                'to' => '/informatica',
                'created_at' => $ticket->created_at,
            ]);
    }

    private function humanStatus(string $status): string
    {
        return str($status)->replace('_', ' ')->headline()->toString();
    }

    private function badgeColor(string $status): string
    {
        return match ($status) {
            'aprovada', 'concluida', 'resolvido', 'encerrado' => 'positive',
            'cancelada', 'rejeitada', 'urgente' => 'negative',
            'analise', 'triagem', 'aguardando_usuario' => 'warning',
            default => 'primary',
        };
    }
}
