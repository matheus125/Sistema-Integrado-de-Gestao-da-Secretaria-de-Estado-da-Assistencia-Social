<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Auth\TokenSessionPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function login(Request $request, TokenSessionPolicy $policy): JsonResponse
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $limits = $this->loginRateLimits($request, $credentials['email']);

        if ($response = $this->rateLimitResponse($limits)) {
            return $response;
        }

        $guard = Auth::guard('web');

        if (! $guard->attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
            'status' => 'ativo',
        ])) {
            $this->recordFailedLogin($limits);

            throw ValidationException::withMessages([
                'email' => ['Credenciais invalidas.'],
            ]);
        }

        RateLimiter::clear($limits['identifier']['key']);

        $user = $guard->user();
        $deviceName = trim($credentials['device_name'] ?? '') ?: 'siges-web';
        $newToken = $user->createToken($deviceName, ['*'], $policy->expiresAt());
        $policy->enforceTokenLimit($user, $newToken->accessToken->getKey());

        activity('auth')->causedBy($user)->log('login');
        $guard->logout();

        return response()->json([
            'token' => $newToken->plainTextToken,
            'token_expires_at' => $newToken->accessToken->expires_at?->toIso8601String(),
            'idle_timeout_minutes' => $policy->idleTimeoutMinutes(),
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

    public function logoutAll(Request $request): JsonResponse
    {
        $user = $request->user();
        $revoked = $user->tokens()->delete();

        activity('auth')->causedBy($user)->withProperties(['revoked_tokens' => $revoked])->log('logout de todas as sessoes');

        return response()->json([
            'message' => 'Todas as sessoes foram encerradas.',
            'revoked' => $revoked,
        ]);
    }

    public function devices(Request $request, TokenSessionPolicy $policy): JsonResponse
    {
        $currentAccessToken = $request->user()->currentAccessToken();
        $currentTokenId = $currentAccessToken instanceof PersonalAccessToken
            ? $currentAccessToken->getKey()
            : null;
        $idleTimeout = $policy->idleTimeoutMinutes();
        $absoluteLifetime = $policy->absoluteLifetimeMinutes();

        $devices = $request->user()->tokens()
            ->latest()
            ->get()
            ->map(function ($token) use ($absoluteLifetime, $currentTokenId, $idleTimeout): array {
                $lastActivity = $token->last_used_at ?? $token->created_at;
                $absoluteExpiresAt = $token->created_at?->copy()->addMinutes($absoluteLifetime);
                $idleExpiresAt = $lastActivity?->copy()->addMinutes($idleTimeout);
                $expirationCandidates = collect([$token->expires_at, $absoluteExpiresAt, $idleExpiresAt])->filter();
                $effectiveExpiresAt = $expirationCandidates->sort()->first();

                return [
                    'id' => $token->getKey(),
                    'name' => $token->name,
                    'current' => $token->getKey() === $currentTokenId,
                    'created_at' => $token->created_at?->toIso8601String(),
                    'last_used_at' => $token->last_used_at?->toIso8601String(),
                    'expires_at' => $effectiveExpiresAt?->toIso8601String(),
                    'expired' => $effectiveExpiresAt?->isPast() ?? true,
                ];
            })
            ->values();

        return response()->json(['data' => $devices]);
    }

    public function revokeDevice(Request $request, int $token): JsonResponse
    {
        $accessToken = $request->user()->tokens()->findOrFail($token);
        $currentAccessToken = $request->user()->currentAccessToken();
        $current = $currentAccessToken instanceof PersonalAccessToken
            && $currentAccessToken->getKey() === $accessToken->getKey();

        activity('auth')->causedBy($request->user())->withProperties([
            'token_id' => $accessToken->getKey(),
            'current' => $current,
        ])->log('sessao revogada');

        $accessToken->delete();

        return response()->json([
            'message' => 'Sessao revogada.',
            'current' => $current,
        ]);
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

    /**
     * @return array{identifier: array{key: string, max_attempts: int}, ip: array{key: string, max_attempts: int}, decay_seconds: int}
     */
    private function loginRateLimits(Request $request, string $email): array
    {
        $ip = $request->ip() ?: 'unknown';
        $identifierHash = hash('sha256', Str::lower(trim($email)));

        return [
            'identifier' => [
                'key' => "login:identifier:{$identifierHash}:{$ip}",
                'max_attempts' => max(1, (int) config('auth.login_throttle.max_attempts', 5)),
            ],
            'ip' => [
                'key' => "login:ip:{$ip}",
                'max_attempts' => max(1, (int) config('auth.login_throttle.max_attempts_per_ip', 20)),
            ],
            'decay_seconds' => max(1, (int) config('auth.login_throttle.decay_seconds', 60)),
        ];
    }

    /**
     * @param  array{identifier: array{key: string, max_attempts: int}, ip: array{key: string, max_attempts: int}, decay_seconds: int}  $limits
     */
    private function rateLimitResponse(array $limits): ?JsonResponse
    {
        $blockedLimits = collect([$limits['identifier'], $limits['ip']])
            ->filter(fn (array $limit): bool => RateLimiter::tooManyAttempts($limit['key'], $limit['max_attempts']));

        if ($blockedLimits->isEmpty()) {
            return null;
        }

        $retryAfter = max(1, $blockedLimits
            ->map(fn (array $limit): int => RateLimiter::availableIn($limit['key']))
            ->max());

        return response()->json([
            'message' => 'Muitas tentativas de acesso. Tente novamente mais tarde.',
        ], 429, [
            'Retry-After' => (string) $retryAfter,
        ]);
    }

    /**
     * @param  array{identifier: array{key: string, max_attempts: int}, ip: array{key: string, max_attempts: int}, decay_seconds: int}  $limits
     */
    private function recordFailedLogin(array $limits): void
    {
        RateLimiter::hit($limits['identifier']['key'], $limits['decay_seconds']);
        RateLimiter::hit($limits['ip']['key'], $limits['decay_seconds']);
    }
}
