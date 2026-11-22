<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Renavam;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TransportIdentifierValidationTest extends TestCase
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

    public function test_official_renavam_examples_are_accepted(): void
    {
        foreach (['01012655145', '00600052230', '11616434907'] as $renavam) {
            $this->assertTrue(Renavam::isValid($renavam), $renavam);
            $this->assertSame($renavam, Renavam::normalize($renavam));
        }
    }

    public function test_renavam_rejects_wrong_length_characters_and_check_digit(): void
    {
        foreach (['1012655145', '001012655145', '0101265514A', '01012655144'] as $renavam) {
            $this->assertFalse(Renavam::isValid($renavam), $renavam);
        }
    }

    public function test_vehicle_api_accepts_valid_renavam_and_rejects_invalid_value_without_writing(): void
    {
        $vehicleId = $this->postJson('/api/transport/vehicles', [
            'brand' => 'Fiat',
            'model' => 'Cronos',
            'plate' => 'REN1A23',
            'renavam' => '01012655145',
            'status' => 'disponivel',
        ])->assertCreated()
            ->assertJsonPath('renavam', '01012655145')
            ->json('id');

        $this->putJson('/api/transport/vehicles/'.$vehicleId, [
            'brand' => 'Fiat',
            'model' => 'Cronos',
            'plate' => 'REN1A23',
            'renavam' => '01012655144',
            'status' => 'disponivel',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('renavam');

        $this->assertSame('01012655145', Vehicle::query()->findOrFail($vehicleId)->renavam);
    }

    public function test_transport_identifier_audit_is_read_only_and_flags_ambiguous_cnh(): void
    {
        $driver = Driver::create([
            'name' => 'Motorista Legado',
            'cnh' => 'AM-REGISTRO-LEGADO',
            'cnh_category' => 'B',
            'cnh_expires_at' => now()->addYear()->toDateString(),
            'status' => 'ativo',
        ]);

        $this->artisan('siges:audit-transport-identifiers', ['--fail-on-issues' => true])
            ->expectsOutputToContain('Nenhum identificador foi exibido ou alterado.')
            ->expectsOutputToContain('Defina institucionalmente qual deles o campo representa')
            ->assertFailed();

        $this->assertSame('AM-REGISTRO-LEGADO', $driver->fresh()->cnh);
    }
}
