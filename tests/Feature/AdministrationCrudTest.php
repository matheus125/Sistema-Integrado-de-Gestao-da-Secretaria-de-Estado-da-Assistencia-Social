<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdministrationCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_administrator_can_create_list_update_and_delete_user(): void
    {
        $administrator = $this->authenticateAs('Administrador Geral');
        $department = Department::create(['name' => 'Financeiro', 'slug' => 'financeiro']);

        $response = $this->postJson('/api/admin/users', [
            'name' => 'Usuario CRUD',
            'email' => 'crud@example.test',
            'password' => 'password-secure',
            'department_id' => $department->id,
            'position' => 'Analista',
            'status' => 'ativo',
            'roles' => ['Consulta'],
        ])->assertCreated()
            ->assertJsonPath('email', 'crud@example.test')
            ->assertJsonPath('department.id', $department->id)
            ->assertJsonPath('roles.0.name', 'Consulta');

        $userId = $response->json('id');
        $created = User::query()->findOrFail($userId);
        $this->assertTrue(Hash::check('password-secure', $created->password));

        $this->getJson('/api/admin/users?search=crud@example.test')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $userId);

        $this->putJson('/api/admin/users/'.$userId, [
            'name' => 'Usuario Atualizado',
            'email' => 'updated@example.test',
            'department_id' => $department->id,
            'position' => 'Coordenador',
            'status' => 'afastado',
            'roles' => ['Servidor'],
        ])->assertOk()
            ->assertJsonPath('name', 'Usuario Atualizado')
            ->assertJsonPath('roles.0.name', 'Servidor');

        $this->deleteJson('/api/admin/users/'.$userId)
            ->assertOk()
            ->assertJsonPath('message', 'Usuario removido.');

        $this->assertDatabaseMissing('users', ['id' => $userId]);
        $this->deleteJson('/api/admin/users/'.$administrator->id)->assertUnprocessable();
    }

    public function test_user_validation_rejects_duplicate_invalid_and_incomplete_data(): void
    {
        $this->authenticateAs('Administrador Geral');
        $existing = User::factory()->create(['email' => 'existing@example.test']);

        $this->postJson('/api/admin/users', [
            'name' => '',
            'email' => $existing->email,
            'password' => 'short',
            'department_id' => 999999,
            'status' => 'bloqueado',
            'roles' => ['Perfil inexistente'],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'email',
                'password',
                'department_id',
                'status',
                'roles.0',
            ]);
    }

    public function test_department_crud_and_dependency_protection_work(): void
    {
        $this->authenticateAs('Administrador Geral');

        $parent = $this->postJson('/api/admin/departments', [
            'name' => 'Administracao',
            'active' => true,
        ])->assertCreated()
            ->assertJsonPath('slug', 'administracao')
            ->json();

        $child = $this->postJson('/api/admin/departments', [
            'parent_id' => $parent['id'],
            'name' => 'Compras',
            'active' => true,
        ])->assertCreated()->json();

        $this->getJson('/api/admin/departments?search=Admin')
            ->assertOk()
            ->assertJsonPath('data.0.id', $parent['id'])
            ->assertJsonPath('data.0.children_count', 1);

        $this->putJson('/api/admin/departments/'.$child['id'], [
            'parent_id' => $parent['id'],
            'name' => 'Compras e Contratos',
            'active' => false,
        ])->assertOk()
            ->assertJsonPath('active', false);

        $this->deleteJson('/api/admin/departments/'.$parent['id'])
            ->assertUnprocessable();

        User::factory()->create(['department_id' => $child['id']]);
        $this->deleteJson('/api/admin/departments/'.$child['id'])
            ->assertUnprocessable();

        $removable = Department::create(['name' => 'Temporario', 'slug' => 'temporario']);
        $this->deleteJson('/api/admin/departments/'.$removable->id)->assertOk();
        $this->assertDatabaseMissing('departments', ['id' => $removable->id]);
    }

    public function test_department_hierarchy_rejects_self_parent_and_cycles(): void
    {
        $this->authenticateAs('Administrador Geral');
        $parent = Department::create(['name' => 'Pai', 'slug' => 'pai']);
        $child = Department::create(['name' => 'Filho', 'slug' => 'filho', 'parent_id' => $parent->id]);

        $this->putJson('/api/admin/departments/'.$parent->id, [
            'parent_id' => $parent->id,
            'name' => $parent->name,
            'active' => true,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('parent_id');

        $this->putJson('/api/admin/departments/'.$parent->id, [
            'parent_id' => $child->id,
            'name' => $parent->name,
            'active' => true,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('parent_id');
    }

    public function test_role_crud_synchronizes_permissions_and_protects_standard_roles(): void
    {
        $this->authenticateAs('Administrador Geral');
        $viewPermission = Permission::query()->where('name', 'administracao.visualizar')->firstOrFail();
        $editPermission = Permission::query()->where('name', 'administracao.editar')->firstOrFail();

        $role = $this->postJson('/api/admin/roles', [
            'name' => 'Auditor Administrativo',
            'permissions' => [$viewPermission->name],
        ])->assertCreated()
            ->assertJsonPath('permissions.0.name', $viewPermission->name)
            ->json();

        $this->getJson('/api/admin/roles')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Auditor Administrativo']);

        $this->putJson('/api/admin/roles/'.$role['id'], [
            'name' => 'Auditor Administrativo Senior',
            'permissions' => [$viewPermission->name, $editPermission->name],
        ])->assertOk()
            ->assertJsonCount(2, 'permissions');

        $this->postJson('/api/admin/roles', [
            'name' => 'Consulta',
            'permissions' => ['permissao.inexistente'],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'permissions.0']);

        $this->deleteJson('/api/admin/roles/'.$role['id'])->assertOk();
        $this->assertDatabaseMissing('roles', ['id' => $role['id']]);

        $standardRole = Role::query()->where('name', 'Consulta')->firstOrFail();
        $this->deleteJson('/api/admin/roles/'.$standardRole->id)
            ->assertUnprocessable();
    }

    public function test_administration_permissions_are_enforced_per_action(): void
    {
        $viewer = $this->authenticateAs('Consulta Administracao');
        $target = User::factory()->create();
        $department = Department::create(['name' => 'Almoxarifado', 'slug' => 'almoxarifado']);

        $this->getJson('/api/admin/users')->assertOk();
        $this->getJson('/api/admin/options')->assertOk();
        $this->postJson('/api/admin/departments', [
            'name' => 'Sem permissao',
            'active' => true,
        ])->assertForbidden();
        $this->putJson('/api/admin/users/'.$target->id, [
            'name' => $target->name,
            'email' => $target->email,
            'status' => 'ativo',
        ])->assertForbidden();
        $this->deleteJson('/api/admin/departments/'.$department->id)->assertForbidden();

        $viewer->syncRoles(['Supervisor Administracao']);
        $administratorRole = Role::query()->where('name', 'Administrador Geral')->firstOrFail();
        $this->putJson('/api/admin/roles/'.$administratorRole->id, [
            'name' => 'Administrador Geral',
            'permissions' => [],
        ])->assertForbidden();
        $this->deleteJson('/api/admin/departments/'.$department->id)->assertForbidden();

        $viewer->syncRoles(['Consulta']);
        $this->getJson('/api/admin/users')->assertForbidden();
    }

    private function authenticateAs(string $role, ?Department $department = null): User
    {
        $user = User::factory()->create(['department_id' => $department?->id]);
        $user->assignRole($role);
        Sanctum::actingAs($user);

        return $user;
    }
}
