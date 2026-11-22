<?php

namespace App\Console\Commands;

use App\Support\BrazilianPhone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditPhoneData extends Command
{
    protected $signature = 'siges:audit-phones
        {--fail-on-invalid : Retornar código de falha quando houver telefone inválido}';

    protected $description = 'Audita telefones legados sem exibir valores ou alterar dados';

    public function handle(): int
    {
        $targets = [
            ['Administração', 'users', 'phone'],
            ['Transporte', 'drivers', 'phone'],
            ['Help Desk', 'it_tickets', 'requester_phone'],
        ];
        $rows = [];
        $invalidTotal = 0;

        foreach ($targets as [$area, $table, $column]) {
            $values = DB::table($table)
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->pluck($column);
            $invalid = $values->reject(
                static fn (mixed $value): bool => BrazilianPhone::isValid($value),
            )->count();
            $needsNormalization = $values->filter(
                static fn (mixed $value): bool => BrazilianPhone::isValid($value)
                    && BrazilianPhone::normalize($value) !== (string) $value,
            )->count();

            $invalidTotal += $invalid;
            $rows[] = [$area, $table.'.'.$column, $values->count(), $invalid, $needsNormalization];
        }

        $this->table(
            ['Área', 'Campo', 'Preenchidos', 'Inválidos', 'A normalizar'],
            $rows,
        );
        $this->line('Nenhum dado foi exibido ou alterado.');

        if ($invalidTotal > 0) {
            $this->warn("Foram encontrados {$invalidTotal} telefone(s) inválido(s). Corrija-os antes de criar constraints de banco.");

            return $this->option('fail-on-invalid') ? self::FAILURE : self::SUCCESS;
        }

        $this->info('Todos os telefones preenchidos atendem à regra brasileira.');

        return self::SUCCESS;
    }
}
