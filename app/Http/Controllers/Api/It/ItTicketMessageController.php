<?php

namespace App\Http\Controllers\Api\It;

use App\Http\Controllers\Controller;
use App\Http\Requests\ItTicketMessageRequest;
use App\Models\ItTicket;
use App\Services\It\ItNotificationService;
use App\Services\It\WhatsappOutboundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItTicketMessageController extends Controller
{
    public function store(ItTicketMessageRequest $request, ItTicket $ticket, ItNotificationService $notifications, WhatsappOutboundService $whatsapp): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.visualizar'), 403);
        abort_unless($request->user()->can('view', $ticket), 403);
        abort_if($ticket->status === 'encerrado' && $request->user()->cannot('informatica.editar'), 422, 'Reabra o chamado antes de enviar uma nova mensagem.');

        $data = $request->validated();

        $user = $request->user();
        $isRequester = $ticket->opened_by === $user->id;
        $direction = $data['direction'] ?? ($isRequester ? 'inbound' : 'internal');
        $visibility = $data['visibility'] ?? ($direction === 'internal' ? 'internal' : 'public');

        // Inbound messages represent a requester reply and cannot be forged by
        // a technician through the public API. WhatsApp inbound messages use
        // WhatsappTicketService, which is the trusted integration boundary.
        if ($direction === 'inbound') {
            abort_unless($isRequester, 403);
            abort_if($visibility !== 'public', 422, 'Mensagens do solicitante devem ser publicas.');
        } else {
            abort_if(! $user->can('informatica.editar'), 403);
            abort_if($visibility === 'internal' && $direction !== 'internal', 422, 'Mensagens internas devem usar a direcao interna.');
        }

        $message = $ticket->messages()->create([
            'user_id' => $user->id,
            'direction' => $direction,
            'visibility' => $visibility,
            'message' => $data['message'],
        ])->load('user:id,name');

        if ($ticket->first_response_at === null && in_array($message->direction, ['internal', 'outbound'], true)) {
            $ticket->update(['first_response_at' => now()]);
        }
        if ($message->direction === 'inbound') {
            $ticket->update(['last_requester_reply_at' => now()]);
        }

        $notifications->ticketEvent(
            $ticket,
            $message->direction === 'inbound' ? 'requester_replied' : 'technician_replied',
            "Nova mensagem no chamado #{$ticket->id}.",
            $message->direction === 'inbound' ? $ticket->assignee : $ticket->openedBy,
        );

        if ($message->direction === 'outbound' && $ticket->source === 'whatsapp' && filled($ticket->whatsapp_conversation_id)) {
            $whatsapp->send($ticket, $ticket->whatsapp_conversation_id, $message->message, 'technician_replied');
        }

        activity('informatica')->causedBy($request->user())->performedOn($ticket)->log('mensagem registrada no chamado');

        return response()->json($message, 201);
    }
}
