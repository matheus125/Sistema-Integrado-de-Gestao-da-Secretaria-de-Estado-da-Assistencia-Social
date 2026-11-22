<?php

namespace App\Http\Controllers\Api\Materials;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AssetController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = $request->string('search')->toString();

        $assets = Asset::query()
            ->with(['department:id,name', 'responsible:id,name'])
            ->when(! $this->hasFullAccess($request), fn ($query) => $query->where('department_id', $request->user()->department_id))
            ->when($search, fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('description', 'like', "%{$search}%")
                    ->orWhere('asset_number', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%");
            }))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json($assets);
    }

    public function show(Request $request, Asset $asset): JsonResponse
    {
        $this->authorizeDepartmentAccess($request, $asset);

        return response()->json($asset->load([
            'department:id,name',
            'responsible:id,name',
            'tickets' => fn ($query) => $query
                ->with(['assignee:id,name', 'categoryModel:id,name,color,icon'])
                ->latest('it_tickets.created_at')
                ->limit(30),
        ]));
    }

    public function store(Request $request): JsonResponse
    {
        abort_if($request->user()->cannot('materiais-patrimonio.cadastrar'), 403);

        $asset = Asset::create($this->enforceDepartmentScope($request, $this->validatedData($request)))->load(['department:id,name', 'responsible:id,name']);

        activity('patrimonio')->causedBy($request->user())->performedOn($asset)->log('patrimonio cadastrado');

        return response()->json($asset, 201);
    }

    public function update(Request $request, Asset $asset): JsonResponse
    {
        abort_if($request->user()->cannot('materiais-patrimonio.editar'), 403);
        $this->authorizeDepartmentAccess($request, $asset);
        $asset->update($this->enforceDepartmentScope($request, $this->validatedData($request, $asset)));

        activity('patrimonio')->causedBy($request->user())->performedOn($asset)->log('patrimonio atualizado');

        return response()->json($asset->load(['department:id,name', 'responsible:id,name']));
    }

    public function destroy(Request $request, Asset $asset): JsonResponse
    {
        abort_if($request->user()->cannot('materiais-patrimonio.excluir'), 403);
        $this->authorizeDepartmentAccess($request, $asset);
        $asset->delete();

        activity('patrimonio')->causedBy($request->user())->performedOn($asset)->log('patrimonio excluido');

        return response()->json(['message' => 'Patrimonio removido.']);
    }

    private function validatedData(Request $request, ?Asset $asset = null): array
    {
        return $request->validate([
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'responsible_id' => ['nullable', 'integer', 'exists:users,id'],
            'asset_number' => ['required', 'string', 'max:80', Rule::unique('assets', 'asset_number')->ignore($asset)],
            'description' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:120'],
            'brand' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:120'],
            'serial_number' => ['nullable', 'string', 'max:120'],
            'acquired_at' => ['nullable', 'date'],
            'value' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'invoice_number' => ['nullable', 'string', 'max:80'],
            'location' => ['nullable', 'string', 'max:160'],
            'status' => ['required', Rule::in(['ativo', 'manutencao', 'baixado', 'inativo'])],
        ]);
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

    private function authorizeDepartmentAccess(Request $request, Asset $asset): void
    {
        abort_if(! $this->hasFullAccess($request) && $asset->department_id !== $request->user()->department_id, 403);
    }
}
