<?php

namespace App\Services\It;

use App\Models\ItTicket;
use App\Models\ItTicketMessage;
use App\Support\BrazilianPhone;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WhatsappTicketService
{
    public function __construct(
        private readonly TechnicianAssignment $assignment,
        private readonly SlaService $slaService,
        private readonly ItNotificationService $notifications,
    ) {}

    public function receiveTextMessage(string $phone, string $name, string $message, array $metadata = []): ItTicket
    {
        $requesterPhone = BrazilianPhone::normalize($phone);
        $conversationId = BrazilianPhone::toE164($phone);

        if ($requesterPhone === null || $conversationId === null) {
            throw new InvalidArgumentException('Invalid Brazilian WhatsApp phone number.');
        }

        $messageId = filled($metadata['message_id'] ?? null)
            ? (string) $metadata['message_id']
            : null;

        if ($messageId && $existingTicket = $this->ticketForProviderMessage($messageId)) {
            return $existingTicket;
        }

        try {
            return DB::transaction(function () use ($conversationId, $requesterPhone, $name, $message, $metadata, $messageId) {
                if ($messageId && $existingTicket = $this->ticketForProviderMessage($messageId)) {
                    return $existingTicket;
                }

                $ticket = ItTicket::query()
                    ->where('source', 'whatsapp')
                    ->where('whatsapp_conversation_id', $conversationId)
                    ->whereNotIn('status', ['resolvido', 'encerrado'])
                    ->latest()
                    ->first();

                if (! $ticket) {
                    $assignedTo = $this->assignment->nextTechnicianId();

                    $ticket = ItTicket::create([
                        'assigned_to' => $assignedTo,
                        'requester_name' => $name ?: $requesterPhone,
                        'requester_phone' => $requesterPhone,
                        'department_name' => 'WhatsApp',
                        'description' => $message,
                        'category' => 'WhatsApp',
                        'type' => 'solicitacao',
                        'priority' => 'media',
                        'status' => $assignedTo ? 'triagem' : 'aberto',
                        'source' => 'whatsapp',
                        'whatsapp_conversation_id' => $conversationId,
                    ]);
                    $this->slaService->applyToTicket($ticket)->save();
                    $this->notifications->ticketEvent($ticket, 'ticket_created', "Chamado WhatsApp #{$ticket->id} criado.");
                }

                $ticket->messages()->create([
                    'direction' => 'inbound',
                    'message' => $message,
                    'provider_message_id' => $messageId,
                    'metadata' => $metadata,
                ]);

                activity('informatica')->performedOn($ticket)->withProperties([
                    'phone' => $conversationId,
                    'source' => 'whatsapp',
                ])->log('mensagem recebida pelo WhatsApp');

                return $this->loadTicketRelations($ticket);
            });
        } catch (QueryException $exception) {
            if ($messageId && $existingTicket = $this->ticketForProviderMessage($messageId)) {
                return $existingTicket;
            }

            throw $exception;
        }
    }

    private function ticketForProviderMessage(string $messageId): ?ItTicket
    {
        $ticketId = ItTicketMessage::query()
            ->where('provider_message_id', $messageId)
            ->value('ticket_id');

        if (! $ticketId) {
            $ticketId = ItTicketMessage::query()
                ->whereJsonContains('metadata->message_id', $messageId)
                ->value('ticket_id');
        }

        if (! $ticketId) {
            return null;
        }

        $ticket = ItTicket::withTrashed()->find($ticketId);

        return $ticket ? $this->loadTicketRelations($ticket) : null;
    }

    private function loadTicketRelations(ItTicket $ticket): ItTicket
    {
        return $ticket->load(['department:id,name', 'assignee:id,name', 'messages.user:id,name']);
    }
}
