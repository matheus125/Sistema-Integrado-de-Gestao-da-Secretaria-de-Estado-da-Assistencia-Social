<?php

namespace App\Http\Controllers\Api\It;

use App\Http\Controllers\Controller;
use App\Models\ItTicketApproval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ItTicketApprovalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.visualizar'), 403);
        $user = $request->user();
        $query = ItTicketApproval::query()->with(['ticket:id,requester_name,description,status,approval_status', 'requester:id,name', 'approver:id,name']);
        if (! $user->hasRole('Administrador Geral') && ! $user->can('informatica.aprovar')) {
            $query->where(function ($q) use ($user) { $q->where('requested_by', $user->id)->orWhere('approver_id', $user->id); });
        }
        return response()->json(['data' => $query->latest()->paginate($request->integer('per_page', 20))]);
    }

    public function decide(Request $request, ItTicketApproval $approval): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.aprovar'), 403);
        abort_if($approval->status !== 'pendente', 422, 'Esta aprovação já foi decidida.');
        $data = $request->validate(['status' => ['required', Rule::in(['aprovado', 'rejeitado'])], 'comment' => ['nullable', 'string', 'max:2000']]);
        $approval->update(['status' => $data['status'], 'comment' => $data['comment'] ?? null, 'approver_id' => $request->user()->id, 'decided_at' => now()]);
        $approval->ticket()->update(['approval_status' => $data['status']]);
        activity('informatica')->causedBy($request->user())->performedOn($approval->ticket)->withProperties(['approval_id' => $approval->id, 'status' => $data['status']])->log('aprovação decidida');
        return response()->json($approval->fresh()->load(['ticket:id,requester_name,description,status,approval_status', 'approver:id,name']));
    }
}
