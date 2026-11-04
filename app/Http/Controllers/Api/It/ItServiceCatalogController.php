<?php

namespace App\Http\Controllers\Api\It;

use App\Http\Controllers\Controller;
use App\Models\ItServiceCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ItServiceCatalogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.visualizar'), 403);
        return response()->json(['data' => ItServiceCatalog::query()->with(['category:id,name', 'slaRule:id,name', 'checklistTemplate:id,name'])->orderBy('name')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.editar'), 403);
        $data = $request->validate($this->rules());
        $data['created_by'] = $request->user()->id;
        return response()->json(ItServiceCatalog::create($data)->load(['category:id,name', 'slaRule:id,name', 'checklistTemplate:id,name']), 201);
    }

    public function update(Request $request, ItServiceCatalog $serviceCatalog): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.editar'), 403);
        $serviceCatalog->update($request->validate($this->rules(true, $serviceCatalog)));
        return response()->json($serviceCatalog->fresh()->load(['category:id,name', 'slaRule:id,name', 'checklistTemplate:id,name']));
    }

    private function rules(bool $partial = false, ?ItServiceCatalog $current = null): array
    {
        return [
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:160'],
            'key' => [$partial ? 'sometimes' : 'required', 'string', 'max:120', 'alpha_dash', Rule::unique('it_service_catalog', 'key')->ignore($current)],
            'description' => ['nullable', 'string', 'max:4000'],
            'category_id' => ['nullable', 'integer', 'exists:it_ticket_categories,id'],
            'type' => ['nullable', Rule::in(array_keys(\App\Models\ItTicket::TYPES))],
            'sla_rule_id' => ['nullable', 'integer', 'exists:it_sla_rules,id'],
            'checklist_template_id' => ['nullable', 'integer', 'exists:it_checklist_templates,id'],
            'requires_approval' => ['nullable', 'boolean'],
            'approval_role' => ['nullable', 'string', 'max:120'],
            'form_fields' => ['nullable', 'array'],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
