<?php

namespace App\Console\Commands;

use App\Support\Renavam;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditTransportIdentifiers extends Command
{
    protected $signature = 'siges:audit-transport-identifiers
        {--fail-on-issues : Retornar falha quando houver RENAVAM inválido ou CNH ainda sem classificação institucional}';

    protected $description = 'Audita a estrutura de CNH e RENAVAM sem exibir valores ou alterar dados';

    public function handle(): int
    {
        $cnhValues = DB::table('drivers')
            ->whereNotNull('cnh')
            ->where('cnh', '!=', '')
            ->pluck('cnh');
        $renavamValues = DB::table('vehicles')
            ->whereNotNull('renavam')
            ->where('renavam', '!=', '')
            ->pluck('renavam');
        $validRenavam = $renavamValues->filter(
            static fn (mixed $value): bool => Renavam::isValid($value),
        )->count();
        $cnhUnclassified = $cnhValues->count();
        $invalidRenavam = $renavamValues->count() - $validRenavam;

        $this->table(
            ['Campo', 'Preenchidos', 'Válidos', 'Pendências'],
            [
                ['CNH — identificador não definido', $cnhValues->count(), 'não avaliado', $cnhUnclassified],
                ['RENAVAM', $renavamValues->count(), $validRenavam, $invalidRenavam],
            ],
        );
        $this->line('Nenhum identificador foi exibido ou alterado.');

        if ($cnhUnclassified > 0) {
            $this->warn('Há CNH preenchida, mas a norma prevê identificadores distintos. Defina institucionalmente qual deles o campo representa antes de validar ou migrar.');
        }

        if ($invalidRenavam > 0) {
            $this->warn("Foram encontrados {$invalidRenavam} RENAVAM(s) inválido(s).");
        }

        if ($this->option('fail-on-issues') && ($cnhUnclassified > 0 || $invalidRenavam > 0)) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
