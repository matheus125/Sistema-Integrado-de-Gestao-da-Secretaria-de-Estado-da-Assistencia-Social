<?php

namespace App\Http\Controllers\Api\It;

use App\Http\Controllers\Controller;
use App\Models\WhatsappMessageTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WhatsappTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.visualizar'), 403);
        return response()->json(['data' => WhatsappMessageTemplate::query()->where('active', true)->orderBy('name')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.editar'), 403);
        $data = $request->validate($this->rules());
        return response()->json(WhatsappMessageTemplate::create($data), 201);
    }

    public function update(Request $request, WhatsappMessageTemplate $template): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.editar'), 403);
        $data = $request->validate($this->rules(true, $template));
        $template->update($data);
        return response()->json($template->fresh());
    }

    private function rules(bool $partial = false, ?WhatsappMessageTemplate $template = null): array
    {
        return [
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:120'],
            'key' => [$partial ? 'sometimes' : 'required', 'string', 'max:120', 'alpha_dash', Rule::unique('whatsapp_message_templates', 'key')->ignore($template)],
            'body' => [$partial ? 'sometimes' : 'required', 'string', 'max:4096'],
            'variables' => ['nullable', 'array'],
            'variables.*' => ['string', 'max:60'],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
