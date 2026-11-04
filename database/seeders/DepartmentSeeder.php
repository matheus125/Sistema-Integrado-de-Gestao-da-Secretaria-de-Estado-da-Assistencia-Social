<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        collect([
            'Gabinete',
            'Departamento Administrativo',
            'Gerencia de Transporte',
            'Gerencia de Materiais e Patrimonio',
            'Gerencia de Informatica',
            'Gerencia de Protecao Social',
        ])->each(function (string $name): void {
            Department::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'active' => true]
            );
        });
    }
}
