<?php

namespace App\Http\Controllers\Api\It;

use App\Http\Controllers\Controller;
use App\Models\ItSupportTeam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ItSupportTeamController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.visualizar'), 403);

        return response()->json(['data' => ItSupportTeam::query()->with(['technicians:id,name', 'categories:id,name'])->orderBy('name')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.editar'), 403);
        $data = $this->validatedData($request);
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
        $team = ItSupportTeam::create($data);
        $this->syncRelations($request, $team);

        return response()->json($team->load(['technicians:id,name', 'categories:id,name']), 201);
    }

    public function update(Request $request, ItSupportTeam $supportTeam): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.editar'), 403);
        $supportTeam->update($this->validatedData($request, partial: true));
        $this->syncRelations($request, $supportTeam);

        return response()->json($supportTeam->load(['technicians:id,name', 'categories:id,name']));
    }

    private function validatedData(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$required, 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:1000'],
            'max_active_tickets' => ['nullable', 'integer', 'min:1'],
            'active' => ['nullable', 'boolean'],
        ]);
    }

    private function syncRelations(Request $request, ItSupportTeam $team): void
    {
        $data = $request->validate([
            'technician_ids' => ['nullable', 'array'],
            'technician_ids.*' => ['integer', 'exists:users,id'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:it_ticket_categories,id'],
        ]);

        if (array_key_exists('technician_ids', $data)) {
            $team->technicians()->sync(collect($data['technician_ids'] ?? [])->mapWithKeys(fn (int $id) => [$id => [
                'specialties' => json_encode($request->input("technician_settings.{$id}.specialties", []), JSON_UNESCAPED_UNICODE),
                'available' => $request->boolean("technician_settings.{$id}.available", true),
                'unavailable_until' => $request->input("technician_settings.{$id}.unavailable_until"),
                'max_active_tickets' => $request->input("technician_settings.{$id}.max_active_tickets"),
            ]])->all());
        }
        if (array_key_exists('category_ids', $data)) {
            $team->categories()->sync($data['category_ids'] ?? []);
        }
    }
}
