<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TransportLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_departure_and_return_checklists_close_trip_with_auditable_history(): void
    {
        [$user, $vehicleRequest, $vehicle] = $this->approvedRequest();

        Sanctum::actingAs($user);

        $this->postJson("/api/transport/requests/{$vehicleRequest->id}/departure-checklist", [
            'fuel_level' => 75,
            'km' => 1000,
            'documents_ok' => true,
            'tires_ok' => true,
            'lights_ok' => true,
            'bodywork_ok' => true,
            'tools_ok' => true,
            'has_damage' => false,
            'observations' => 'Saida conferida.',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'em_deslocamento')
            ->assertJsonPath('initial_km', 1000)
            ->assertJsonPath('checklists.0.type', 'saida');

        $this->assertSame('em_uso', $vehicle->fresh()->status);

        $this->postJson("/api/transport/requests/{$vehicleRequest->id}/return-checklist", [
            'fuel_level' => 40,
            'km' => 1088,
            'documents_ok' => true,
            'tires_ok' => true,
            'lights_ok' => true,
            'bodywork_ok' => false,
            'tools_ok' => true,
            'has_damage' => true,
            'damages_description' => 'Risco leve no para-choque traseiro.',
            'observations' => 'Retorno registrado com avaria.',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'concluida')
            ->assertJsonPath('final_km', 1088);

        $vehicle->refresh();
        $this->assertSame('disponivel', $vehicle->status);
        $this->assertSame(1088, $vehicle->current_km);

        $vehicleRequest->refresh();
        $this->assertNotNull($vehicleRequest->started_at);
        $this->assertNotNull($vehicleRequest->completed_at);
        $this->assertDatabaseHas('vehicle_request_status_histories', [
            'vehicle_request_id' => $vehicleRequest->id,
            'from_status' => 'aprovada',
            'to_status' => 'em_deslocamento',
        ]);
        $this->assertDatabaseHas('vehicle_request_status_histories', [
            'vehicle_request_id' => $vehicleRequest->id,
            'from_status' => 'em_deslocamento',
            'to_status' => 'concluida',
        ]);
    }

    public function test_departure_checklist_blocks_trip_when_critical_items_are_unsafe(): void
    {
        [$user, $vehicleRequest] = $this->approvedRequest();

        Sanctum::actingAs($user);

        $this->postJson("/api/transport/requests/{$vehicleRequest->id}/departure-checklist", [
            'fuel_level' => 90,
            'km' => 1000,
            'documents_ok' => false,
            'tires_ok' => true,
            'lights_ok' => true,
            'bodywork_ok' => true,
            'tools_ok' => true,
            'has_damage' => false,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('documents_ok');

        $this->assertSame('aprovada', $vehicleRequest->fresh()->status);
    }

    public function test_departure_checklist_cannot_roll_back_vehicle_km(): void
    {
        [$user, $vehicleRequest] = $this->approvedRequest();

        Sanctum::actingAs($user);

        $this->postJson("/api/transport/requests/{$vehicleRequest->id}/departure-checklist", [
            'fuel_level' => 90,
            'km' => 999,
            'documents_ok' => true,
            'tires_ok' => true,
            'lights_ok' => true,
            'bodywork_ok' => true,
            'tools_ok' => true,
            'has_damage' => false,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('km');

        $this->assertSame('aprovada', $vehicleRequest->fresh()->status);
    }

    public function test_cancellation_requires_reason_and_records_responsible_user(): void
    {
        [$user, $vehicleRequest] = $this->approvedRequest();

        Sanctum::actingAs($user);

        $this->postJson("/api/transport/requests/{$vehicleRequest->id}/cancel")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        $this->postJson("/api/transport/requests/{$vehicleRequest->id}/cancel", [
            'reason' => 'Agenda institucional remarcada.',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'cancelada')
            ->assertJsonPath('cancellation_reason', 'Agenda institucional remarcada.')
            ->assertJsonPath('cancelled_by', $user->id);

        $vehicleRequest->refresh();
        $this->assertSame($user->id, $vehicleRequest->cancelled_by);
        $this->assertNotNull($vehicleRequest->cancelled_at);
        $this->assertDatabaseHas('vehicle_request_status_histories', [
            'vehicle_request_id' => $vehicleRequest->id,
            'from_status' => 'aprovada',
            'to_status' => 'cancelada',
            'reason' => 'Agenda institucional remarcada.',
        ]);
    }

    /**
     * @return array{0: User, 1: VehicleRequest, 2: Vehicle}
     */
    private function approvedRequest(): array
    {
        $department = Department::create(['name' => 'Transporte', 'slug' => 'transporte']);
        $user = User::factory()->create(['department_id' => $department->id]);
        $user->assignRole('Gerente Transporte');

        $vehicle = Vehicle::create([
            'brand' => 'Fiat',
            'model' => 'Cronos',
            'plate' => 'LIF1D23',
            'status' => 'disponivel',
            'current_km' => 1000,
        ]);

        $vehicleRequest = VehicleRequest::create([
            'department_id' => $department->id,
            'vehicle_id' => $vehicle->id,
            'requested_by' => $user->id,
            'approved_by' => $user->id,
            'status' => 'aprovada',
            'route_description' => 'Viagem oficial',
            'origin' => 'SEAS',
            'destination' => 'Unidade externa',
            'date' => now()->addDay()->toDateString(),
            'departure_time' => '08:00',
            'expected_arrival_time' => '12:00',
            'passengers_count' => 2,
            'responsible' => 'Responsavel',
        ]);

        return [$user, $vehicleRequest, $vehicle];
    }
}
