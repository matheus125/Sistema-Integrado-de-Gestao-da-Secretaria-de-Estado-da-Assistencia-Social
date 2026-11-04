<?php

namespace App\Http\Controllers\Api\Materials;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = $request->string('search')->toString();

        $products = Product::query()
            ->with('category:id,name')
            ->when($search, fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('description', 'like', "%{$search}%")
                    ->orWhere('internal_code', 'like', "%{$search}%")
                    ->orWhere('supplier', 'like', "%{$search}%");
            }))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json($products);
    }

    public function store(Request $request): JsonResponse
    {
        abort_if($request->user()->cannot('materiais-patrimonio.cadastrar'), 403);

        $product = Product::create($this->validatedData($request))->load('category:id,name');

        activity('materiais')->causedBy($request->user())->performedOn($product)->log('produto cadastrado');

        return response()->json($product, 201);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        abort_if($request->user()->cannot('materiais-patrimonio.editar'), 403);

        $product->update($this->validatedData($request, $product));

        activity('materiais')->causedBy($request->user())->performedOn($product)->log('produto atualizado');

        return response()->json($product->load('category:id,name'));
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        abort_if($request->user()->cannot('materiais-patrimonio.excluir'), 403);

        $product->delete();

        activity('materiais')->causedBy($request->user())->performedOn($product)->log('produto excluido');

        return response()->json(['message' => 'Produto removido.']);
    }

    private function validatedData(Request $request, ?Product $product = null): array
    {
        return $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:material_categories,id'],
            'internal_code' => ['required', 'string', 'max:80', Rule::unique('products', 'internal_code')->ignore($product)],
            'description' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:30'],
            'supplier' => ['nullable', 'string', 'max:160'],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'minimum_quantity' => ['nullable', 'numeric', 'min:0'],
            'location' => ['nullable', 'string', 'max:160'],
            'status' => ['required', Rule::in(['ativo', 'inativo'])],
        ]);
    }
}
