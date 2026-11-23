<?php

namespace App\Http\Controllers\Api\Transport;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\VehicleMaintenance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VehicleMaintenanceController extends Controller
{
    private const TYPES = ['revisao', 'oleo', 'pneus', 'licenciamento', 'corretiva', 'outros'];

    private const STATUSES = ['agendada', 'em_execucao', 'concluida', 'cancelada'];

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'type' => ['nullable', Rule::in(self::TYPES)],
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'due' => ['nullable', Rule::in(['overdue', 'soon'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.config('pagination.max_per_page', 100)],
        ]);

        $maintenances = VehicleMaintenance::query()
            ->with(['vehicle:id,plate,brand,model,current_km,status', 'creator:id,name', 'completer:id,name'])
            ->when($filters['vehicle_id'] ?? null, fn ($query, $vehicleId) => $query->where('vehicle_id', $vehicleId))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('description', 'like', "%{$search}%")
                        ->orWhere('supplier', 'like', "%{$search}%")
                        ->orWhereHas('vehicle', fn ($query) => $query
                            ->where('plate', 'like', "%{$search}%")
                            ->orWhere('brand', 'like', "%{$search}%")
                            ->orWhere('model', 'like', "%{$search}%"));
                });
            })
            ->when($filters['due'] ?? null, fn ($query, $due) => $this->applyDueFilter($query, $due))
            ->latest('scheduled_at')
            ->latest()
            ->paginate($filters['per_page'] ?? 15);

        return response()->json($maintenances);
    }

    public function store(Request $request): JsonResponse
    {
        abort_if($request->user()->cannot('transporte.cadastrar'), 403);

        $data = $this->validatedData($request);
        $maintenance = VehicleMaintenance::create([
            ...$data,
            'created_by' => $request->user()->id,
        ])->load(['vehicle:id,plate,brand,model,current_km,status', 'creator:id,name']);

        $this->syncVehicleMaintenanceState($maintenance);

        activity('transporte')->causedBy($request->user())->performedOn($maintenance)->log('manutencao de veiculo cadastrada');

        return response()->json($maintenance, 201);
    }

    public function update(Request $request, VehicleMaintenance $maintenance): JsonResponse
    {
        abort_if($request->user()->cannot('transporte.editar'), 403);

        $data = $this->validatedData($request, partial: true);

        if (($data['status'] ?? null) === 'concluida' && $maintenance->status !== 'concluida') {
            $data['completed_by'] = $request->user()->id;
            $data['completed_at'] ??= now()->toDateString();
        }

        $maintenance->update($data);
        $maintenance->load(['vehicle:id,plate,brand,model,current_km,status', 'creator:id,name', 'completer:id,name']);
        $this->syncVehicleMaintenanceState($maintenance);

        activity('transporte')->causedBy($request->user())->performedOn($maintenance)->log('manutencao de veiculo atualizada');

        return response()->json($maintenance);
    }

    public function destroy(Request $request, VehicleMaintenance $maintenance): JsonResponse
    {
        abort_if($request->user()->cannot('transporte.excluir'), 403);

        $maintenance->delete();

        activity('transporte')->causedBy($request->user())->performedOn($maintenance)->log('manutencao de veiculo removida');

        return response()->json(['message' => 'Manutencao removida.']);
    }

    public function summary(Request $request): JsonResponse
    {
        abort_if($request->user()->cannot('transporte.visualizar'), 403);

        $base = VehicleMaintenance::query()->whereNotIn('status', ['concluida', 'cancelada']);

        return response()->json([
            'open' => (clone $base)->count(),
            'overdue' => (clone $base)->where(fn ($query) => $query
                ->whereDate('next_due_at', '<', today())
                ->orWhereHas('vehicle', fn ($query) => $query->whereColumn('vehicles.current_km', '>=', 'vehicle_maintenances.next_due_km')))
                ->count(),
            'soon' => (clone $base)->where(fn ($query) => $query
                ->whereBetween('next_due_at', [today(), today()->addDays(30)])
                ->orWhereHas('vehicle', fn ($query) => $query
                    ->whereNotNull('vehicle_maintenances.next_due_km')
                    ->whereRaw('vehicles.current_km + 500 >= vehicle_maintenances.next_due_km')))
                ->count(),
            'vehicles_in_maintenance' => Vehicle::query()->where('status', 'manutencao')->count(),
            'completed_month' => VehicleMaintenance::query()
                ->where('status', 'concluida')
                ->whereBetween('completed_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->count(),
        ]);
    }

    private function validatedData(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'vehicle_id' => [$required, 'integer', 'exists:vehicles,id'],
            'type' => [$required, Rule::in(self::TYPES)],
            'status' => ['sometimes', Rule::in(self::STATUSES)],
            'scheduled_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
            'odometer' => ['nullable', 'integer', 'min:0'],
            'next_due_km' => ['nullable', 'integer', 'min:0'],
            'next_due_at' => ['nullable', 'date'],
            'cost' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999.99'],
            'supplier' => ['nullable', 'string', 'max:160'],
            'description' => [$required, 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function applyDueFilter($query, string $due): void
    {
        $query->whereNotIn('status', ['concluida', 'cancelada']);

        if ($due === 'overdue') {
            $query->where(fn ($query) => $query
                ->whereDate('next_due_at', '<', today())
                ->orWhereHas('vehicle', fn ($query) => $query->whereColumn('vehicles.current_km', '>=', 'vehicle_maintenances.next_due_km')));

            return;
        }

        $query->where(fn ($query) => $query
            ->whereBetween('next_due_at', [today(), today()->addDays(30)])
            ->orWhereHas('vehicle', fn ($query) => $query
                ->whereNotNull('vehicle_maintenances.next_due_km')
                ->whereRaw('vehicles.current_km + 500 >= vehicle_maintenances.next_due_km')));
    }

    private function syncVehicleMaintenanceState(VehicleMaintenance $maintenance): void
    {
        if ($maintenance->status === 'em_execucao') {
            $maintenance->vehicle()->update(['status' => 'manutencao']);

            return;
        }

        if ($maintenance->status !== 'concluida') {
            return;
        }

        $changes = [
            'status' => 'disponivel',
            'last_maintenance_at' => $maintenance->completed_at ?? today(),
        ];

        if ($maintenance->next_due_at) {
            $changes['next_licensing_at'] = $maintenance->type === 'licenciamento'
                ? $maintenance->next_due_at
                : $maintenance->vehicle?->next_licensing_at;
        }

        if ($maintenance->odometer !== null && $maintenance->odometer > ($maintenance->vehicle?->current_km ?? 0)) {
            $changes['current_km'] = $maintenance->odometer;
        }

        $maintenance->vehicle()->update($changes);
    }
}
