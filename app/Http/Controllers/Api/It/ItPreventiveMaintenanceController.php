<?php

namespace App\Http\Controllers\Api\It;

use App\Http\Controllers\Controller;
use App\Models\ItPreventiveMaintenance;
use App\Services\It\ItPreventiveMaintenanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ItPreventiveMaintenanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.visualizar'), 403);
        $plans = ItPreventiveMaintenance::query()
            ->with(['asset:id,asset_number,description,department_id', 'department:id,name', 'lastTicket:id,status'])
            ->when(! $this->hasFullAccess($request), fn ($q) => $q->where('department_id', $request->user()->department_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('asset_id'), fn ($q) => $q->where('asset_id', $request->integer('asset_id')))
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->string('search').'%'))
            ->orderBy('next_maintenance_at')
            ->paginate($request->integer('per_page', 15));

        return response()->json($plans);
    }

    public function summary(Request $request): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.visualizar'), 403);
        $query = ItPreventiveMaintenance::query()->where('status', 'ativo');
        if (! $this->hasFullAccess($request)) $query->where('department_id', $request->user()->department_id);
        $today = now()->toDateString();
        return response()->json([
            'total' => (clone $query)->count(),
            'overdue' => (clone $query)->whereDate('next_maintenance_at', '<', $today)->count(),
            'due_soon' => (clone $query)->whereBetween('next_maintenance_at', [$today, now()->addDays(7)->toDateString()])->count(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.editar'), 403);
        $data = $this->validatedData($request);
        $data['department_id'] = $this->hasFullAccess($request) ? ($data['department_id'] ?? null) : $request->user()->department_id;
        $data['created_by'] = $request->user()->id;
        $plan = ItPreventiveMaintenance::create($data);
        return response()->json($plan->load(['asset:id,asset_number,description', 'department:id,name']), 201);
    }

    public function update(Request $request, ItPreventiveMaintenance $preventiveMaintenance): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.editar'), 403);
        $this->authorizeAccess($request, $preventiveMaintenance);
        $data = $this->validatedData($request, true);
        if (! $this->hasFullAccess($request)) $data['department_id'] = $request->user()->department_id;
        $preventiveMaintenance->update($data);
        return response()->json($preventiveMaintenance->fresh()->load(['asset:id,asset_number,description', 'department:id,name']));
    }

    public function complete(Request $request, ItPreventiveMaintenance $preventiveMaintenance, ItPreventiveMaintenanceService $service): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.editar'), 403);
        $this->authorizeAccess($request, $preventiveMaintenance);
        $data = $request->validate(['completed_at' => ['nullable', 'date']]);
        return response()->json($service->complete($preventiveMaintenance, $data['completed_at'] ?? null));
    }

    public function generate(Request $request, ItPreventiveMaintenanceService $service): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.editar'), 403);
        return response()->json(['generated' => $service->generateDueTickets($request->user())]);
    }

    private function validatedData(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        return $request->validate([
            'asset_id' => [$required, 'integer', 'exists:assets,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'name' => [$required, 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'frequency_days' => [$required, 'integer', 'min:1', 'max:3650'],
            'last_maintenance_at' => ['nullable', 'date'],
            'next_maintenance_at' => [$required, 'date'],
            'lead_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'status' => ['sometimes', Rule::in(['ativo', 'pausado'])],
            'auto_create_ticket' => ['sometimes', 'boolean'],
        ]);
    }

    private function hasFullAccess(Request $request): bool
    {
        return $request->user()->hasRole('Administrador Geral') || $request->user()->can('informatica.aprovar');
    }

    private function authorizeAccess(Request $request, ItPreventiveMaintenance $plan): void
    {
        abort_if(! $this->hasFullAccess($request) && $plan->department_id !== $request->user()->department_id, 403);
    }
}
