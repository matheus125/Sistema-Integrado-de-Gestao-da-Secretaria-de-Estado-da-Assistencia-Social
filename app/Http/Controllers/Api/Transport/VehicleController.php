<?php

namespace App\Http\Controllers\Api\Transport;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VehicleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = $request->string('search')->toString();

        $vehicles = Vehicle::query()
            ->when($search, fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('plate', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%");
            }))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json($vehicles);
    }

    public function store(Request $request): JsonResponse
    {
        abort_if($request->user()->cannot('transporte.cadastrar'), 403);

        $vehicle = Vehicle::create($this->validatedData($request));

        activity('transporte')->causedBy($request->user())->performedOn($vehicle)->log('veiculo cadastrado');

        return response()->json($vehicle, 201);
    }

    public function update(Request $request, Vehicle $vehicle): JsonResponse
    {
        abort_if($request->user()->cannot('transporte.editar'), 403);

        $vehicle->update($this->validatedData($request, $vehicle));

        activity('transporte')->causedBy($request->user())->performedOn($vehicle)->log('veiculo atualizado');

        return response()->json($vehicle);
    }

    public function destroy(Request $request, Vehicle $vehicle): JsonResponse
    {
        abort_if($request->user()->cannot('transporte.excluir'), 403);

        $vehicle->delete();

        activity('transporte')->causedBy($request->user())->performedOn($vehicle)->log('veiculo excluido');

        return response()->json(['message' => 'Veiculo removido.']);
    }

    private function validatedData(Request $request, ?Vehicle $vehicle = null): array
    {
        return $request->validate([
            'brand' => ['required', 'string', 'max:120'],
            'model' => ['required', 'string', 'max:120'],
            'year' => ['nullable', 'integer', 'min:1950', 'max:2100'],
            'category' => ['nullable', 'string', 'max:80'],
            'type' => ['nullable', 'string', 'max:80'],
            'color' => ['nullable', 'string', 'max:60'],
            'plate' => ['required', 'string', 'max:20', Rule::unique('vehicles', 'plate')->ignore($vehicle)],
            'renavam' => ['nullable', 'string', 'max:40', Rule::unique('vehicles', 'renavam')->ignore($vehicle)],
            'chassis' => ['nullable', 'string', 'max:80', Rule::unique('vehicles', 'chassis')->ignore($vehicle)],
            'fuel' => ['nullable', 'string', 'max:60'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100'],
            'status' => ['required', Rule::in(['disponivel', 'em_uso', 'manutencao', 'inativo'])],
            'current_km' => ['nullable', 'integer', 'min:0'],
            'last_maintenance_at' => ['nullable', 'date'],
            'next_licensing_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
