<?php

namespace App\Console\Commands;

use App\Support\BrazilianVehiclePlate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditVehiclePlateData extends Command
{
    protected $signature = 'siges:audit-plates
        {--fail-on-issues : Retornar código de falha quando houver placa inválida ou duplicada}';

    protected $description = 'Audita placas legadas sem exibir valores ou alterar dados';

    public function handle(): int
    {
        $plates = DB::table('vehicles')
            ->whereNotNull('plate')
            ->where('plate', '!=', '')
            ->pluck('plate');
        $invalid = $plates->reject(
            static fn (mixed $plate): bool => BrazilianVehiclePlate::isValid($plate),
        )->count();
        $needsNormalization = $plates->filter(
            static fn (mixed $plate): bool => BrazilianVehiclePlate::isValid($plate)
                && BrazilianVehiclePlate::normalize($plate) !== (string) $plate,
        )->count();
        $duplicateGroups = $plates
            ->map(static fn (mixed $plate): ?string => BrazilianVehiclePlate::normalize($plate))
            ->filter()
            ->countBy()
            ->filter(static fn (int $count): bool => $count > 1)
            ->count();

        $this->table(
            ['Preenchidas', 'Inválidas', 'A normalizar', 'Grupos duplicados'],
            [[$plates->count(), $invalid, $needsNormalization, $duplicateGroups]],
        );
        $this->line('Nenhuma placa foi exibida ou alterada.');

        if ($invalid > 0 || $duplicateGroups > 0) {
            $this->warn("Foram encontrados {$invalid} registro(s) inválido(s) e {$duplicateGroups} grupo(s) duplicado(s). Corrija-os antes de criar novas constraints.");

            return $this->option('fail-on-issues') ? self::FAILURE : self::SUCCESS;
        }

        $this->info('Todas as placas preenchidas possuem formato válido e unicidade canônica.');

        return self::SUCCESS;
    }
}
