<?php

namespace App\Services\It;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ItMetricsService
{
    public function operational(Request $request): array
    {
        $scope = $this->scope($request);
        $open = (clone $scope)->whereNotIn('status', ['resolvido', 'encerrado', 'cancelado']);

        return [
            'open' => (clone $open)->count(),
            'triage' => (clone $scope)->where('status', 'triagem')->count(),
            'in_progress' => (clone $scope)->where('status', 'em_atendimento')->count(),
            'waiting_user' => (clone $scope)->where('status', 'aguardando_usuario')->count(),
            'sla_due_soon' => (clone $open)->whereBetween('sla_due_at', [now(), now()->addDay()])->count(),
            'sla_overdue' => (clone $open)->where('sla_due_at', '<', now())->count(),
            'resolved_today' => (clone $scope)->whereDate('resolved_at', now()->toDateString())->count(),
            'resolved_month' => (clone $scope)->whereBetween('resolved_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
            'reopened' => (clone $scope)->where('reopen_count', '>', 0)->count(),
            'unassigned' => (clone $open)->whereNull('assigned_to')->count(),
            'avg_rating' => round((float) DB::table('it_ticket_evaluations')->avg('rating'), 2),
            'mtta_minutes' => $this->averageMinutesQuery($scope, 'first_response_at'),
            'mttr_minutes' => $this->averageMinutesQuery($scope, 'resolved_at'),
            'backlog' => (clone $open)->count(),
            'sla_compliance_percent' => $this->slaCompliance($scope),
            'reopen_percent' => $this->percent((clone $scope)->where('reopen_count', '>', 0)->count(), (clone $scope)->count()),
            'aging' => $this->aging($open),
        ];
    }

    public function charts(Request $request): array
    {
        return [
            'by_status' => $this->group($this->scope($request), 'status'),
            'by_priority' => $this->group($this->scope($request), 'priority'),
            'by_category' => $this->group($this->scope($request), 'category'),
            'by_type' => $this->group($this->scope($request), 'type'),
            'by_department' => $this->group($this->scope($request)->leftJoin('departments', 'departments.id', '=', 'it_tickets.department_id'), 'departments.name'),
            'by_technician' => $this->group($this->scope($request)->leftJoin('users', 'users.id', '=', 'it_tickets.assigned_to'), 'users.name'),
        ];
    }

    private function scope(Request $request)
    {
        $user = $request->user();

        return DB::table('it_tickets')
            ->whereNull('it_tickets.deleted_at')
            ->when(! $user->hasRole('Administrador Geral') && ! $user->can('informatica.aprovar'), function ($query) use ($user) {
                $query->where(function ($query) use ($user) {
                    $query->where('it_tickets.assigned_to', $user->id)
                        ->orWhere('it_tickets.opened_by', $user->id)
                        ->orWhere('it_tickets.department_id', $user->department_id);
                });
            });
    }

    private function group($query, string $column): array
    {
        return $query->selectRaw("coalesce({$column}, 'Sem informacao') as label, count(*) as value")
            ->groupBy('label')
            ->orderByDesc('value')
            ->limit(12)
            ->get()
            ->all();
    }

    private function slaCompliance($scope): float
    {
        $resolved = (clone $scope)->whereNotNull('resolved_at')->count();
        return $this->percent((clone $scope)->whereNotNull('resolved_at')->whereColumn('resolved_at', '<=', 'sla_due_at')->count(), $resolved);
    }

    private function percent(int $value, int $total): float
    {
        return $total > 0 ? round(($value / $total) * 100, 1) : 0.0;
    }

    private function aging($open): array
    {
        $now = now();
        $rows = (clone $open)->get(['created_at']);

        return [
            '0-4h' => $rows->filter(fn ($row) => $now->diffInHours($row->created_at) < 4)->count(),
            '4-8h' => $rows->filter(fn ($row) => $now->diffInHours($row->created_at) >= 4 && $now->diffInHours($row->created_at) < 8)->count(),
            '8-24h' => $rows->filter(fn ($row) => $now->diffInHours($row->created_at) >= 8 && $now->diffInHours($row->created_at) < 24)->count(),
            '1-3d' => $rows->filter(fn ($row) => $now->diffInDays($row->created_at) >= 1 && $now->diffInDays($row->created_at) < 3)->count(),
            '3-7d' => $rows->filter(fn ($row) => $now->diffInDays($row->created_at) >= 3 && $now->diffInDays($row->created_at) < 7)->count(),
            '7d+' => $rows->filter(fn ($row) => $now->diffInDays($row->created_at) >= 7)->count(),
        ];
    }

    private function averageMinutes($rows, string $from, string $to): float
    {
        if ($rows->isEmpty()) {
            return 0.0;
        }

        return round($rows->avg(fn ($row) => \Illuminate\Support\Carbon::parse($row->{$from})->diffInMinutes(\Illuminate\Support\Carbon::parse($row->{$to}))), 1);
    }

    private function averageMinutesQuery($scope, string $to): float
    {
        $expression = DB::connection()->getDriverName() === 'sqlite'
            ? "(julianday({$to}) - julianday(created_at)) * 1440"
            : "(TIMESTAMPDIFF(MICROSECOND, created_at, {$to}) / 60000000.0)";

        return round((float) (clone $scope)->whereNotNull($to)->avg(DB::raw($expression)), 1);
    }
}
