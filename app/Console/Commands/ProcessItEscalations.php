<?php

namespace App\Console\Commands;

use App\Models\ItTicket;
use App\Services\It\EscalationService;
use Illuminate\Console\Command;

class ProcessItEscalations extends Command
{
    protected $signature = 'it:process-escalations {ticketId? : Processar somente um chamado}';

    protected $description = 'Processa regras de escalonamento de SLA do Help Desk';

    public function handle(EscalationService $escalation): int
    {
        $ticketId = $this->argument('ticketId');
        $ticket = $ticketId ? ItTicket::query()->findOrFail($ticketId) : null;
        $count = $escalation->process($ticket);
        $this->info("{$count} escalonamento(s) registrado(s).");

        return self::SUCCESS;
    }
}
