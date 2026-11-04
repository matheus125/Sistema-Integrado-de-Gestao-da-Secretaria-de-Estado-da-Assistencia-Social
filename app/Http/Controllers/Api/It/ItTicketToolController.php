<?php

namespace App\Http\Controllers\Api\It;

use App\Http\Controllers\Controller;
use App\Models\ItTicket;
use App\Models\ItTicketChecklistItem;
use App\Models\ItTicketRelationship;
use App\Models\ItTicketTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ItTicketToolController extends Controller
{
    public function addFollower(Request $request, ItTicket $ticket): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.editar'), 403);
        $this->authorizeTicketAccess($request, $ticket);
        $data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);
        $existing = $ticket->followers()->whereKey($data['user_id'])->first();
        if ($existing) {
            $ticket->followers()->updateExistingPivot($data['user_id'], ['added_by' => $request->user()->id, 'removed_at' => null, 'removed_by' => null]);
        } else {
            $ticket->followers()->syncWithoutDetaching([$data['user_id'] => ['added_by' => $request->user()->id]]);
        }

        return response()->json($ticket->load('followers:id,name,email'));
    }

    public function removeFollower(Request $request, ItTicket $ticket, int $userId): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.editar'), 403);
        $this->authorizeTicketAccess($request, $ticket);
        $ticket->followers()->updateExistingPivot($userId, ['removed_at' => now(), 'removed_by' => $request->user()->id]);

        return response()->json($ticket->load('followers:id,name,email'));
    }

    public function storeTask(Request $request, ItTicket $ticket): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.editar'), 403);
        $this->authorizeTicketAccess($request, $ticket);
        $task = $ticket->tasks()->create($request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'due_at' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(['pendente', 'em_andamento', 'concluida', 'cancelada'])],
            'priority' => ['nullable', Rule::in(['baixa', 'media', 'alta', 'urgente'])],
            'required' => ['nullable', 'boolean'],
        ]));

        return response()->json($task, 201);
    }

    public function updateTask(Request $request, ItTicket $ticket, ItTicketTask $task): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.editar') || $task->ticket_id !== $ticket->id, 403);
        $this->authorizeTicketAccess($request, $ticket);
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'due_at' => ['nullable', 'date'],
            'status' => ['sometimes', Rule::in(['pendente', 'em_andamento', 'concluida', 'cancelada'])],
            'priority' => ['sometimes', Rule::in(['baixa', 'media', 'alta', 'urgente'])],
            'required' => ['nullable', 'boolean'],
        ]);
        if (($data['status'] ?? null) === 'concluida') {
            $data['completed_at'] = now();
            $data['completed_by'] = $request->user()->id;
        }
        if (array_key_exists('status', $data) && $data['status'] !== 'concluida') {
            $data['completed_at'] = null;
            $data['completed_by'] = null;
        }
        $task->update($data);

        return response()->json($task);
    }

    public function storeChecklistItem(Request $request, ItTicket $ticket): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.editar'), 403);
        $this->authorizeTicketAccess($request, $ticket);
        return response()->json($ticket->checklistItems()->create($request->validate([
            'label' => ['required', 'string', 'max:255'],
            'required' => ['nullable', 'boolean'],
            'status' => ['nullable', Rule::in(['pendente', 'concluido', 'nao_aplicavel'])],
        ])), 201);
    }

    public function updateChecklistItem(Request $request, ItTicket $ticket, ItTicketChecklistItem $item): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.editar') || $item->ticket_id !== $ticket->id, 403);
        $this->authorizeTicketAccess($request, $ticket);
        $data = $request->validate(['status' => ['required', Rule::in(['pendente', 'concluido', 'nao_aplicavel'])]]);
        $item->update([...$data, 'checked_by' => $request->user()->id, 'checked_at' => now()]);

        return response()->json($item);
    }

    public function relate(Request $request, ItTicket $ticket): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.editar'), 403);
        $this->authorizeTicketAccess($request, $ticket);
        $data = $request->validate([
            'related_ticket_id' => ['required', 'integer', 'exists:it_tickets,id', 'different:id'],
            'type' => ['required', Rule::in(['duplicado_de', 'relacionado_a', 'causado_por', 'bloqueado_por', 'problema_pai', 'incidente_derivado'])],
        ]);
        abort_if((int) $data['related_ticket_id'] === $ticket->id, 422, 'Um chamado nao pode ser relacionado a ele mesmo.');
        abort_if(! $this->ticketVisible($request, (int) $data['related_ticket_id']), 403);
        $relationship = ItTicketRelationship::firstOrCreate([...$data, 'ticket_id' => $ticket->id], ['created_by' => $request->user()->id]);

        return response()->json($relationship, 201);
    }

    public function mergeDuplicate(Request $request, ItTicket $ticket): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.editar'), 403);
        $this->authorizeTicketAccess($request, $ticket);
        $data = $request->validate(['duplicate_ticket_id' => ['required', 'integer', 'exists:it_tickets,id']]);
        $duplicate = ItTicket::findOrFail($data['duplicate_ticket_id']);
        abort_if($duplicate->id === $ticket->id, 422, 'Um chamado nao pode ser duplicado dele mesmo.');
        $this->authorizeTicketAccess($request, $duplicate);
        ItTicketRelationship::firstOrCreate([
            'ticket_id' => $duplicate->id,
            'related_ticket_id' => $ticket->id,
            'type' => 'duplicado_de',
        ], ['created_by' => $request->user()->id, 'metadata' => ['merged_at' => now()->toISOString()]]);
        $duplicate->update(['status' => 'encerrado', 'closed_at' => now(), 'closed_by' => $request->user()->id]);

        return response()->json(['message' => 'Chamado duplicado mesclado.']);
    }

    public function reflectIncident(Request $request, ItTicket $ticket): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.editar') || ! $ticket->is_major_incident, 403);
        $this->authorizeTicketAccess($request, $ticket);
        $data = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
            'status' => ['nullable', Rule::in(['em_atendimento', 'resolvido', 'encerrado'])],
        ]);

        $children = $ticket->incidentChildren()->whereNotIn('status', ['encerrado', 'cancelado'])->get();
        DB::transaction(function () use ($children, $data, $request): void {
            foreach ($children as $child) {
                $child->messages()->create([
                    'user_id' => $request->user()->id,
                    'direction' => 'outbound',
                    'visibility' => 'public',
                    'message' => $data['message'],
                ]);
                if (! empty($data['status']) && in_array($data['status'], $child->available_transitions ?? [], true)) {
                    $from = $child->status;
                    $updates = ['status' => $data['status']];
                    if ($data['status'] === 'resolvido') {
                        $updates['resolved_at'] = now();
                        $updates['resolved_by'] = $request->user()->id;
                        $updates['resolution_summary'] = $data['message'];
                    }
                    if ($data['status'] === 'encerrado') {
                        $updates['closed_at'] = now();
                        $updates['closed_by'] = $request->user()->id;
                    }
                    $child->update($updates);
                    $child->statusHistories()->create([
                        'changed_by' => $request->user()->id,
                        'from_status' => $from,
                        'to_status' => $data['status'],
                        'reason' => $data['message'],
                    ]);
                }
            }
        });

        return response()->json(['updated' => $children->count()]);
    }

    private function authorizeTicketAccess(Request $request, ItTicket $ticket): void
    {
        abort_unless($request->user()->can('view', $ticket), 403);
    }

    private function ticketVisible(Request $request, int $ticketId): bool
    {
        $ticket = ItTicket::query()->find($ticketId);
        if (! $ticket) {
            return false;
        }
        return $request->user()->can('view', $ticket);
    }
}
