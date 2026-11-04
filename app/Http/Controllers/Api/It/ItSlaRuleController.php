<?php

namespace App\Http\Controllers\Api\It;

use App\Http\Controllers\Controller;
use App\Models\ItSlaCalendar;
use App\Models\ItSlaRule;
use App\Models\ItTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ItSlaRuleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.visualizar'), 403);

        return response()->json([
            'data' => ItSlaRule::query()
                ->with('calendar:id,name')
                ->orderByDesc('rule_priority')
                ->orderByDesc('id')
                ->paginate($request->integer('per_page', 50)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.editar'), 403);

        $rule = ItSlaRule::create($this->validatedData($request));

        return response()->json($rule->load('calendar:id,name'), 201);
    }

    public function update(Request $request, ItSlaRule $slaRule): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.editar'), 403);

        $data = $this->validatedData($request, partial: true, rule: $slaRule);
        $calendarId = array_key_exists('calendar_id', $data) ? $data['calendar_id'] : $slaRule->calendar_id;
        if (($data['active'] ?? $slaRule->active) && $calendarId && ! ItSlaCalendar::whereKey($calendarId)->where('active', true)->exists()) {
            throw ValidationException::withMessages(['calendar_id' => 'Selecione um calendario ativo antes de ativar a regra.']);
        }
        $slaRule->update($data);

        return response()->json($slaRule->load('calendar:id,name'));
    }

    private function validatedData(Request $request, bool $partial = false, ?ItSlaRule $rule = null): array
    {
        $required = $partial ? 'sometimes' : 'required';

        $data = $request->validate([
            'calendar_id' => ['nullable', 'integer', Rule::exists('it_sla_calendars', 'id')->where('active', true)],
            'name' => [$required, 'string', 'max:160'],
            'priority' => ['nullable', Rule::in(['baixa', 'media', 'alta', 'urgente'])],
            'category_id' => ['nullable', 'integer', 'exists:it_ticket_categories,id'],
            'type' => ['nullable', Rule::in(array_keys(ItTicket::TYPES))],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'first_response_minutes' => [$required, 'integer', 'min:0'],
            'start_minutes' => ['nullable', 'integer', 'min:0'],
            'resolution_minutes' => [$required, 'integer', 'min:1'],
            'rule_priority' => ['nullable', 'integer'],
            'is_fallback' => ['nullable', 'boolean'],
            'active' => ['nullable', 'boolean'],
        ]);

        $effective = array_merge($rule?->only(['priority', 'category_id', 'type', 'department_id', 'is_fallback']) ?? [], $data);
        if (($effective['is_fallback'] ?? false)
            && collect(['priority', 'category_id', 'type', 'department_id'])->contains(fn (string $field) => filled($effective[$field] ?? null))) {
            throw ValidationException::withMessages([
                'is_fallback' => 'Uma regra fallback nao pode ter prioridade, categoria, tipo ou setor definidos.',
            ]);
        }

        return $data;
    }
}
