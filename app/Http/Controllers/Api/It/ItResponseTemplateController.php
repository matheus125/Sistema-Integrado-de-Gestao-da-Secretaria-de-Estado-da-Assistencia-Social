<?php

namespace App\Http\Controllers\Api\It;

use App\Http\Controllers\Controller;
use App\Models\ItResponseTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ItResponseTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.visualizar'), 403);
        $user = $request->user();
        $templates = ItResponseTemplate::query()
            ->with('supportTeam:id,name')
            ->where('active', true)
            ->where(function ($query) use ($user) {
                $query->whereNull('user_id')->whereNull('support_team_id')
                    ->orWhere('user_id', $user->id)
                    ->orWhereIn('support_team_id', $user->supportTeams()->select('it_support_teams.id'));
            })
            ->orderBy('name')
            ->get();
        return response()->json(['data' => $templates]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.editar'), 403);
        $data = $request->validate($this->rules());
        $data['created_by'] = $request->user()->id;
        if (($data['scope'] ?? 'global') === 'pessoal') $data['user_id'] = $request->user()->id;
        if (($data['scope'] ?? 'global') !== 'equipe') $data['support_team_id'] = null;
        unset($data['scope']);
        return response()->json(ItResponseTemplate::create($data)->load('supportTeam:id,name'), 201);
    }

    public function update(Request $request, ItResponseTemplate $template): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.editar'), 403);
        abort_if($template->created_by !== $request->user()->id && $template->user_id !== null, 403);
        $data = $request->validate($this->rules(true));
        if (($data['scope'] ?? null) === 'pessoal') $data['user_id'] = $request->user()->id;
        if (array_key_exists('scope', $data) && $data['scope'] !== 'equipe') $data['support_team_id'] = null;
        unset($data['scope']);
        $template->update($data);
        return response()->json($template->fresh()->load('supportTeam:id,name'));
    }

    private function rules(bool $partial = false): array
    {
        return [
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:120'],
            'key' => ['nullable', 'string', 'max:120', 'alpha_dash'],
            'body' => [$partial ? 'sometimes' : 'required', 'string', 'max:4096'],
            'scope' => [$partial ? 'sometimes' : 'nullable', Rule::in(['global', 'pessoal', 'equipe'])],
            'support_team_id' => ['nullable', 'integer', 'exists:it_support_teams,id'],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
