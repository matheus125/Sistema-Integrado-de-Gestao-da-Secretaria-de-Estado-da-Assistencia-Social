<?php

namespace App\Http\Controllers\Api\It;

use App\Http\Controllers\Controller;
use App\Models\ItAutomationRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ItAutomationRuleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.visualizar'), 403);
        return response()->json(['data' => ItAutomationRule::query()->orderBy('name')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.editar'), 403);
        $data = $request->validate($this->rules());
        $data['created_by'] = $request->user()->id;
        return response()->json(ItAutomationRule::create($data), 201);
    }

    public function update(Request $request, ItAutomationRule $automationRule): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.editar'), 403);
        $automationRule->update($request->validate($this->rules(true)));
        return response()->json($automationRule->fresh());
    }

    private function rules(bool $partial = false): array
    {
        return [
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:120'],
            'event' => [$partial ? 'sometimes' : 'required', Rule::in(['ticket_created', 'status_changed', 'message_created'])],
            'conditions' => ['nullable', 'array'],
            'actions' => [$partial ? 'sometimes' : 'required', 'array', 'min:1'],
            'actions.*.type' => ['required', Rule::in(['update_ticket', 'message'])],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
