<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'roles' => Role::query()->with('permissions:id,name')->orderBy('name')->get(['id', 'name']),
            'permissions' => Permission::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_if($request->user()->cannot('administracao.cadastrar'), 403);

        $data = $this->validatedData($request);
        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        $role->syncPermissions($data['permissions'] ?? []);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        activity('administracao')->causedBy($request->user())->performedOn($role)->log('perfil cadastrado');

        return response()->json($role->load('permissions:id,name'), 201);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        abort_if($request->user()->cannot('administracao.editar'), 403);
        abort_if($role->name === 'Administrador Geral' && $request->user()->cannot('administracao.aprovar'), 403);

        $data = $this->validatedData($request, $role);
        $role->update(['name' => $data['name']]);
        $role->syncPermissions($data['permissions'] ?? []);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        activity('administracao')->causedBy($request->user())->performedOn($role)->log('perfil atualizado');

        return response()->json($role->load('permissions:id,name'));
    }

    public function destroy(Request $request, Role $role): JsonResponse
    {
        abort_if($request->user()->cannot('administracao.excluir'), 403);
        abort_if(in_array($role->name, ['Administrador Geral', 'Gerente', 'Supervisor', 'Servidor', 'Consulta'], true), 422, 'Perfil padrao nao pode ser removido.');

        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        activity('administracao')->causedBy($request->user())->performedOn($role)->log('perfil removido');

        return response()->json(['message' => 'Perfil removido.']);
    }

    private function validatedData(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('roles', 'name')->ignore($role)],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')],
        ]);
    }
}
