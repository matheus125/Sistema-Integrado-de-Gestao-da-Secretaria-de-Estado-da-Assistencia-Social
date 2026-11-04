<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        if (! Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['Credenciais invalidas.'],
            ]);
        }

        $user = $request->user();

        activity('auth')->causedBy($user)->log('login');

        return response()->json([
            'token' => $user->createToken($credentials['device_name'] ?? 'siges-web')->plainTextToken,
            'user' => $this->userPayload($user->load('department')),
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values(),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('department');

        return response()->json([
            'user' => $this->userPayload($user),
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        activity('auth')->causedBy($request->user())->log('logout');
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Sessao encerrada.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload($user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'position' => $user->position,
            'status' => $user->status,
            'department' => $user->department,
        ];
    }
}
