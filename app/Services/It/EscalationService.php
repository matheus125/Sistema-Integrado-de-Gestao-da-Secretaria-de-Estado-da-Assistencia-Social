<?php

namespace App\Services\It;

use App\Models\ItEscalationRule;
use App\Models\ItTicket;
use App\Models\ItTicketEscalation;
use App\Models\User;

class EscalationService
{
    public function __construct(private readonly ItNotificationService $notifications)
    {
    }

    public function process(?ItTicket $ticket = null): int
    {
        $query = ItTicket::query()
            ->whereNotIn('status', ['resolvido', 'encerrado'])
            ->whereNotNull('sla_due_at');

        if ($ticket) {
            $query->whereKey($ticket->id);
        }

        $count = 0;
        $rules = ItEscalationRule::query()->where('active', true)->orderBy('remaining_percent')->get();

        $query->with(['assignee:id,name,email', 'openedBy:id,name,email'])->chunkById(100, function ($tickets) use ($rules, &$count) {
            foreach ($tickets as $ticket) {
                foreach ($rules as $rule) {
                    if (! $this->matches($ticket, $rule)) {
                        continue;
                    }

                    $created = ItTicketEscalation::query()->firstOrCreate(
                        ['ticket_id' => $ticket->id, 'rule_id' => $rule->id],
                        ['level' => $rule->level, 'status' => 'registered', 'escalated_at' => now(), 'metadata' => ['rule' => $rule->name]]
                    );

                    if ($created->wasRecentlyCreated) {
                        $count++;

                        if ($rule->notify_technician && $ticket->assignee) {
                            $this->notifications->ticketEvent($ticket, 'sla_escalated', "Chamado #{$ticket->id} escalado para {$rule->level}.", $ticket->assignee, ['rule_id' => $rule->id, 'level' => $rule->level]);
                        }

                        if ($rule->notify_manager) {
                            User::query()
                                ->whereHas('roles', fn ($query) => $query->where('name', 'Gerente Informatica'))
                                ->get()
                                ->each(fn (User $manager) => $this->notifications->ticketEvent($ticket, 'sla_escalated', "Chamado #{$ticket->id} escalado para {$rule->level}.", $manager, ['rule_id' => $rule->id, 'level' => $rule->level]));
                        }
                    }

                    if ($rule->mark_overdue && ! $ticket->sla_overdue) {
                        $ticket->update(['sla_overdue' => true]);
                    }
                }
            }
        });

        return $count;
    }

    private function matches(ItTicket $ticket, ItEscalationRule $rule): bool
    {
        if ($rule->minutes_after_due !== null) {
            return $ticket->sla_due_at->copy()->addMinutes($rule->minutes_after_due)->lte(now());
        }

        if ($rule->remaining_percent === null || ! $ticket->created_at) {
            return false;
        }

        $total = max(1, $ticket->created_at->diffInMinutes($ticket->sla_due_at, false));
        $remaining = now()->diffInMinutes($ticket->sla_due_at, false);

        return $remaining >= 0 && ($remaining / $total) * 100 <= $rule->remaining_percent;
    }
}
