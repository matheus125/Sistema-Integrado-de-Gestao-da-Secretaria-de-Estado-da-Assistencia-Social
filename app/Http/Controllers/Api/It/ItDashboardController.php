<?php

namespace App\Http\Controllers\Api\It;

use App\Http\Controllers\Controller;
use App\Models\ItTicket;
use App\Services\It\ItMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ItDashboardController extends Controller
{
    public function operational(Request $request, ItMetricsService $metrics): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.visualizar'), 403);

        return response()->json([
            'indicators' => $metrics->operational($request),
            'charts' => $metrics->charts($request),
        ]);
    }

    public function technician(Request $request): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.visualizar'), 403);
        $user = $request->user();
        $base = ItTicket::query()->where('assigned_to', $user->id)->whereNull('deleted_at');
        $activeStatuses = ['resolvido', 'encerrado', 'cancelado'];

        return response()->json([
            'my_tickets' => (clone $base)->whereNotIn('status', $activeStatuses)->count(),
            'urgent' => (clone $base)->where('priority', 'urgente')->whereNotIn('status', $activeStatuses)->count(),
            'sla_due' => (clone $base)->whereBetween('sla_due_at', [now(), now()->addDay()])->count(),
            'sla_overdue' => (clone $base)->where('sla_due_at', '<', now())->whereNotIn('status', $activeStatuses)->count(),
            'waiting_action' => (clone $base)->whereIn('status', ['triagem', 'em_atendimento'])->count(),
            'requester_replied' => (clone $base)->whereNotNull('last_requester_reply_at')->whereColumn('last_requester_reply_at', '>', 'updated_at')->count(),
            'resolved_today' => (clone $base)->whereDate('resolved_at', now()->toDateString())->count(),
            'monthly_performance' => (clone $base)->whereBetween('resolved_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
            'avg_rating' => round((float) DB::table('it_ticket_evaluations')->join('it_tickets', 'it_tickets.id', '=', 'it_ticket_evaluations.ticket_id')->where('it_tickets.assigned_to', $user->id)->avg('rating'), 2),
            'recommended' => (clone $base)
                ->whereNotIn('status', $activeStatuses)
                ->orderByRaw('case when sla_due_at < ? then 0 else 1 end', [now()])
                ->orderBy('sla_due_at')
                ->orderByRaw("case priority when 'urgente' then 0 when 'alta' then 1 when 'media' then 2 else 3 end")
                ->oldest()
                ->limit(10)
                ->get(['id', 'description', 'priority', 'status', 'sla_due_at', 'created_at']),
        ]);
    }

    public function recurrences(Request $request): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.visualizar'), 403);
        $from = now()->subMonths(3);

        return response()->json([
            'by_equipment' => $this->recurrence('primary_asset_id', $from),
            'by_category' => $this->recurrence('category_id', $from),
            'by_department' => $this->recurrence('department_id', $from),
            'by_requester' => $this->recurrence('opened_by', $from),
            'by_root_cause' => $this->recurrence('root_cause_id', $from),
        ]);
    }

    private function recurrence(string $column, $from)
    {
        return ItTicket::query()
            ->whereNotNull($column)
            ->where('created_at', '>=', $from)
            ->selectRaw("{$column} as `key`, count(*) as total")
            ->groupBy($column)
            ->having('total', '>=', 3)
            ->orderByDesc('total')
            ->limit(20)
            ->get();
    }
}
