<?php

namespace App\Console\Commands;

use App\Services\It\ItPreventiveMaintenanceService;
use Illuminate\Console\Command;

class ProcessItPreventiveMaintenance extends Command
{
    protected $signature = 'it:process-preventive-maintenance';
    protected $description = 'Gera chamados para manutenções preventivas próximas do vencimento';

    public function handle(ItPreventiveMaintenanceService $service): int
    {
        $count = $service->generateDueTickets();
        $this->info("{$count} chamado(s) preventivo(s) gerado(s).");
        return self::SUCCESS;
    }
}
