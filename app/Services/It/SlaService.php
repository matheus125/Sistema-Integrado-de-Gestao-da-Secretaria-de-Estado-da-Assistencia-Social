<?php

namespace App\Services\It;

use App\Models\ItSlaCalendar;
use App\Models\ItSlaRule;
use App\Models\ItTicket;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class SlaService
{
    public function resolveRule(array|ItTicket $ticket): ItSlaRule
    {
        $data = $ticket instanceof ItTicket ? $ticket->toArray() : $ticket;

        $query = ItSlaRule::query()
            ->with('calendar')
            ->where('active', true)
            // Fallback rules are considered only after scoped/general rules.
            // Keeping them out of the matching pool prevents a misconfigured
            // fallback with a scope from taking precedence unexpectedly.
            ->where('is_fallback', false)
            ->where(fn ($query) => $query->whereNull('calendar_id')->orWhereHas('calendar', fn ($calendar) => $calendar->where('active', true)))
            ->where(function ($query) use ($data) {
                $query->whereNull('priority')->orWhere('priority', $data['priority'] ?? null);
            })
            ->where(function ($query) use ($data) {
                $query->whereNull('category_id')->orWhere('category_id', $data['category_id'] ?? null);
            })
            ->where(function ($query) use ($data) {
                $query->whereNull('type')->orWhere('type', $data['type'] ?? null);
            })
            ->where(function ($query) use ($data) {
                $query->whereNull('department_id')->orWhere('department_id', $data['department_id'] ?? null);
            });

        $rules = $query->get();

        $best = $rules
            ->sortByDesc(fn (ItSlaRule $rule) => [$this->specificity($rule), $rule->rule_priority, $rule->id])
            ->first();

        return $best
            ?? ItSlaRule::query()->with('calendar')->where('active', true)->where(fn ($query) => $query->whereNull('calendar_id')->orWhereHas('calendar', fn ($calendar) => $calendar->where('active', true)))->where('is_fallback', true)->orderByDesc('rule_priority')->first()
            ?? $this->runtimeFallbackRule();
    }

    public function applyToTicket(ItTicket $ticket, ?Carbon $openedAt = null): ItTicket
    {
        $openedAt ??= $ticket->created_at ?? now();
        $rule = $this->resolveRule($ticket);

        $ticket->forceFill([
            'sla_rule_id' => $rule->exists ? $rule->id : null,
            'first_response_due_at' => $this->addBusinessMinutes($openedAt, $rule->first_response_minutes, $rule->calendar),
            'start_due_at' => $rule->start_minutes > 0 ? $this->addBusinessMinutes($openedAt, $rule->start_minutes, $rule->calendar) : null,
            'sla_due_at' => $this->addBusinessMinutes($openedAt, $rule->resolution_minutes, $rule->calendar),
        ]);

        return $ticket;
    }

    public function addBusinessMinutes(Carbon $from, int $minutes, ?ItSlaCalendar $calendar): Carbon
    {
        if (! $calendar) {
            return $from->copy()->addMinutes(max(0, $minutes));
        }
        if (! $calendar->active) {
            throw ValidationException::withMessages(['calendar_id' => 'O calendario de atendimento esta inativo.']);
        }

        $timezone = $from->timezone;
        $cursor = $from->copy()->setTimezone(config('app.timezone', 'UTC'));
        $remaining = max(0, $minutes) * 60;

        // A bounded search rejects invalid legacy calendars instead of hanging requests.
        for ($day = 0; $day < 3660; $day++) {
            foreach ($this->windowsFor($cursor, $calendar) as [$start, $end]) {
                if ($cursor->gte($end)) {
                    continue;
                }
                if ($cursor->lt($start)) {
                    $cursor = $start;
                }
                $available = $cursor->diffInSeconds($end, false);
                if ($remaining <= $available) {
                    return $cursor->copy()->addSeconds($remaining)->setTimezone($timezone);
                }
                $remaining -= $available;
                $cursor = $end;
            }
            $cursor = $cursor->copy()->addDay()->startOfDay();
        }

        throw ValidationException::withMessages([
            'calendar_id' => 'Nao foi possivel calcular o prazo em dez anos. Revise o calendario e o prazo da regra.',
        ]);
    }

    private function specificity(ItSlaRule $rule): int
    {
        return collect([$rule->priority, $rule->category_id, $rule->type, $rule->department_id])
            ->filter(fn ($value) => filled($value))
            ->count();
    }

    private function runtimeFallbackRule(): ItSlaRule
    {
        $rule = new ItSlaRule([
            'name' => 'Fallback legado',
            'first_response_minutes' => 240,
            'start_minutes' => 0,
            'resolution_minutes' => 1440,
            'rule_priority' => -1000,
            'is_fallback' => true,
            'active' => true,
        ]);
        $rule->exists = false;

        return $rule;
    }

    private function windowsFor(Carbon $date, ItSlaCalendar $calendar): array
    {
        $exception = collect($calendar->exceptions ?? [])->firstWhere('date', $date->toDateString());
        // Explicit exceptions take precedence over holidays and regular weekdays.
        if ($exception !== null) {
            if (! ($exception['working'] ?? true)) {
                return [];
            }
        } elseif (in_array($date->toDateString(), $calendar->holidays ?? [], true)
            || (! $calendar->count_weekends && ! in_array($date->dayOfWeekIso, $calendar->weekdays ?? [1, 2, 3, 4, 5], true))) {
            return [];
        }

        $schedule = $exception !== null && ! empty($exception['starts_at']) ? $exception : $calendar->toArray();
        $at = fn ($time) => Carbon::parse($date->toDateString().' '.$time, $date->timezone);
        $start = $at($schedule['starts_at']);
        $end = $at($schedule['ends_at']);
        $breakStart = $schedule['break_starts_at'] ?? null;
        $breakEnd = $schedule['break_ends_at'] ?? null;
        if ($end->lte($start) || (bool) $breakStart !== (bool) $breakEnd) {
            throw ValidationException::withMessages(['calendar_id' => 'Horario de atendimento invalido.']);
        }
        if (! $breakStart) {
            return [[$start, $end]];
        }
        $pause = $at($breakStart);
        $resume = $at($breakEnd);
        if ($pause->lte($start) || $resume->gte($end) || $resume->lte($pause)) {
            throw ValidationException::withMessages(['calendar_id' => 'Intervalo de atendimento invalido.']);
        }

        return [[$start, $pause], [$resume, $end]];
    }
}
