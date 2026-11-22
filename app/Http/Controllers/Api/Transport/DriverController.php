<?php

namespace App\Http\Controllers\Api\Transport;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Rules\BrazilianPhoneRule;
use App\Support\BrazilianPhone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DriverController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = $request->string('search')->toString();

        $drivers = Driver::query()
            ->when($search, fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('cnh', 'like', "%{$search}%");
            }))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json($drivers);
    }

    public function store(Request $request): JsonResponse
    {
        abort_if($request->user()->cannot('transporte.cadastrar'), 403);

        $driver = Driver::create($this->validatedData($request));

        activity('transporte')->causedBy($request->user())->performedOn($driver)->log('motorista cadastrado');

        return response()->json($driver, 201);
    }

    public function update(Request $request, Driver $driver): JsonResponse
    {
        abort_if($request->user()->cannot('transporte.editar'), 403);

        $driver->update($this->validatedData($request, $driver));

        activity('transporte')->causedBy($request->user())->performedOn($driver)->log('motorista atualizado');

        return response()->json($driver);
    }

    public function destroy(Request $request, Driver $driver): JsonResponse
    {
        abort_if($request->user()->cannot('transporte.excluir'), 403);

        $driver->delete();

        activity('transporte')->causedBy($request->user())->performedOn($driver)->log('motorista excluido');

        return response()->json(['message' => 'Motorista removido.']);
    }

    private function validatedData(Request $request, ?Driver $driver = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'cnh' => ['required', 'string', 'max:40', Rule::unique('drivers', 'cnh')->ignore($driver)],
            'cnh_category' => ['required', 'string', 'max:10'],
            'cnh_expires_at' => ['required', 'date'],
            'phone' => ['nullable', 'string', 'max:25', new BrazilianPhoneRule],
            'status' => ['required', Rule::in(['ativo', 'inativo', 'afastado'])],
        ]);

        if (array_key_exists('phone', $data)) {
            $data['phone'] = BrazilianPhone::normalize($data['phone']);
        }

        return $data;
    }
}
