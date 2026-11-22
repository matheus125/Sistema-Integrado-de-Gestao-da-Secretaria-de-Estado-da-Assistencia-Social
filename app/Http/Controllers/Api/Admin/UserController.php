<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\BrazilianPhoneRule;
use App\Support\BrazilianPhone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = $request->string('search')->toString();

        $users = User::query()
            ->with(['department:id,name', 'roles:id,name'])
            ->when($search, fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('position', 'like', "%{$search}%");
            }))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json($users);
    }

    public function store(Request $request): JsonResponse
    {
        abort_if($request->user()->cannot('administracao.cadastrar'), 403);

        $data = $this->validatedData($request);
        $roles = $data['roles'] ?? [];
        unset($data['roles']);

        $user = User::create($data);
        $user->syncRoles($roles);

        activity('administracao')->causedBy($request->user())->performedOn($user)->log('usuario cadastrado');

        return response()->json($user->load(['department:id,name', 'roles:id,name']), 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        abort_if($request->user()->cannot('administracao.editar'), 403);

        $data = $this->validatedData($request, $user);
        $roles = $data['roles'] ?? null;
        unset($data['roles']);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $mustRevokeTokens = array_key_exists('password', $data)
            || ($user->status === 'ativo' && $data['status'] !== 'ativo');

        $user->update($data);

        if ($mustRevokeTokens) {
            $user->tokens()->delete();
        }

        if (is_array($roles)) {
            $user->syncRoles($roles);
        }

        activity('administracao')->causedBy($request->user())->performedOn($user)->log('usuario atualizado');

        return response()->json($user->load(['department:id,name', 'roles:id,name']));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        abort_if($request->user()->cannot('administracao.excluir'), 403);
        abort_if($request->user()->is($user), 422, 'Voce nao pode remover o proprio usuario.');

        $user->tokens()->delete();
        $user->delete();

        activity('administracao')->causedBy($request->user())->performedOn($user)->log('usuario removido');

        return response()->json(['message' => 'Usuario removido.']);
    }

    private function validatedData(Request $request, ?User $user = null): array
    {
        $passwordRule = $user ? ['nullable', 'string', 'min:8', 'max:255'] : ['required', 'string', 'min:8', 'max:255'];

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:180', Rule::unique('users', 'email')->ignore($user)],
            'password' => $passwordRule,
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'phone' => ['nullable', 'string', 'max:25', new BrazilianPhoneRule],
            'position' => ['nullable', 'string', 'max:120'],
            'status' => ['required', Rule::in(['ativo', 'inativo', 'afastado'])],
            'roles' => ['array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
        ]);

        if (array_key_exists('phone', $data)) {
            $data['phone'] = BrazilianPhone::normalize($data['phone']);
        }

        return $data;
    }
}
