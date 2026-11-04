<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $modules = array_keys(config('siges.modules'));
        $actions = config('siges.actions');

        foreach ($modules as $module) {
            foreach ($actions as $action) {
                Permission::firstOrCreate(['name' => "{$module}.{$action}", 'guard_name' => 'web']);
            }
        }

        foreach (['visualizar', 'cadastrar', 'editar', 'excluir'] as $action) {
            Permission::firstOrCreate(['name' => "informatica.categorias.{$action}", 'guard_name' => 'web']);
        }

        $admin = Role::firstOrCreate(['name' => 'Administrador Geral', 'guard_name' => 'web']);

        $admin->syncPermissions(Permission::all());

        Role::firstOrCreate(['name' => 'Gerente', 'guard_name' => 'web'])
            ->syncPermissions($this->permissionModels(['dashboard.visualizar', 'relatorios.visualizar']));
        Role::firstOrCreate(['name' => 'Supervisor', 'guard_name' => 'web'])
            ->syncPermissions($this->permissionModels(['dashboard.visualizar']));
        Role::firstOrCreate(['name' => 'Servidor', 'guard_name' => 'web'])
            ->syncPermissions($this->permissionModels(['dashboard.visualizar']));
        Role::firstOrCreate(['name' => 'Consulta', 'guard_name' => 'web'])
            ->syncPermissions($this->permissionModels(['dashboard.visualizar']));

        $this->syncModuleProfiles('Transporte', 'transporte');
        $this->syncModuleProfiles('Materiais e Patrimonio', 'materiais-patrimonio');
        $this->syncModuleProfiles('Informatica', 'informatica');
        $this->syncModuleProfiles('Administracao', 'administracao');
    }

    /**
     * @return array<int, string>
     */
    private function permissionsFor(array $actions): array
    {
        $permissions = [];

        foreach (array_keys(config('siges.modules')) as $module) {
            foreach ($actions as $action) {
                $permissions[] = "{$module}.{$action}";
            }
        }

        return $permissions;
    }

    private function syncModuleProfiles(string $label, string $module): void
    {
        $base = ['dashboard.visualizar'];
        $reports = ['relatorios.visualizar', 'relatorios.exportar', 'relatorios.emitir-pdf'];

        Role::firstOrCreate(['name' => "Gerente {$label}", 'guard_name' => 'web'])
            ->syncPermissions($this->permissionModels([
                ...$base,
                ...$reports,
                ...$this->modulePermissions($module, ['visualizar', 'cadastrar', 'editar', 'excluir', 'aprovar', 'cancelar', 'exportar', 'emitir-pdf']),
                ...$this->categoryPermissions($module, ['visualizar', 'cadastrar', 'editar', 'excluir']),
            ]));

        Role::firstOrCreate(['name' => "Supervisor {$label}", 'guard_name' => 'web'])
            ->syncPermissions($this->permissionModels([
                ...$base,
                ...$this->modulePermissions($module, ['visualizar', 'cadastrar', 'editar', 'exportar', 'emitir-pdf']),
                ...$this->categoryPermissions($module, ['visualizar', 'cadastrar', 'editar']),
            ]));

        Role::firstOrCreate(['name' => "Servidor {$label}", 'guard_name' => 'web'])
            ->syncPermissions($this->permissionModels([
                ...$base,
                ...$this->modulePermissions($module, ['visualizar', 'cadastrar']),
                ...$this->categoryPermissions($module, ['visualizar']),
            ]));

        Role::firstOrCreate(['name' => "Consulta {$label}", 'guard_name' => 'web'])
            ->syncPermissions($this->permissionModels([
                ...$base,
                ...$this->modulePermissions($module, ['visualizar', 'exportar', 'emitir-pdf']),
                ...$this->categoryPermissions($module, ['visualizar']),
            ]));
    }

    /**
     * @return array<int, string>
     */
    private function modulePermissions(string $module, array $actions): array
    {
        return array_map(fn (string $action) => "{$module}.{$action}", $actions);
    }

    /**
     * @return array<int, string>
     */
    private function categoryPermissions(string $module, array $actions): array
    {
        if ($module !== 'informatica') {
            return [];
        }

        return array_map(fn (string $action) => "informatica.categorias.{$action}", $actions);
    }

    private function permissionModels(array $names)
    {
        return Permission::query()->whereIn('name', $names)->get();
    }
}
