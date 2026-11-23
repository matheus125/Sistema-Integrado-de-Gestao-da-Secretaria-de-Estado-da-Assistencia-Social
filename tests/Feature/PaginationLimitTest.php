<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaginationLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Administrador Geral');
        Sanctum::actingAs($user);
    }

    public function test_boundary_is_accepted_and_default_pagination_is_preserved(): void
    {
        User::factory()->count(105)->create();

        $this->getJson('/api/admin/users?per_page=100')
            ->assertOk()
            ->assertJsonPath('per_page', 100)
            ->assertJsonCount(100, 'data');

        $this->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonPath('per_page', 15)
            ->assertJsonCount(15, 'data');
    }

    public function test_invalid_and_extreme_values_return_consistent_validation_error(): void
    {
        $values = ['', '0', '-1', '1.5', '1e2', 'texto', '101', '999999999999999999999'];

        foreach ($values as $value) {
            $this->getJson('/api/admin/users?per_page='.urlencode($value))
                ->assertUnprocessable()
                ->assertJsonPath('message', 'A quantidade de registros por página é inválida.')
                ->assertJsonStructure(['errors' => ['per_page']]);
        }

        $this->getJson('/api/admin/users?per_page[]=10')
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['per_page']]);
    }

    public function test_limit_is_enforced_before_all_paginated_controllers(): void
    {
        $endpoints = [
            '/api/admin/users',
            '/api/admin/departments',
            '/api/transport/vehicles',
            '/api/transport/drivers',
            '/api/transport/requests',
            '/api/transport/maintenances',
            '/api/materials/products',
            '/api/materials/stock-movements',
            '/api/materials/assets',
            '/api/materials/asset-movements',
            '/api/it/knowledge',
            '/api/it/preventive-maintenance',
            '/api/it/approvals',
            '/api/it/sla-rules',
            '/api/it/categories',
            '/api/it/tickets',
        ];

        foreach ($endpoints as $endpoint) {
            $this->getJson("{$endpoint}?per_page=101")
                ->assertUnprocessable()
                ->assertJsonPath('errors.per_page.0', 'A quantidade por página não pode ser maior que 100.');
        }
    }

    public function test_configured_limit_is_used_globally(): void
    {
        config()->set('pagination.max_per_page', 25);

        $this->getJson('/api/admin/users?per_page=25')
            ->assertOk()
            ->assertJsonPath('per_page', 25);

        $this->getJson('/api/it/tickets?per_page=26')
            ->assertUnprocessable()
            ->assertJsonPath('errors.per_page.0', 'A quantidade por página não pode ser maior que 25.');
    }

    public function test_public_routes_keep_their_original_middleware_contract(): void
    {
        auth('sanctum')->forgetUser();
        auth()->forgetGuards();

        $this->getJson('/api/auth/me?per_page=999999')
            ->assertUnauthorized();
        $this->getJson('/api/webhooks/whatsapp?per_page=999999')
            ->assertServiceUnavailable();
    }
}
