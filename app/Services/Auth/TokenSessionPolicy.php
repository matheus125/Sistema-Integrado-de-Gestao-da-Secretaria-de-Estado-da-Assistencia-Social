<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\Settings\SettingValue;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;
use Throwable;

final class TokenSessionPolicy
{
    public function absoluteLifetimeMinutes(): int
    {
        return max(15, (int) config('sanctum.expiration', 480));
    }

    public function idleTimeoutMinutes(): int
    {
        $fallback = max(5, (int) config('sanctum.idle_timeout', 120));

        try {
            return min(1440, max(5, (int) SettingValue::get('session_timeout_minutes', $fallback)));
        } catch (Throwable) {
            return $fallback;
        }
    }

    public function maximumTokensPerUser(): int
    {
        return max(1, (int) config('sanctum.max_tokens_per_user', 5));
    }

    public function expiresAt(): Carbon
    {
        return now()->addMinutes($this->absoluteLifetimeMinutes());
    }

    public function isValid(PersonalAccessToken $token, bool $sanctumIsValid): bool
    {
        if (! $sanctumIsValid || $token->tokenable?->status !== 'ativo') {
            return false;
        }

        $lastActivity = $token->last_used_at ?? $token->created_at;

        return $lastActivity !== null
            && $lastActivity->gt(now()->subMinutes($this->idleTimeoutMinutes()));
    }

    public function enforceTokenLimit(User $user, int $currentTokenId): void
    {
        $keepIds = $user->tokens()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($this->maximumTokensPerUser())
            ->pluck('id');

        if (! $keepIds->contains($currentTokenId)) {
            $keepIds->push($currentTokenId);
        }

        $user->tokens()->whereNotIn('id', $keepIds)->delete();
    }
}
