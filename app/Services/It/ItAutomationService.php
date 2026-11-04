<?php

namespace App\Services\It;

use App\Models\ItAutomationRule;
use App\Models\ItTicket;
use Illuminate\Support\Facades\Log;

class ItAutomationService
{
    public function run(ItTicket $ticket, string $event): int
    {
        $count = 0;
        foreach (ItAutomationRule::query()->where('event', $event)->where('active', true)->get() as $rule) {
            if (! $this->matches($ticket, $rule->conditions ?? [])) continue;
            foreach ($rule->actions ?? [] as $action) {
                $this->apply($ticket, $action);
            }
            activity('informatica')->performedOn($ticket)->withProperties(['rule_id' => $rule->id, 'event' => $event])->log('automação executada');
            $count++;
        }
        return $count;
    }

    private function matches(ItTicket $ticket, array $conditions): bool
    {
        foreach ($conditions as $field => $expected) {
            if ((string) data_get($ticket, $field) !== (string) $expected) return false;
        }
        return true;
    }

    private function apply(ItTicket $ticket, array $action): void
    {
        $type = $action['type'] ?? null;
        if ($type === 'update_ticket') {
            $allowed = ['priority', 'assigned_to', 'support_team_id', 'category_id'];
            $updates = array_intersect_key($action['values'] ?? [], array_flip($allowed));
            if ($updates) $ticket->update($updates);
        } elseif ($type === 'message' && filled($action['message'] ?? null)) {
            $ticket->messages()->create(['direction' => 'internal', 'visibility' => 'internal', 'message' => $action['message']]);
        } else {
            Log::warning('Ação de automação desconhecida.', ['action' => $action]);
        }
    }
}
