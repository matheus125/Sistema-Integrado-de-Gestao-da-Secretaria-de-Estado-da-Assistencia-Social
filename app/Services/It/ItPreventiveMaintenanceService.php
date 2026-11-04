<?php

namespace App\Services\It;

use App\Models\ItPreventiveMaintenance;
use App\Models\ItTicket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ItPreventiveMaintenanceService
{
    public function generateDueTickets(?User $actor = null): int
    {
        $today = now()->startOfDay();
        $count = 0;

        ItPreventiveMaintenance::query()
            ->with('asset')
            ->where('status', 'ativo')
            ->where('auto_create_ticket', true)
            ->whereDate('next_maintenance_at', '<=', $today->copy()->addDays(365))
            ->chunkById(100, function ($plans) use (&$count, $actor, $today): void {
                foreach ($plans as $plan) {
                    if ($plan->next_maintenance_at->startOfDay()->gt($today->copy()->addDays($plan->lead_days))) continue;
                    $alreadyOpen = ItTicket::query()
                        ->where('preventive_maintenance_id', $plan->id)
                        ->whereNotIn('status', ['resolvido', 'encerrado', 'cancelado'])
                        ->exists();
                    if ($alreadyOpen) continue;

                    $ticket = DB::transaction(function () use ($plan, $actor, $today) {
                        $asset = $plan->asset;
                        $ticket = ItTicket::create([
                            'department_id' => $plan->department_id ?: $asset?->department_id,
                            'opened_by' => $actor?->id,
                            'primary_asset_id' => $plan->asset_id,
                            'preventive_maintenance_id' => $plan->id,
                            'requester_name' => 'Manutencao preventiva',
                            'description' => $plan->description ?: "Manutencao preventiva: {$plan->name}",
                            'type' => 'manutencao',
                            'priority' => 'media',
                            'status' => 'aberto',
                            'source' => 'preventiva',
                        ]);
                        $plan->update(['last_ticket_id' => $ticket->id, 'last_generated_at' => $today]);
                        return $ticket;
                    });
                    if ($ticket) $count++;
                }
            });

        return $count;
    }

    public function complete(ItPreventiveMaintenance $plan, ?string $date = null): ItPreventiveMaintenance
    {
        $completedAt = $date ? now()->parse($date)->startOfDay() : now()->startOfDay();
        $plan->update([
            'last_maintenance_at' => $completedAt->toDateString(),
            'next_maintenance_at' => $completedAt->copy()->addDays($plan->frequency_days)->toDateString(),
        ]);

        return $plan->fresh(['asset', 'lastTicket']);
    }
}
