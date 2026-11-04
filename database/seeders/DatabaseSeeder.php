<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(DepartmentSeeder::class);
        $this->call(RolesAndPermissionsSeeder::class);
        $this->call(SystemSettingSeeder::class);
        $this->call(ItTicketCategorySeeder::class);

        $user = User::query()->updateOrCreate(
            ['email' => 'admin@siges.local'],
            [
                'name' => 'Administrador SIGES',
                'password' => 'password',
                'department_id' => \App\Models\Department::query()->where('slug', 'departamento-administrativo')->value('id'),
                'status' => 'ativo',
            ]
        );

        $user->assignRole('Administrador Geral');

        $technician = User::query()->updateOrCreate(
            ['email' => 'tecnico@siges.local'],
            [
                'name' => 'Tecnico Informatica',
                'password' => 'password',
                'department_id' => \App\Models\Department::query()->where('slug', 'gerencia-de-informatica')->value('id'),
                'position' => 'Tecnico de suporte',
                'status' => 'ativo',
            ]
        );

        $technician->syncRoles(['Servidor Informatica']);

        $this->call(ItSupportTeamSeeder::class);
    }
}
