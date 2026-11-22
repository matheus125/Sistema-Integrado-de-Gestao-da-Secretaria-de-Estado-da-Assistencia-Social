<?php

namespace App\Http\Controllers\Api\Materials;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AssetMovementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $movements = AssetMovement::query()
            ->with([
                'asset:id,asset_number,description',
                'fromDepartment:id,name',
                'toDepartment:id,name',
                'responsible:id,name',
            ])
            ->when(! $this->hasFullAccess($request), function ($query) use ($request) {
                $query->where(function ($query) use ($request) {
                    $query->where('from_department_id', $request->user()->department_id)
                        ->orWhere('to_department_id', $request->user()->department_id);
                });
            })
            ->latest('moved_at')
            ->paginate($request->integer('per_page', 15));

        return response()->json($movements);
    }

    public function store(Request $request): JsonResponse
    {
        abort_if($request->user()->cannot('materiais-patrimonio.cadastrar'), 403);

        $data = $request->validate([
            'asset_id' => ['required', 'integer', 'exists:assets,id'],
            'to_department_id' => ['nullable', 'required_if:type,transferencia', 'integer', 'exists:departments,id'],
            'type' => ['required', Rule::in(['transferencia', 'manutencao', 'retorno', 'baixa'])],
            'notes' => ['nullable', 'string', 'max:4000'],
            'moved_at' => ['required', 'date'],
        ]);

        $movement = DB::transaction(function () use ($request, $data) {
            $asset = Asset::query()->lockForUpdate()->findOrFail($data['asset_id']);
            abort_if(! $this->hasFullAccess($request) && $asset->department_id !== $request->user()->department_id, 403);
            $fromDepartmentId = $asset->department_id;

            if ($data['type'] === 'transferencia') {
                $asset->department_id = $data['to_department_id'] ?? null;
                $asset->status = 'ativo';
            } elseif ($data['type'] === 'manutencao') {
                $asset->status = 'manutencao';
            } elseif ($data['type'] === 'retorno') {
                $asset->status = 'ativo';
            } elseif ($data['type'] === 'baixa') {
                $asset->status = 'baixado';
            }

            $asset->save();

            return AssetMovement::create([
                ...$data,
                'from_department_id' => $fromDepartmentId,
                'responsible_id' => $request->user()->id,
            ])->load(['asset:id,asset_number,description', 'fromDepartment:id,name', 'toDepartment:id,name', 'responsible:id,name']);
        });

        activity('patrimonio')->causedBy($request->user())->performedOn($movement)->log('movimentacao de patrimonio registrada');

        return response()->json($movement, 201);
    }

    private function hasFullAccess(Request $request): bool
    {
        return $request->user()->hasRole('Administrador Geral') || $request->user()->can('materiais-patrimonio.aprovar');
    }
}
