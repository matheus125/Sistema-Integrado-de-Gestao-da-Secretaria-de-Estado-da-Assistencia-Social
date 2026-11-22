<?php

namespace App\Http\Controllers\Api\Materials;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StockMovementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $movements = StockMovement::query()
            ->with(['product:id,description,internal_code', 'department:id,name', 'responsible:id,name'])
            ->when(! $this->hasFullAccess($request), fn ($query) => $query->where('department_id', $request->user()->department_id))
            ->latest('movement_date')
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json($movements);
    }

    public function store(Request $request): JsonResponse
    {
        abort_if($request->user()->cannot('materiais-patrimonio.cadastrar'), 403);

        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'type' => ['required', Rule::in(['entrada', 'saida'])],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'supplier' => ['nullable', 'string', 'max:160'],
            'invoice_number' => ['nullable', 'string', 'max:80'],
            'destination' => ['nullable', 'string', 'max:160'],
            'reason' => ['nullable', 'string', 'max:160'],
            'movement_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ]);
        $data = $this->enforceDepartmentScope($request, $data);

        $movement = DB::transaction(function () use ($request, $data) {
            $product = Product::query()->lockForUpdate()->findOrFail($data['product_id']);
            $quantity = (float) $data['quantity'];
            $current = (float) $product->quantity;

            if ($data['type'] === 'saida' && $current < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => ['Estoque insuficiente para esta saida.'],
                ]);
            }

            $product->quantity = $data['type'] === 'entrada'
                ? $current + $quantity
                : $current - $quantity;
            $product->save();

            return StockMovement::create([
                ...$data,
                'responsible_id' => $request->user()->id,
            ])->load(['product:id,description,internal_code', 'department:id,name', 'responsible:id,name']);
        });

        activity('materiais')->causedBy($request->user())->performedOn($movement)->log('movimentacao de estoque registrada');

        return response()->json($movement, 201);
    }

    private function hasFullAccess(Request $request): bool
    {
        return $request->user()->hasRole('Administrador Geral') || $request->user()->can('materiais-patrimonio.aprovar');
    }

    private function enforceDepartmentScope(Request $request, array $data): array
    {
        if (! $this->hasFullAccess($request)) {
            $data['department_id'] = $request->user()->department_id;
        }

        return $data;
    }
}
