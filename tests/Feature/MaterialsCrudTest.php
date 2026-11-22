<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Department;
use App\Models\MaterialCategory;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MaterialsCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_product_crud_supports_search_update_and_soft_delete(): void
    {
        $this->authenticateAs('Gerente Materiais e Patrimonio');
        $category = MaterialCategory::create(['name' => 'Expediente', 'slug' => 'expediente']);

        $product = $this->postJson('/api/materials/products', [
            'category_id' => $category->id,
            'internal_code' => 'MAT-001',
            'description' => 'Papel A4',
            'unit' => 'resma',
            'supplier' => 'Fornecedor Teste',
            'quantity' => 10,
            'minimum_quantity' => 2,
            'location' => 'Prateleira A',
            'status' => 'ativo',
        ])->assertCreated()
            ->assertJsonPath('category.id', $category->id)
            ->json();

        $this->getJson('/api/materials/products?search=MAT-001')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $product['id']);

        $this->putJson('/api/materials/products/'.$product['id'], [
            'category_id' => $category->id,
            'internal_code' => 'MAT-001',
            'description' => 'Papel A4 reciclado',
            'unit' => 'resma',
            'quantity' => 12,
            'minimum_quantity' => 3,
            'status' => 'inativo',
        ])->assertOk()
            ->assertJsonPath('description', 'Papel A4 reciclado')
            ->assertJsonPath('status', 'inativo');

        $this->deleteJson('/api/materials/products/'.$product['id'])->assertOk();
        $this->assertSoftDeleted('products', ['id' => $product['id']]);
    }

    public function test_product_validation_and_action_permissions_are_enforced(): void
    {
        $this->authenticateAs('Gerente Materiais e Patrimonio');
        Product::create($this->productData(['internal_code' => 'EXISTING']));

        $this->postJson('/api/materials/products', [
            'internal_code' => 'EXISTING',
            'description' => '',
            'unit' => '',
            'quantity' => -1,
            'minimum_quantity' => -2,
            'status' => 'desconhecido',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'internal_code',
                'description',
                'unit',
                'quantity',
                'minimum_quantity',
                'status',
            ]);

        $viewer = $this->authenticateAs('Consulta Materiais e Patrimonio');
        $product = Product::query()->where('internal_code', 'EXISTING')->firstOrFail();
        $this->getJson('/api/materials/products')->assertOk();
        $this->postJson('/api/materials/products', $this->productData())->assertForbidden();
        $this->putJson('/api/materials/products/'.$product->id, $this->productData())->assertForbidden();
        $this->deleteJson('/api/materials/products/'.$product->id)->assertForbidden();

        $viewer->syncRoles(['Consulta']);
        $this->getJson('/api/materials/products')->assertForbidden();
    }

    public function test_stock_entries_and_exits_are_transactional_and_validate_balance(): void
    {
        $department = Department::create(['name' => 'Almoxarifado', 'slug' => 'almoxarifado-mat']);
        $this->authenticateAs('Gerente Materiais e Patrimonio', $department);
        $product = Product::create($this->productData(['quantity' => 10]));

        $this->postJson('/api/materials/stock-movements', $this->stockMovementData($product, $department, [
            'type' => 'entrada',
            'quantity' => 5,
        ]))->assertCreated();
        $this->assertSame('15.00', $product->fresh()->quantity);

        $this->postJson('/api/materials/stock-movements', $this->stockMovementData($product, $department, [
            'type' => 'saida',
            'quantity' => 4,
        ]))->assertCreated();
        $this->assertSame('11.00', $product->fresh()->quantity);

        $this->postJson('/api/materials/stock-movements', $this->stockMovementData($product, $department, [
            'type' => 'saida',
            'quantity' => 20,
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');

        $this->assertSame('11.00', $product->fresh()->quantity);
        $this->assertDatabaseCount('stock_movements', 2);
    }

    public function test_stock_movement_scope_forces_and_filters_user_department(): void
    {
        $ownDepartment = Department::create(['name' => 'Setor A', 'slug' => 'stock-a']);
        $otherDepartment = Department::create(['name' => 'Setor B', 'slug' => 'stock-b']);
        $user = $this->authenticateAs('Servidor Materiais e Patrimonio', $ownDepartment);
        $product = Product::create($this->productData(['quantity' => 20]));

        $movement = $this->postJson('/api/materials/stock-movements', $this->stockMovementData($product, $otherDepartment))
            ->assertCreated()
            ->assertJsonPath('department.id', $ownDepartment->id)
            ->json();

        $otherUser = User::factory()->create(['department_id' => $otherDepartment->id]);
        $otherMovement = $product->movements()->create([
            'department_id' => $otherDepartment->id,
            'responsible_id' => $otherUser->id,
            'type' => 'entrada',
            'quantity' => 1,
            'movement_date' => '2026-11-13',
        ]);

        $response = $this->getJson('/api/materials/stock-movements')->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($movement['id']));
        $this->assertFalse($ids->contains($otherMovement->id));
        $this->assertSame($user->id, $movement['responsible']['id']);
    }

    public function test_asset_crud_supports_details_update_and_soft_delete(): void
    {
        $department = Department::create(['name' => 'Tecnologia', 'slug' => 'tecnologia-assets']);
        $responsible = User::factory()->create(['department_id' => $department->id]);
        $this->authenticateAs('Gerente Materiais e Patrimonio');

        $asset = $this->postJson('/api/materials/assets', $this->assetData($department, [
            'responsible_id' => $responsible->id,
        ]))->assertCreated()
            ->assertJsonPath('department.id', $department->id)
            ->json();

        $this->getJson('/api/materials/assets/'.$asset['id'])
            ->assertOk()
            ->assertJsonPath('asset_number', 'PAT-001');

        $this->putJson('/api/materials/assets/'.$asset['id'], $this->assetData($department, [
            'description' => 'Notebook atualizado',
            'status' => 'manutencao',
        ]))->assertOk()
            ->assertJsonPath('description', 'Notebook atualizado')
            ->assertJsonPath('status', 'manutencao');

        $this->deleteJson('/api/materials/assets/'.$asset['id'])->assertOk();
        $this->assertSoftDeleted('assets', ['id' => $asset['id']]);
    }

    public function test_asset_validation_rejects_duplicate_and_invalid_relations_values_and_status(): void
    {
        $department = Department::create(['name' => 'Validacao', 'slug' => 'asset-validation']);
        $this->authenticateAs('Gerente Materiais e Patrimonio');
        Asset::create($this->assetData($department));

        $this->postJson('/api/materials/assets', [
            'department_id' => 999999,
            'responsible_id' => 999999,
            'asset_number' => 'PAT-001',
            'description' => '',
            'acquired_at' => 'data-invalida',
            'value' => -1,
            'status' => 'desconhecido',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'department_id',
                'responsible_id',
                'asset_number',
                'description',
                'acquired_at',
                'value',
                'status',
            ]);
    }

    public function test_asset_value_persists_exact_decimal_and_rejects_excess_precision_or_range(): void
    {
        $department = Department::create(['name' => 'Financeiro', 'slug' => 'financeiro']);
        $this->authenticateAs('Gerente Materiais e Patrimonio', $department);

        $assetId = $this->postJson('/api/materials/assets', $this->assetData($department, [
            'asset_number' => 'PAT-MONEY-001',
            'value' => '1234.56',
        ]))->assertCreated()
            ->assertJsonPath('value', '1234.56')
            ->json('id');

        $this->assertSame('1234.56', Asset::query()->findOrFail($assetId)->value);

        foreach (['1.001', '10000000000.00'] as $value) {
            $this->postJson('/api/materials/assets', $this->assetData($department, [
                'asset_number' => 'PAT-MONEY-'.md5($value),
                'value' => $value,
            ]))->assertUnprocessable()
                ->assertJsonValidationErrors('value');
        }
    }

    public function test_asset_access_is_scoped_to_department_for_non_managers(): void
    {
        $ownDepartment = Department::create(['name' => 'Setor Proprio', 'slug' => 'asset-own']);
        $otherDepartment = Department::create(['name' => 'Outro Setor', 'slug' => 'asset-other']);
        $this->authenticateAs('Servidor Materiais e Patrimonio', $ownDepartment);

        $ownAsset = $this->postJson('/api/materials/assets', $this->assetData($otherDepartment))
            ->assertCreated()
            ->assertJsonPath('department.id', $ownDepartment->id)
            ->json();
        $otherAsset = Asset::create($this->assetData($otherDepartment, ['asset_number' => 'PAT-OTHER']));

        $response = $this->getJson('/api/materials/assets')->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($ownAsset['id']));
        $this->assertFalse($ids->contains($otherAsset->id));

        $this->getJson('/api/materials/assets/'.$otherAsset->id)->assertForbidden();
        $this->putJson('/api/materials/assets/'.$otherAsset->id, $this->assetData($otherDepartment, [
            'asset_number' => 'PAT-OTHER',
        ]))->assertForbidden();
        $this->deleteJson('/api/materials/assets/'.$ownAsset['id'])->assertForbidden();

        $options = $this->getJson('/api/materials/options')->assertOk();
        $this->assertSame([$ownDepartment->id], collect($options->json('departments'))->pluck('id')->all());
        $this->assertFalse(collect($options->json('assets'))->pluck('id')->contains($otherAsset->id));
    }

    public function test_asset_movements_update_lifecycle_and_require_transfer_destination(): void
    {
        $from = Department::create(['name' => 'Origem', 'slug' => 'movement-origin']);
        $to = Department::create(['name' => 'Destino', 'slug' => 'movement-destination']);
        $this->authenticateAs('Gerente Materiais e Patrimonio');
        $asset = Asset::create($this->assetData($from));

        $this->postJson('/api/materials/asset-movements', $this->assetMovementData($asset, 'transferencia'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('to_department_id');

        $this->postJson('/api/materials/asset-movements', $this->assetMovementData($asset, 'manutencao'))
            ->assertCreated();
        $this->assertSame('manutencao', $asset->fresh()->status);

        $this->postJson('/api/materials/asset-movements', $this->assetMovementData($asset, 'retorno'))
            ->assertCreated();
        $this->assertSame('ativo', $asset->fresh()->status);

        $this->postJson('/api/materials/asset-movements', $this->assetMovementData($asset, 'transferencia', [
            'to_department_id' => $to->id,
        ]))->assertCreated()
            ->assertJsonPath('from_department.id', $from->id)
            ->assertJsonPath('to_department.id', $to->id);
        $this->assertSame($to->id, $asset->fresh()->department_id);

        $this->postJson('/api/materials/asset-movements', $this->assetMovementData($asset, 'baixa'))
            ->assertCreated();
        $this->assertSame('baixado', $asset->fresh()->status);
        $this->assertDatabaseCount('asset_movements', 4);
    }

    public function test_asset_movement_permissions_and_department_scope_are_enforced(): void
    {
        $ownDepartment = Department::create(['name' => 'Movimento Proprio', 'slug' => 'movement-own']);
        $otherDepartment = Department::create(['name' => 'Movimento Outro', 'slug' => 'movement-other']);
        $this->authenticateAs('Servidor Materiais e Patrimonio', $ownDepartment);
        $ownAsset = Asset::create($this->assetData($ownDepartment));
        $otherAsset = Asset::create($this->assetData($otherDepartment, ['asset_number' => 'PAT-MOV-OTHER']));

        $ownMovement = $this->postJson('/api/materials/asset-movements', $this->assetMovementData($ownAsset, 'manutencao'))
            ->assertCreated()
            ->json();
        $this->postJson('/api/materials/asset-movements', $this->assetMovementData($otherAsset, 'manutencao'))
            ->assertForbidden();

        $response = $this->getJson('/api/materials/asset-movements')->assertOk();
        $this->assertSame([$ownMovement['id']], collect($response->json('data'))->pluck('id')->all());

        $this->authenticateAs('Consulta Materiais e Patrimonio', $ownDepartment);
        $this->postJson('/api/materials/asset-movements', $this->assetMovementData($ownAsset, 'retorno'))
            ->assertForbidden();
    }

    private function authenticateAs(string $role, ?Department $department = null): User
    {
        $user = User::factory()->create(['department_id' => $department?->id]);
        $user->assignRole($role);
        Sanctum::actingAs($user);

        return $user;
    }

    private function productData(array $overrides = []): array
    {
        return [
            'internal_code' => 'MAT-TEST',
            'description' => 'Material de teste',
            'unit' => 'unidade',
            'quantity' => 0,
            'minimum_quantity' => 0,
            'status' => 'ativo',
            ...$overrides,
        ];
    }

    private function stockMovementData(Product $product, Department $department, array $overrides = []): array
    {
        return [
            'product_id' => $product->id,
            'department_id' => $department->id,
            'type' => 'entrada',
            'quantity' => 1,
            'movement_date' => '2026-11-13',
            ...$overrides,
        ];
    }

    private function assetData(Department $department, array $overrides = []): array
    {
        return [
            'department_id' => $department->id,
            'asset_number' => 'PAT-001',
            'description' => 'Notebook de teste',
            'category' => 'Informatica',
            'value' => 3500,
            'status' => 'ativo',
            ...$overrides,
        ];
    }

    private function assetMovementData(Asset $asset, string $type, array $overrides = []): array
    {
        return [
            'asset_id' => $asset->id,
            'type' => $type,
            'moved_at' => '2026-11-13 10:00:00',
            ...$overrides,
        ];
    }
}
