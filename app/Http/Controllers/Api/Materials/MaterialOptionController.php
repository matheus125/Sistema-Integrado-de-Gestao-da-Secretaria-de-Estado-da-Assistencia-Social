<?php

namespace App\Http\Controllers\Api\Materials;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Department;
use App\Models\MaterialCategory;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaterialOptionController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $fullAccess = $request->user()->hasRole('Administrador Geral') || $request->user()->can('materiais-patrimonio.aprovar');

        return response()->json([
            'categories' => MaterialCategory::query()->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()->where('status', 'ativo')->orderBy('description')->get(['id', 'internal_code', 'description', 'quantity']),
            'assets' => Asset::query()
                ->when(! $fullAccess, fn ($query) => $query->where('department_id', $request->user()->department_id))
                ->orderBy('description')
                ->get(['id', 'asset_number', 'description', 'department_id']),
            'departments' => Department::query()
                ->where('active', true)
                ->when(! $fullAccess, fn ($query) => $query->whereKey($request->user()->department_id))
                ->orderBy('name')
                ->get(['id', 'name']),
            'users' => User::query()
                ->where('status', 'ativo')
                ->when(! $fullAccess, fn ($query) => $query->where('department_id', $request->user()->department_id))
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }
}
