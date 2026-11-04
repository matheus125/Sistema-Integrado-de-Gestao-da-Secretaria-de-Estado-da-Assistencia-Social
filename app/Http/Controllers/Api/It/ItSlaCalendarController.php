<?php

namespace App\Http\Controllers\Api\It;

use App\Http\Controllers\Controller;
use App\Models\ItSlaCalendar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ItSlaCalendarController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.visualizar'), 403);

        return response()->json(['data' => ItSlaCalendar::query()->orderBy('name')->get(), 'timezone' => config('app.timezone')]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.editar'), 403);

        return response()->json(ItSlaCalendar::create($this->validatedData($request)), 201);
    }

    public function update(Request $request, ItSlaCalendar $slaCalendar): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.editar'), 403);

        $slaCalendar->update($this->validatedData($request, calendar: $slaCalendar));

        return response()->json($slaCalendar);
    }

    private function validatedData(Request $request, ?ItSlaCalendar $calendar = null): array
    {
        $required = $calendar ? 'sometimes' : 'required';

        $data = $request->validate([
            'name' => [$required, 'string', 'max:160'],
            'weekdays' => ['nullable', 'array'],
            'weekdays.*' => ['integer', 'between:1,7', 'distinct'],
            'starts_at' => [$required, 'date_format:H:i'],
            'ends_at' => [$required, 'date_format:H:i'],
            'break_starts_at' => ['nullable', 'date_format:H:i'],
            'break_ends_at' => ['nullable', 'date_format:H:i'],
            'count_weekends' => ['sometimes', 'boolean'],
            'holidays' => ['nullable', 'array'],
            'holidays.*' => ['date_format:Y-m-d'],
            'exceptions' => ['nullable', 'array', 'max:366'],
            'exceptions.*' => ['array:date,working,starts_at,ends_at,break_starts_at,break_ends_at'],
            'exceptions.*.date' => ['required', 'date_format:Y-m-d', 'distinct'],
            'exceptions.*.working' => ['required', 'boolean'],
            'exceptions.*.starts_at' => ['nullable', 'date_format:H:i'],
            'exceptions.*.ends_at' => ['nullable', 'date_format:H:i'],
            'exceptions.*.break_starts_at' => ['nullable', 'date_format:H:i'],
            'exceptions.*.break_ends_at' => ['nullable', 'date_format:H:i'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $merged = array_replace($calendar?->toArray() ?? ['weekdays' => [1, 2, 3, 4, 5]], $data);
        $errors = [];
        $checkWindow = function (array $window, string $prefix = '') use (&$errors): void {
            $start = substr($window['starts_at'] ?? '', 0, 5);
            $end = substr($window['ends_at'] ?? '', 0, 5);
            $pause = substr($window['break_starts_at'] ?? '', 0, 5);
            $resume = substr($window['break_ends_at'] ?? '', 0, 5);
            if (! $start || ! $end || $end <= $start) {
                $errors[$prefix.'ends_at'] = 'O fim deve ser posterior ao inicio, no mesmo dia.';
            }
            if (($pause || $resume) && (! $pause || ! $resume || $pause <= $start || $resume >= $end || $resume <= $pause)) {
                $errors[$prefix.'break_ends_at'] = 'Informe inicio e fim do intervalo dentro do expediente.';
            }
        };
        $checkWindow($merged);
        if (empty($merged['count_weekends']) && empty($merged['weekdays'])) {
            $errors['weekdays'] = 'Selecione ao menos um dia de atendimento.';
        }
        foreach ($merged['exceptions'] ?? [] as $index => $exception) {
            if (($exception['working'] ?? true) && collect(['starts_at', 'ends_at', 'break_starts_at', 'break_ends_at'])->contains(fn ($key) => filled($exception[$key] ?? null))) {
                $checkWindow($exception, "exceptions.{$index}.");
            }
        }
        if (array_key_exists('active', $data) && ! $data['active'] && $calendar?->rules()->where('active', true)->exists()) {
            $errors['active'] = 'Desative ou altere as regras SLA ativas antes de desativar este calendario.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $data;
    }
}
