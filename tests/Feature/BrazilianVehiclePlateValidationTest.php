<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use App\Support\BrazilianVehiclePlate;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BrazilianVehiclePlateValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $administrator = User::factory()->create();
        $administrator->assignRole('Administrador Geral');
        Sanctum::actingAs($administrator);
    }

    public function test_old_and_mercosur_plate_formats_are_normalized(): void
    {
        $validPlates = [
            ['ABC-1234', 'ABC-1234'],
            ['abc1234', 'ABC-1234'],
            ['abc 1234', 'ABC-1234'],
            ['ABC1D23', 'ABC1D23'],
            ['abc-1d23', 'ABC1D23'],
        ];

        foreach ($validPlates as [$input, $normalized]) {
            $this->assertTrue(BrazilianVehiclePlate::isValid($input), $input);
            $this->assertSame($normalized, BrazilianVehiclePlate::normalize($input));
        }
    }

    public function test_invalid_plate_formats_are_rejected(): void
    {
        foreach (['x', 'AB-1234', 'ABCD-1234', 'ABC-123', 'ABC-12345', 'ABC12D3', '123-ABCD', 'ABC@1234'] as $plate) {
            $this->assertFalse(BrazilianVehiclePlate::isValid($plate), $plate);
        }
    }

    public function test_vehicle_api_persists_canonical_old_and_mercosur_plates(): void
    {
        $oldId = $this->createVehicle('abc1234')
            ->assertCreated()
            ->assertJsonPath('plate', 'ABC-1234')
            ->json('id');
        $mercosurId = $this->createVehicle('def-1g23')
            ->assertCreated()
            ->assertJsonPath('plate', 'DEF1G23')
            ->json('id');

        $this->assertDatabaseHas('vehicles', ['id' => $oldId, 'plate' => 'ABC-1234']);
        $this->assertDatabaseHas('vehicles', ['id' => $mercosurId, 'plate' => 'DEF1G23']);

        $this->getJson('/api/transport/vehicles?search=abc1234')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $oldId);
    }

    public function test_case_and_format_variations_cannot_create_duplicates(): void
    {
        $this->createVehicle('abc1234')->assertCreated();
        $this->createVehicle('ABC-1234')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('plate');

        Vehicle::create([
            'brand' => 'Ford',
            'model' => 'Ka',
            'plate' => 'ghi2j34',
            'status' => 'disponivel',
        ]);

        $this->createVehicle('GHI-2J34')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('plate');

        $this->assertDatabaseCount('vehicles', 2);
    }

    public function test_invalid_update_does_not_change_existing_plate(): void
    {
        $vehicleId = $this->createVehicle('JKL-4321')->assertCreated()->json('id');

        $this->putJson('/api/transport/vehicles/'.$vehicleId, [
            'brand' => 'Fiat',
            'model' => 'Cronos',
            'plate' => 'x',
            'status' => 'disponivel',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('plate');

        $this->assertSame('JKL-4321', Vehicle::query()->findOrFail($vehicleId)->plate);
    }

    public function test_legacy_audit_counts_invalid_normalization_and_duplicates_without_changes(): void
    {
        Vehicle::create(['brand' => 'A', 'model' => 'A', 'plate' => 'abc1234', 'status' => 'disponivel']);
        Vehicle::create(['brand' => 'B', 'model' => 'B', 'plate' => 'ABC-1234', 'status' => 'disponivel']);
        Vehicle::create(['brand' => 'C', 'model' => 'C', 'plate' => 'x', 'status' => 'disponivel']);

        $this->artisan('siges:audit-plates', ['--fail-on-issues' => true])
            ->expectsOutputToContain('Nenhuma placa foi exibida ou alterada.')
            ->expectsOutputToContain('Foram encontrados 1 registro(s) inválido(s) e 1 grupo(s) duplicado(s).')
            ->assertFailed();

        $this->assertDatabaseHas('vehicles', ['plate' => 'abc1234']);
        $this->assertDatabaseHas('vehicles', ['plate' => 'ABC-1234']);
        $this->assertDatabaseHas('vehicles', ['plate' => 'x']);
    }

    private function createVehicle(string $plate)
    {
        return $this->postJson('/api/transport/vehicles', [
            'brand' => 'Fiat',
            'model' => 'Cronos',
            'plate' => $plate,
            'status' => 'disponivel',
        ]);
    }
}
