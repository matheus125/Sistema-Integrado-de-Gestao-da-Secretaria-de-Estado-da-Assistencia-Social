<?php

namespace App\Services\It;

use App\Models\ItTicket;
use Illuminate\Support\Facades\DB;

class WhatsappTicketService
{
    public function __construct(
        private readonly TechnicianAssignment $assignment,
        private readonly SlaService $slaService,
        private readonly ItNotificationService $notifications,
    )
    {
    }

    public function receiveTextMessage(string $phone, string $name, string $message, array $metadata = []): ItTicket
    {
        return DB::transaction(function () use ($phone, $name, $message, $metadata) {
            $messageId = $metadata['message_id'] ?? null;
            $ticket = ItTicket::query()
                ->where('source', 'whatsapp')
                ->where('whatsapp_conversation_id', $phone)
                ->whereNotIn('status', ['resolvido', 'encerrado'])
                ->latest()
                ->first();

            if (! $ticket) {
                $assignedTo = $this->assignment->nextTechnicianId();

                $ticket = ItTicket::create([
                    'assigned_to' => $assignedTo,
                    'requester_name' => $name ?: $phone,
                    'requester_phone' => $phone,
                    'department_name' => 'WhatsApp',
                    'description' => $message,
                    'category' => 'WhatsApp',
                    'type' => 'solicitacao',
                    'priority' => 'media',
                    'status' => $assignedTo ? 'triagem' : 'aberto',
                    'source' => 'whatsapp',
                    'whatsapp_conversation_id' => $phone,
                ]);
                $this->slaService->applyToTicket($ticket)->save();
                $this->notifications->ticketEvent($ticket, 'ticket_created', "Chamado WhatsApp #{$ticket->id} criado.");
            }

            if ($messageId && $ticket->messages()->whereJsonContains('metadata->message_id', $messageId)->exists()) {
                return $ticket->load(['department:id,name', 'assignee:id,name', 'messages.user:id,name']);
            }

            $ticket->messages()->create([
                'direction' => 'inbound',
                'message' => $message,
                'metadata' => $metadata,
            ]);

            activity('informatica')->performedOn($ticket)->withProperties([
                'phone' => $phone,
                'source' => 'whatsapp',
            ])->log('mensagem recebida pelo WhatsApp');

            return $ticket->load(['department:id,name', 'assignee:id,name', 'messages.user:id,name']);
        });
    }
}
