<?php

namespace App\Http\Controllers\Api\Transport;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Driver;
use App\Models\Vehicle;
use App\Models\VehicleRequest;
use App\Models\VehicleRequestChecklist;
use App\Models\VehicleRequestStatusHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VehicleRequestController extends Controller
{
    private const STATUS_FLOW = [
        'solicitacao' => ['analise', 'cancelada', 'rejeitada'],
        'analise' => ['aprovada', 'cancelada', 'rejeitada'],
        'aprovada' => ['em_deslocamento', 'cancelada'],
        'em_deslocamento' => ['concluida'],
        'concluida' => [],
        'cancelada' => [],
        'rejeitada' => [],
    ];

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(array_keys(self::STATUS_FLOW))],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'driver_id' => ['nullable', 'integer', 'exists:drivers,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $requests = VehicleRequest::query()
            ->with(['department:id,name', 'vehicle:id,plate,brand,model', 'driver:id,name', 'requester:id,name'])
            ->when(! $this->hasFullAccess($request), fn ($query) => $query->where('department_id', $request->user()->department_id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when(($filters['department_id'] ?? null) && $this->hasFullAccess($request), fn ($query) => $query->where('department_id', $filters['department_id']))
            ->when($filters['vehicle_id'] ?? null, fn ($query, $vehicleId) => $query->where('vehicle_id', $vehicleId))
            ->when($filters['driver_id'] ?? null, fn ($query, $driverId) => $query->where('driver_id', $driverId))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('date', '<=', $date))
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('origin', 'like', "%{$search}%")
                        ->orWhere('destination', 'like', "%{$search}%")
                        ->orWhere('responsible', 'like', "%{$search}%")
                        ->orWhere('route_description', 'like', "%{$search}%")
                        ->orWhereHas('vehicle', fn ($query) => $query->where('plate', 'like', "%{$search}%"))
                        ->orWhereHas('driver', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate($filters['per_page'] ?? 15);

        $requests->getCollection()->transform(fn (VehicleRequest $vehicleRequest) => $this->withAvailableTransitions($request, $vehicleRequest));

        return response()->json($requests);
    }

    public function show(Request $request, VehicleRequest $vehicleRequest): JsonResponse
    {
        $this->authorizeDepartmentAccess($request, $vehicleRequest);

        return response()->json($this->withAvailableTransitions($request, $vehicleRequest->load($this->detailRelations())));
    }

    public function store(Request $request): JsonResponse
    {
        abort_if($request->user()->cannot('transporte.cadastrar'), 403);

        $data = $this->validatedData($request);
        $data = $this->enforceDepartmentScope($request, $data);

        $vehicleRequest = DB::transaction(function () use ($request, $data): VehicleRequest {
            $this->lockSchedulingResources($data);
            $this->ensureResourcesAreAvailable($data);

            return VehicleRequest::create([
                ...$data,
                'requested_by' => $request->user()->id,
                'status' => 'solicitacao',
            ]);
        }, 3)->load(['department:id,name', 'vehicle:id,plate,brand,model', 'driver:id,name', 'requester:id,name']);

        activity('transporte')->causedBy($request->user())->performedOn($vehicleRequest)->log('solicitacao de veiculo criada');
        $this->recordStatusHistory($request, $vehicleRequest, null, 'solicitacao');

        return response()->json($this->withAvailableTransitions($request, $vehicleRequest), 201);
    }

    public function update(Request $request, VehicleRequest $vehicleRequest): JsonResponse
    {
        abort_if($request->user()->cannot('transporte.editar'), 403);
        $this->authorizeDepartmentAccess($request, $vehicleRequest);
        abort_if(in_array($vehicleRequest->status, ['aprovada', 'em_deslocamento'], true) && ! $request->user()->can('transporte.aprovar'), 403);

        $data = $this->validatedData($request, partial: true);
        $data = $this->enforceDepartmentScope($request, $data);

        DB::transaction(function () use ($vehicleRequest, $data): void {
            VehicleRequest::query()->whereKey($vehicleRequest->id)->lockForUpdate()->firstOrFail();
            $vehicleRequest->refresh();
            $schedule = [
                ...$vehicleRequest->only(['vehicle_id', 'driver_id', 'date', 'departure_time', 'expected_arrival_time', 'passengers_count', 'initial_km', 'final_km']),
                ...$data,
            ];

            $this->lockSchedulingResources($schedule, $vehicleRequest);
            $this->ensureResourcesAreAvailable($schedule, $vehicleRequest);
            $vehicleRequest->update($data);
        }, 3);

        activity('transporte')->causedBy($request->user())->performedOn($vehicleRequest)->log('solicitacao de veiculo atualizada');

        return response()->json($this->withAvailableTransitions($request, $vehicleRequest->load(['department:id,name', 'vehicle:id,plate,brand,model', 'driver:id,name', 'requester:id,name'])));
    }

    public function approve(Request $request, VehicleRequest $vehicleRequest): JsonResponse
    {
        $this->authorizeDepartmentAccess($request, $vehicleRequest);

        $this->transitionRequest($request, $vehicleRequest, 'aprovada');

        return response()->json($this->withAvailableTransitions($request, $vehicleRequest->load(['department:id,name', 'vehicle:id,plate,brand,model', 'driver:id,name'])));
    }

    public function cancel(Request $request, VehicleRequest $vehicleRequest): JsonResponse
    {
        $this->authorizeDepartmentAccess($request, $vehicleRequest);

        $data = $this->statusChangeData($request, reasonRequired: true);

        $this->transitionRequest($request, $vehicleRequest, 'cancelada', $data['reason']);

        return response()->json($this->withAvailableTransitions($request, $vehicleRequest->load(['department:id,name', 'vehicle:id,plate,brand,model', 'driver:id,name'])));
    }

    public function changeStatus(Request $request, VehicleRequest $vehicleRequest): JsonResponse
    {
        $this->authorizeDepartmentAccess($request, $vehicleRequest);

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(self::STATUS_FLOW))],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        if (in_array($data['status'], ['cancelada', 'rejeitada'], true) && blank($data['reason'] ?? null)) {
            throw ValidationException::withMessages([
                'reason' => ['Informe o motivo desta alteracao de status.'],
            ]);
        }

        $this->transitionRequest($request, $vehicleRequest, $data['status'], $data['reason'] ?? null);

        return response()->json($this->withAvailableTransitions($request, $vehicleRequest->load(['department:id,name', 'vehicle:id,plate,brand,model', 'driver:id,name'])));
    }

    public function departureChecklist(Request $request, VehicleRequest $vehicleRequest): JsonResponse
    {
        $this->authorizeDepartmentAccess($request, $vehicleRequest);
        abort_if($request->user()->cannot('transporte.aprovar'), 403);

        $data = $this->validatedChecklistData($request, 'saida');

        DB::transaction(function () use ($request, $vehicleRequest, $data) {
            VehicleRequest::query()->whereKey($vehicleRequest->id)->lockForUpdate()->firstOrFail();
            $vehicleRequest->refresh();

            if ($vehicleRequest->status !== 'aprovada') {
                throw ValidationException::withMessages([
                    'status' => ['O checklist de saida so pode ser registrado em solicitacoes aprovadas.'],
                ]);
            }

            $this->ensureDepartureChecklistAllowsTrip($data);
            $this->ensureDepartureKmDoesNotRollBack($vehicleRequest, $data);
            $this->ensureResourcesAreAvailable($vehicleRequest->toArray(), $vehicleRequest);

            $this->saveChecklist($request, $vehicleRequest, 'saida', $data);
            $vehicleRequest->update(['initial_km' => $data['km']]);
            $this->transitionRequest($request, $vehicleRequest, 'em_deslocamento', $data['observations'] ?? null);
        });

        return response()->json($this->withAvailableTransitions($request, $vehicleRequest->fresh()->load($this->detailRelations())));
    }

    public function returnChecklist(Request $request, VehicleRequest $vehicleRequest): JsonResponse
    {
        $this->authorizeDepartmentAccess($request, $vehicleRequest);
        abort_if($request->user()->cannot('transporte.aprovar'), 403);

        $data = $this->validatedChecklistData($request, 'retorno');

        DB::transaction(function () use ($request, $vehicleRequest, $data) {
            VehicleRequest::query()->whereKey($vehicleRequest->id)->lockForUpdate()->firstOrFail();
            $vehicleRequest->refresh();

            if ($vehicleRequest->status !== 'em_deslocamento') {
                throw ValidationException::withMessages([
                    'status' => ['O checklist de retorno so pode ser registrado em solicitacoes em deslocamento.'],
                ]);
            }

            if ($vehicleRequest->initial_km !== null && (int) $data['km'] < (int) $vehicleRequest->initial_km) {
                throw ValidationException::withMessages([
                    'km' => ['O KM de retorno deve ser maior ou igual ao KM de saida.'],
                ]);
            }

            $this->saveChecklist($request, $vehicleRequest, 'retorno', $data);
            $vehicleRequest->update([
                'final_km' => $data['km'],
                'notes' => $data['observations'] ?? $vehicleRequest->notes,
            ]);
            $this->transitionRequest($request, $vehicleRequest, 'concluida', $data['observations'] ?? null);
        });

        return response()->json($this->withAvailableTransitions($request, $vehicleRequest->fresh()->load($this->detailRelations())));
    }

    public function options(Request $request): JsonResponse
    {
        return response()->json([
            'departments' => Department::query()
                ->where('active', true)
                ->when(! $this->hasFullAccess($request), fn ($query) => $query->whereKey($request->user()->department_id))
                ->orderBy('name')
                ->get(['id', 'name']),
            'vehicles' => Vehicle::query()->where('status', 'disponivel')->orderBy('plate')->get(['id', 'plate', 'brand', 'model', 'capacity']),
            'drivers' => Driver::query()->where('status', 'ativo')->whereDate('cnh_expires_at', '>=', now())->orderBy('name')->get(['id', 'name', 'cnh_category']),
        ]);
    }

    public function availability(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['nullable', 'date'],
        ]);
        $date = $data['date'] ?? now()->toDateString();

        $requests = VehicleRequest::query()
            ->with(['department:id,name', 'driver:id,name', 'vehicle:id,plate'])
            ->whereDate('date', $date)
            ->whereNotIn('status', ['cancelada', 'concluida', 'rejeitada'])
            ->get();

        $vehicles = Vehicle::query()
            ->orderBy('plate')
            ->get(['id', 'plate', 'brand', 'model', 'capacity', 'status', 'current_km', 'last_maintenance_at'])
            ->map(function (Vehicle $vehicle) use ($requests) {
                $schedule = $requests
                    ->where('vehicle_id', $vehicle->id)
                    ->sortBy('departure_time')
                    ->values()
                    ->map(fn (VehicleRequest $item) => [
                        'id' => $item->id,
                        'department' => $item->department?->name,
                        'driver' => $item->driver?->name,
                        'origin' => $item->origin,
                        'destination' => $item->destination,
                        'departure_time' => $this->timeOnly($item->departure_time),
                        'expected_arrival_time' => $this->timeOnly($item->expected_arrival_time),
                        'status' => $item->status,
                    ])
                    ->all();

                return [
                    'id' => $vehicle->id,
                    'plate' => $vehicle->plate,
                    'brand' => $vehicle->brand,
                    'model' => $vehicle->model,
                    'capacity' => $vehicle->capacity,
                    'status' => $vehicle->status,
                    'current_km' => $vehicle->current_km,
                    'last_maintenance_at' => $vehicle->last_maintenance_at,
                    'available' => $vehicle->status === 'disponivel' && count($schedule) === 0,
                    'schedule' => $schedule,
                ];
            });

        $drivers = Driver::query()
            ->orderBy('name')
            ->get(['id', 'name', 'cnh_category', 'cnh_expires_at', 'phone', 'status'])
            ->map(function (Driver $driver) use ($requests) {
                $schedule = $requests
                    ->where('driver_id', $driver->id)
                    ->sortBy('departure_time')
                    ->values()
                    ->map(fn (VehicleRequest $item) => [
                        'id' => $item->id,
                        'department' => $item->department?->name,
                        'vehicle' => $item->vehicle?->plate,
                        'origin' => $item->origin,
                        'destination' => $item->destination,
                        'departure_time' => $this->timeOnly($item->departure_time),
                        'expected_arrival_time' => $this->timeOnly($item->expected_arrival_time),
                        'status' => $item->status,
                    ])
                    ->all();

                return [
                    'id' => $driver->id,
                    'name' => $driver->name,
                    'cnh_category' => $driver->cnh_category,
                    'cnh_expires_at' => $driver->cnh_expires_at,
                    'phone' => $driver->phone,
                    'status' => $driver->status,
                    'available' => $driver->status === 'ativo' && ! Carbon::parse($driver->cnh_expires_at)->isPast() && count($schedule) === 0,
                    'schedule' => $schedule,
                ];
            });

        return response()->json([
            'date' => $date,
            'vehicles' => $vehicles,
            'drivers' => $drivers,
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $this->authorizeTransportView($request);

        $scope = VehicleRequest::query()
            ->when(! $this->hasFullAccess($request), fn ($query) => $query->where('department_id', $request->user()->department_id));

        $monthScope = (clone $scope)->whereBetween('date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()]);
        $completedKm = (clone $monthScope)
            ->where('status', 'concluida')
            ->whereNotNull('initial_km')
            ->whereNotNull('final_km')
            ->get(['initial_km', 'final_km'])
            ->sum(fn (VehicleRequest $request) => max(0, (int) $request->final_km - (int) $request->initial_km));

        return response()->json([
            'open' => (clone $scope)->whereNotIn('status', ['concluida', 'cancelada', 'rejeitada'])->count(),
            'in_progress' => (clone $scope)->where('status', 'em_deslocamento')->count(),
            'scheduled_today' => (clone $scope)->whereDate('date', today())->whereNotIn('status', ['concluida', 'cancelada', 'rejeitada'])->count(),
            'completed_month' => (clone $monthScope)->where('status', 'concluida')->count(),
            'cancelled_month' => (clone $monthScope)->where('status', 'cancelada')->count(),
            'km_month' => $completedKm,
            'idle_vehicles' => Vehicle::query()
                ->where('status', 'disponivel')
                ->whereDoesntHave('maintenances', fn ($query) => $query->whereIn('status', ['agendada', 'em_execucao']))
                ->whereDoesntHave('maintenances', fn ($query) => $query->where('status', 'em_execucao'))
                ->count(),
            'top_driver' => (clone $monthScope)
                ->select('driver_id', DB::raw('count(*) as total'))
                ->whereNotNull('driver_id')
                ->groupBy('driver_id')
                ->orderByDesc('total')
                ->with('driver:id,name')
                ->first(),
        ]);
    }

    public function agenda(Request $request): JsonResponse
    {
        $this->authorizeTransportView($request);

        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'driver_id' => ['nullable', 'integer', 'exists:drivers,id'],
        ]);
        $dateFrom = $filters['date_from'] ?? today()->toDateString();
        $dateTo = $filters['date_to'] ?? today()->addDays(6)->toDateString();

        $items = VehicleRequest::query()
            ->with(['department:id,name', 'vehicle:id,plate,brand,model', 'driver:id,name'])
            ->when(! $this->hasFullAccess($request), fn ($query) => $query->where('department_id', $request->user()->department_id))
            ->when($filters['vehicle_id'] ?? null, fn ($query, $vehicleId) => $query->where('vehicle_id', $vehicleId))
            ->when($filters['driver_id'] ?? null, fn ($query, $driverId) => $query->where('driver_id', $driverId))
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->whereNotIn('status', ['cancelada', 'rejeitada'])
            ->orderBy('date')
            ->orderBy('departure_time')
            ->get()
            ->map(fn (VehicleRequest $item) => [
                'id' => $item->id,
                'date' => $item->date?->format('Y-m-d'),
                'department' => $item->department?->name,
                'vehicle' => $item->vehicle ? "{$item->vehicle->plate} - {$item->vehicle->brand} {$item->vehicle->model}" : 'Veiculo nao definido',
                'driver' => $item->driver?->name ?? 'Motorista nao definido',
                'origin' => $item->origin,
                'destination' => $item->destination,
                'departure_time' => $this->timeOnly($item->departure_time),
                'expected_arrival_time' => $this->timeOnly($item->expected_arrival_time),
                'status' => $item->status,
            ]);

        return response()->json([
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'items' => $items,
        ]);
    }

    private function transitionRequest(Request $request, VehicleRequest $vehicleRequest, string $nextStatus, ?string $reason = null): void
    {
        DB::transaction(function () use ($request, $vehicleRequest, $nextStatus, $reason) {
            VehicleRequest::query()->whereKey($vehicleRequest->id)->lockForUpdate()->firstOrFail();
            $vehicleRequest->refresh();
            $currentStatus = $vehicleRequest->status;
            $allowedStatuses = $this->availableTransitions($request, $vehicleRequest);

            if (! in_array($nextStatus, $allowedStatuses, true)) {
                throw ValidationException::withMessages([
                    'status' => ["Nao e possivel alterar de {$currentStatus} para {$nextStatus}."],
                ]);
            }

            if (in_array($nextStatus, ['aprovada', 'em_deslocamento'], true)) {
                $this->lockSchedulingResources($vehicleRequest->toArray(), $vehicleRequest);
                $this->ensureResourcesAreAvailable($vehicleRequest->toArray(), $vehicleRequest);
            }

            if ($nextStatus === 'em_deslocamento') {
                $this->ensureChecklistExists($vehicleRequest, 'saida');
            }

            if ($nextStatus === 'concluida') {
                $this->ensureChecklistExists($vehicleRequest, 'retorno');
            }

            $changes = ['status' => $nextStatus];

            if ($nextStatus === 'aprovada') {
                $changes += [
                    'approved_by' => $request->user()->id,
                    'approved_at' => now(),
                ];
            }

            if ($nextStatus === 'rejeitada') {
                $changes += [
                    'rejected_by' => $request->user()->id,
                    'rejected_at' => now(),
                    'rejection_reason' => $reason,
                ];
            }

            if ($nextStatus === 'cancelada') {
                $changes += [
                    'cancelled_by' => $request->user()->id,
                    'cancelled_at' => now(),
                    'cancellation_reason' => $reason,
                ];
            }

            if ($nextStatus === 'em_deslocamento') {
                $changes['started_at'] = now();
            }

            if ($nextStatus === 'concluida') {
                $changes['completed_at'] = now();
            }

            $vehicleRequest->update($changes);
            $this->syncVehicleStatus($vehicleRequest, $nextStatus);
            $this->recordStatusHistory($request, $vehicleRequest, $currentStatus, $nextStatus, $reason);

            activity('transporte')->causedBy($request->user())->performedOn($vehicleRequest)->log("solicitacao de veiculo alterada para {$nextStatus}");
        });
    }

    private function withAvailableTransitions(Request $request, VehicleRequest $vehicleRequest): VehicleRequest
    {
        $vehicleRequest->setAttribute('available_transitions', $this->availableTransitions($request, $vehicleRequest));

        return $vehicleRequest;
    }

    /**
     * @return array<int, string>
     */
    private function detailRelations(): array
    {
        return [
            'department:id,name',
            'vehicle:id,plate,brand,model,capacity,status,current_km,last_maintenance_at,next_licensing_at',
            'driver:id,name,cnh_category,cnh_expires_at,phone,status',
            'requester:id,name,email',
            'approver:id,name',
            'rejecter:id,name',
            'canceller:id,name',
            'checklists.checkedBy:id,name',
            'statusHistories.changedBy:id,name',
        ];
    }

    private function statusChangeData(Request $request, bool $reasonRequired = false): array
    {
        return $request->validate([
            'reason' => [$reasonRequired ? 'required' : 'nullable', 'string', 'max:500'],
        ]);
    }

    private function validatedChecklistData(Request $request, string $type): array
    {
        $data = $request->validate([
            'fuel_level' => ['nullable', 'integer', 'min:0', 'max:100'],
            'km' => ['required', 'integer', 'min:0'],
            'documents_ok' => ['required', 'boolean'],
            'tires_ok' => ['required', 'boolean'],
            'lights_ok' => ['required', 'boolean'],
            'bodywork_ok' => ['required', 'boolean'],
            'tools_ok' => ['required', 'boolean'],
            'has_damage' => ['required', 'boolean'],
            'damages_description' => ['nullable', 'string', 'max:1000'],
            'observations' => ['nullable', 'string', 'max:1000'],
        ]);

        if (($data['has_damage'] || ! $data['bodywork_ok']) && blank($data['damages_description'] ?? null)) {
            throw ValidationException::withMessages([
                'damages_description' => ['Descreva as avarias identificadas no checklist.'],
            ]);
        }

        $data['type'] = $type;

        return $data;
    }

    private function saveChecklist(Request $request, VehicleRequest $vehicleRequest, string $type, array $data): VehicleRequestChecklist
    {
        return VehicleRequestChecklist::query()->updateOrCreate(
            [
                'vehicle_request_id' => $vehicleRequest->id,
                'type' => $type,
            ],
            [
                ...$data,
                'checked_by' => $request->user()->id,
                'checked_at' => now(),
            ],
        );
    }

    private function ensureDepartureChecklistAllowsTrip(array $data): void
    {
        $blocked = collect([
            'documents_ok' => 'Documentos do veiculo devem estar conferidos.',
            'tires_ok' => 'Pneus devem estar em condicao segura para saida.',
            'lights_ok' => 'Luzes devem estar em condicao segura para saida.',
        ])->filter(fn (string $message, string $field) => ! (bool) ($data[$field] ?? false));

        if ($blocked->isNotEmpty()) {
            throw ValidationException::withMessages($blocked->map(fn (string $message) => [$message])->all());
        }
    }

    private function ensureDepartureKmDoesNotRollBack(VehicleRequest $vehicleRequest, array $data): void
    {
        $currentKm = $vehicleRequest->vehicle?->current_km;

        if ($currentKm !== null && (int) $data['km'] < (int) $currentKm) {
            throw ValidationException::withMessages([
                'km' => ["O KM de saida deve ser maior ou igual ao KM atual do veiculo ({$currentKm})."],
            ]);
        }
    }

    private function ensureChecklistExists(VehicleRequest $vehicleRequest, string $type): void
    {
        $exists = $vehicleRequest->checklists()->where('type', $type)->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'checklist' => ["Registre o checklist de {$type} antes de alterar este status."],
            ]);
        }
    }

    private function recordStatusHistory(Request $request, VehicleRequest $vehicleRequest, ?string $fromStatus, string $toStatus, ?string $reason = null): void
    {
        VehicleRequestStatusHistory::create([
            'vehicle_request_id' => $vehicleRequest->id,
            'changed_by' => $request->user()?->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'reason' => $reason,
            'metadata' => [
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ],
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function availableTransitions(Request $request, VehicleRequest $vehicleRequest): array
    {
        return collect(self::STATUS_FLOW[$vehicleRequest->status] ?? [])
            ->filter(fn (string $status) => match ($status) {
                'cancelada' => $request->user()->can('transporte.cancelar'),
                default => $request->user()->can('transporte.aprovar'),
            })
            ->values()
            ->all();
    }

    private function hasVehicleScheduleConflict(array $data, ?VehicleRequest $current = null): bool
    {
        [$requestedStart, $requestedEnd] = $this->requestTimeWindow($data);

        return VehicleRequest::query()
            ->where('vehicle_id', $data['vehicle_id'])
            ->whereDate('date', $data['date'])
            ->whereNotIn('status', ['cancelada', 'concluida', 'rejeitada'])
            ->when($current, fn ($query) => $query->whereKeyNot($current->id))
            ->get(['departure_time', 'expected_arrival_time'])
            ->contains(function (VehicleRequest $request) use ($requestedStart, $requestedEnd) {
                [$existingStart, $existingEnd] = $this->requestTimeWindow($request->toArray());

                return $existingStart < $requestedEnd && $existingEnd > $requestedStart;
            });
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function requestTimeWindow(array $data): array
    {
        $start = Carbon::createFromFormat('H:i', substr((string) $data['departure_time'], 0, 5));
        $end = filled($data['expected_arrival_time'] ?? null)
            ? Carbon::createFromFormat('H:i', substr((string) $data['expected_arrival_time'], 0, 5))
            : $start->copy()->addHour();

        if ($end->lessThanOrEqualTo($start)) {
            throw ValidationException::withMessages([
                'expected_arrival_time' => ['A previsao de chegada deve ser posterior a hora de saida.'],
            ]);
        }

        return [$start->format('H:i:s'), $end->format('H:i:s')];
    }

    private function timeOnly(null|string|\DateTimeInterface $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return Carbon::parse($value)->format('H:i');
    }

    private function validatedData(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'department_id' => [$required, 'integer', 'exists:departments,id'],
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'driver_id' => ['nullable', 'integer', 'exists:drivers,id'],
            'route_description' => [$required, 'string', 'max:4000'],
            'origin' => [$required, 'string', 'max:160'],
            'destination' => [$required, 'string', 'max:160'],
            'date' => [$required, 'date'],
            'departure_time' => [$required, 'date_format:H:i'],
            'expected_arrival_time' => ['nullable', 'date_format:H:i'],
            'passengers_count' => [$required, 'integer', 'min:1', 'max:100'],
            'responsible' => [$required, 'string', 'max:160'],
            'initial_km' => ['nullable', 'integer', 'min:0'],
            'final_km' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'status' => ['sometimes', Rule::in(['solicitacao', 'analise', 'aprovada', 'em_deslocamento', 'concluida', 'cancelada', 'rejeitada'])],
        ]);
    }

    private function ensureResourcesAreAvailable(array $data, ?VehicleRequest $current = null): void
    {
        $this->ensureKmSequence($data);

        if (empty($data['date']) || empty($data['departure_time'])) {
            return;
        }

        $this->requestTimeWindow($data);

        if (! empty($data['vehicle_id'])) {
            $this->ensureVehicleCanServeRequest($data, $current);
        }

        if (! empty($data['driver_id'])) {
            $this->ensureDriverCanServeRequest($data);
        }

        if (! empty($data['vehicle_id']) && $this->hasVehicleScheduleConflict($data, $current)) {
            throw ValidationException::withMessages([
                'vehicle_id' => ['Este veiculo ja possui agendamento em conflito neste periodo.'],
            ]);
        }

        if (! empty($data['driver_id']) && $this->hasDriverScheduleConflict($data, $current)) {
            throw ValidationException::withMessages([
                'driver_id' => ['Este motorista ja possui agendamento em conflito neste periodo.'],
            ]);
        }
    }

    private function lockSchedulingResources(array $data, ?VehicleRequest $current = null): void
    {
        collect([$current?->vehicle_id, $data['vehicle_id'] ?? null])
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->sort()
            ->each(fn (int $id) => Vehicle::query()->whereKey($id)->lockForUpdate()->first());

        collect([$current?->driver_id, $data['driver_id'] ?? null])
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->sort()
            ->each(fn (int $id) => Driver::query()->whereKey($id)->lockForUpdate()->first());
    }

    private function ensureKmSequence(array $data): void
    {
        if (($data['initial_km'] ?? null) === null || ($data['final_km'] ?? null) === null) {
            return;
        }

        if ((int) $data['final_km'] < (int) $data['initial_km']) {
            throw ValidationException::withMessages([
                'final_km' => ['O KM final deve ser maior ou igual ao KM inicial.'],
            ]);
        }
    }

    private function ensureVehicleCanServeRequest(array $data, ?VehicleRequest $current = null): void
    {
        $vehicle = Vehicle::query()->find($data['vehicle_id']);

        if (! $vehicle) {
            return;
        }

        $isCurrentTripVehicleInUse = $current
            && (int) $current->vehicle_id === (int) $vehicle->id
            && $current->status === 'em_deslocamento'
            && $vehicle->status === 'em_uso';

        if ($vehicle->status !== 'disponivel' && ! $isCurrentTripVehicleInUse) {
            throw ValidationException::withMessages([
                'vehicle_id' => ['Este veiculo nao esta disponivel para agendamento.'],
            ]);
        }

        if ($vehicle->capacity !== null && (int) ($data['passengers_count'] ?? 0) > $vehicle->capacity) {
            throw ValidationException::withMessages([
                'passengers_count' => ["Este veiculo comporta no maximo {$vehicle->capacity} passageiro(s)."],
            ]);
        }
    }

    private function ensureDriverCanServeRequest(array $data): void
    {
        $driver = Driver::query()->find($data['driver_id']);

        if (! $driver) {
            return;
        }

        if ($driver->status !== 'ativo') {
            throw ValidationException::withMessages([
                'driver_id' => ['Este motorista nao esta ativo.'],
            ]);
        }

        if (Carbon::parse($driver->cnh_expires_at)->isPast()) {
            throw ValidationException::withMessages([
                'driver_id' => ['Este motorista esta com CNH vencida.'],
            ]);
        }
    }

    private function hasDriverScheduleConflict(array $data, ?VehicleRequest $current = null): bool
    {
        [$requestedStart, $requestedEnd] = $this->requestTimeWindow($data);

        return VehicleRequest::query()
            ->where('driver_id', $data['driver_id'])
            ->whereDate('date', $data['date'])
            ->whereNotIn('status', ['cancelada', 'concluida', 'rejeitada'])
            ->when($current, fn ($query) => $query->whereKeyNot($current->id))
            ->get(['departure_time', 'expected_arrival_time'])
            ->contains(function (VehicleRequest $request) use ($requestedStart, $requestedEnd) {
                [$existingStart, $existingEnd] = $this->requestTimeWindow($request->toArray());

                return $existingStart < $requestedEnd && $existingEnd > $requestedStart;
            });
    }

    private function syncVehicleStatus(VehicleRequest $vehicleRequest, string $nextStatus): void
    {
        if (! $vehicleRequest->vehicle_id) {
            return;
        }

        if ($nextStatus === 'em_deslocamento') {
            Vehicle::query()
                ->whereKey($vehicleRequest->vehicle_id)
                ->where('status', 'disponivel')
                ->update(['status' => 'em_uso']);

            return;
        }

        if (! in_array($nextStatus, ['concluida', 'cancelada'], true)) {
            return;
        }

        Vehicle::query()
            ->whereKey($vehicleRequest->vehicle_id)
            ->where('status', 'em_uso')
            ->update([
                'status' => 'disponivel',
                ...($nextStatus === 'concluida' && $vehicleRequest->final_km ? ['current_km' => $vehicleRequest->final_km] : []),
            ]);
    }

    private function hasFullAccess(Request $request): bool
    {
        return $request->user()->hasRole('Administrador Geral') || $request->user()->can('transporte.aprovar');
    }

    private function enforceDepartmentScope(Request $request, array $data): array
    {
        if (! $this->hasFullAccess($request)) {
            $data['department_id'] = $request->user()->department_id;
        }

        return $data;
    }

    private function authorizeDepartmentAccess(Request $request, VehicleRequest $vehicleRequest): void
    {
        abort_if(! $this->hasFullAccess($request) && $vehicleRequest->department_id !== $request->user()->department_id, 403);
    }

    private function authorizeTransportView(Request $request): void
    {
        abort_if($request->user()->cannot('transporte.visualizar'), 403);
    }
}
