<?php

namespace App\Console\Commands;

use App\Services\Auth\TokenSessionPolicy;
use Illuminate\Console\Command;
use Laravel\Sanctum\Sanctum;

class PruneAccessTokens extends Command
{
    protected $signature = 'siges:prune-tokens
        {--hours= : Horas de retencao depois que um token expira}';

    protected $description = 'Remove tokens Sanctum expirados ou inativos sem exibir seus valores';

    public function handle(TokenSessionPolicy $policy): int
    {
        $configuredHours = (int) config('sanctum.prune_expired_hours', 24);
        $hours = $this->option('hours');
        $retentionHours = max(0, is_numeric($hours) ? (int) $hours : $configuredHours);
        $expiredBefore = now()->subHours($retentionHours);
        $absoluteBefore = now()
            ->subMinutes($policy->absoluteLifetimeMinutes())
            ->subHours($retentionHours);
        $inactiveBefore = now()
            ->subMinutes($policy->idleTimeoutMinutes())
            ->subHours($retentionHours);
        $model = Sanctum::personalAccessTokenModel();

        $deleted = $model::query()
            ->where(function ($query) use ($absoluteBefore, $expiredBefore, $inactiveBefore): void {
                $query->where('expires_at', '<', $expiredBefore)
                    ->orWhere('created_at', '<', $absoluteBefore)
                    ->orWhere(function ($query) use ($inactiveBefore): void {
                        $query->whereNotNull('last_used_at')
                            ->where('last_used_at', '<', $inactiveBefore);
                    })
                    ->orWhere(function ($query) use ($inactiveBefore): void {
                        $query->whereNull('last_used_at')
                            ->where('created_at', '<', $inactiveBefore);
                    });
            })
            ->delete();

        $this->info("{$deleted} token(s) expirado(s) ou inativo(s) removido(s).");

        return self::SUCCESS;
    }
}
