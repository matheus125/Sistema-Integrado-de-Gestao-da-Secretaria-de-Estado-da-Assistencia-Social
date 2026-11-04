<?php

namespace App\Services\It;

class ItPriorityCalculator
{
    public function calculate(?string $impact, ?string $urgency, ?string $fallback = 'media'): string
    {
        if (! $impact || ! $urgency) return $fallback ?: 'media';
        return match ($impact.':'.$urgency) {
            'alto:alta', 'alto:media', 'medio:alta' => 'urgente',
            'alto:baixa', 'medio:media', 'baixo:alta' => 'alta',
            'medio:baixa', 'baixo:media' => 'media',
            default => 'baixa',
        };
    }
}
