<?php

namespace App\Http\Controllers\Api\It;

use App\Http\Controllers\Controller;
use App\Models\ItTicket;
use App\Models\ItTicketEvaluation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ItTicketEvaluationController extends Controller
{
    public function store(Request $request, ItTicket $ticket): JsonResponse
    {
        abort_if(! in_array($ticket->status, ['resolvido', 'encerrado'], true), 422, 'Chamado ainda nao pode ser avaliado.');
        abort_if($ticket->opened_by !== $request->user()->id && ! $request->user()->can('informatica.aprovar'), 403);

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $existing = ItTicketEvaluation::query()->where('ticket_id', $ticket->id)->first();
        abort_if($existing && ! $request->user()->can('informatica.aprovar'), 422, 'Este chamado ja foi avaliado.');

        $evaluation = ItTicketEvaluation::updateOrCreate(
            ['ticket_id' => $ticket->id],
            [...$data, 'user_id' => $request->user()->id, 'evaluated_at' => now()]
        );

        return response()->json($evaluation->load('user:id,name'), $evaluation->wasRecentlyCreated ? 201 : 200);
    }

    public function stats(Request $request): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.visualizar'), 403);
        $ticketScope = ItTicket::query()
            ->when(! $request->user()->hasRole('Administrador Geral') && ! $request->user()->can('informatica.aprovar'), function ($query) use ($request) {
                $query->where(function ($query) use ($request) {
                    $query->where('assigned_to', $request->user()->id)
                        ->orWhere('opened_by', $request->user()->id)
                        ->orWhere('department_id', $request->user()->department_id);
                });
            });
        $ticketIds = $ticketScope->pluck('id');
        $monthExpression = DB::connection()->getDriverName() === 'sqlite'
            ? 'strftime("%Y-%m", evaluated_at)'
            : "date_format(evaluated_at, '%Y-%m')";

        return response()->json([
            'overall_avg' => round((float) ItTicketEvaluation::query()->whereIn('ticket_id', $ticketIds)->avg('rating'), 2),
            'by_technician' => DB::table('it_ticket_evaluations')
                ->join('it_tickets', 'it_tickets.id', '=', 'it_ticket_evaluations.ticket_id')
                ->whereIn('it_tickets.id', $ticketIds)
                ->leftJoin('users', 'users.id', '=', 'it_tickets.assigned_to')
                ->selectRaw('users.name as technician, round(avg(it_ticket_evaluations.rating), 2) as average, count(*) as total')
                ->groupBy('users.name')
                ->orderByDesc('average')
                ->get(),
            'by_category' => DB::table('it_ticket_evaluations')
                ->join('it_tickets', 'it_tickets.id', '=', 'it_ticket_evaluations.ticket_id')
                ->whereIn('it_tickets.id', $ticketIds)
                ->selectRaw("coalesce(it_tickets.category, 'Sem categoria') as category, round(avg(it_ticket_evaluations.rating), 2) as average, count(*) as total")
                ->groupBy('it_tickets.category')
                ->orderByDesc('average')
                ->get(),
            'monthly' => DB::table('it_ticket_evaluations')
                ->whereIn('ticket_id', $ticketIds)
                ->selectRaw("{$monthExpression} as month, round(avg(rating), 2) as average, count(*) as total")
                ->groupBy('month')
                ->orderBy('month')
                ->get(),
        ]);
    }
}
