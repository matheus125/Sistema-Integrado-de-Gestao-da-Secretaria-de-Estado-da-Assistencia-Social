<?php

namespace App\Http\Controllers\Api\It;

use App\Http\Controllers\Controller;
use App\Models\ItChecklistTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ItChecklistTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.visualizar'), 403);
        return response()->json(['data' => ItChecklistTemplate::with(['category:id,name', 'items'])->where('active', true)->orderBy('name')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.editar'), 403);
        $data = $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:it_ticket_categories,id'],
            'type' => ['nullable', Rule::in(array_keys(\App\Models\ItTicket::TYPES))],
            'name' => ['required', 'string', 'max:160'],
            'active' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.label' => ['required', 'string', 'max:255'],
            'items.*.required' => ['nullable', 'boolean'],
            'items.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
        $template = ItChecklistTemplate::create(collect($data)->except('items')->all());
        $template->items()->createMany($data['items']);
        return response()->json($template->load(['category:id,name', 'items']), 201);
    }

    public function update(Request $request, ItChecklistTemplate $template): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.editar'), 403);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:160'],
            'active' => ['sometimes', 'boolean'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.label' => ['required_with:items', 'string', 'max:255'],
            'items.*.required' => ['nullable', 'boolean'],
            'items.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
        $template->update(collect($data)->except('items')->all());
        if (array_key_exists('items', $data)) {
            $template->items()->delete();
            $template->items()->createMany($data['items']);
        }
        return response()->json($template->load(['category:id,name', 'items']));
    }
}
