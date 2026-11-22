<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleMaintenance;
use App\Models\VehicleRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TransportOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_vehicle_maintenance_lifecycle_updates_vehicle_state_and_summary(): void
    {
        [$user, $department, $vehicle] = $this->transportContext();

        Sanctum::actingAs($user);

        $maintenanceId = $this->postJson('/api/transport/maintenances', [
            'vehicle_id' => $vehicle->id,
            'type' => 'oleo',
            'status' => 'em_execucao',
            'scheduled_at' => now()->toDateString(),
            'odometer' => 1050,
            'next_due_km' => 1500,
            'next_due_at' => now()->addMonth()->toDateString(),
            'description' => 'Troca de oleo preventiva.',
        ])
            ->assertCreated()
            ->assertJsonPath('status', 'em_execucao')
            ->json('id');

        $this->assertSame('manutencao', $vehicle->fresh()->status);

        $this->putJson("/api/transport/maintenances/{$maintenanceId}", [
            'status' => 'concluida',
            'completed_at' => now()->toDateString(),
            'odometer' => 1100,
            'cost' => 240.50,
        ])
            ->assertOk()
            ->assertJsonPath('status', 'concluida')
            ->assertJsonPath('completed_by', $user->id);

        $vehicle->refresh();
        $this->assertSame('disponivel', $vehicle->status);
        $this->assertSame(1100, $vehicle->current_km);

        $this->getJson('/api/transport/maintenances/summary')
            ->assertOk()
            ->assertJsonPath('completed_month', 1);

        $this->assertDatabaseHas('vehicle_maintenances', [
            'id' => $maintenanceId,
            'vehicle_id' => $vehicle->id,
            'completed_by' => $user->id,
        ]);

        $this->assertSame($department->id, $user->department_id);
    }

    public function test_maintenance_cost_persists_exact_decimal_and_rejects_excess_precision_or_range(): void
    {
        [$user, , $vehicle] = $this->transportContext();
        Sanctum::actingAs($user);

        $maintenanceId = $this->postJson('/api/transport/maintenances', [
            'vehicle_id' => $vehicle->id,
            'type' => 'revisao',
            'description' => 'Teste de custo decimal.',
            'cost' => '240.50',
        ])->assertCreated()
            ->assertJsonPath('cost', '240.50')
            ->json('id');

        $this->assertSame('240.50', VehicleMaintenance::query()->findOrFail($maintenanceId)->cost);

        foreach (['0.001', '1000000000.00'] as $cost) {
            $this->postJson('/api/transport/maintenances', [
                'vehicle_id' => $vehicle->id,
                'type' => 'revisao',
                'description' => 'Custo inválido.',
                'cost' => $cost,
            ])->assertUnprocessable()
                ->assertJsonValidationErrors('cost');
        }
    }

    public function test_transport_request_filters_and_agenda_return_scoped_operational_data(): void
    {
        [$user, $department, $vehicle] = $this->transportContext();
        $driver = Driver::create([
            'name' => 'Motorista Agenda',
            'cnh' => '11122233344',
            'cnh_category' => 'B',
            'cnh_expires_at' => now()->addYear()->toDateString(),
            'status' => 'ativo',
        ]);
        $date = now()->addDay()->toDateString();

        VehicleRequest::create([
            'department_id' => $department->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'requested_by' => $user->id,
            'status' => 'aprovada',
            'route_description' => 'Agenda operacional',
            'origin' => 'SEAS',
            'destination' => 'Unidade externa',
            'date' => $date,
            'departure_time' => '08:00',
            'expected_arrival_time' => '10:00',
            'passengers_count' => 2,
            'responsible' => 'Responsavel Agenda',
        ]);

        VehicleRequest::create([
            'department_id' => $department->id,
            'requested_by' => $user->id,
            'status' => 'cancelada',
            'route_description' => 'Viagem cancelada',
            'origin' => 'SEAS',
            'destination' => 'Arquivo',
            'date' => $date,
            'departure_time' => '13:00',
            'passengers_count' => 1,
            'responsible' => 'Outro responsavel',
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/transport/requests?status=aprovada&vehicle_id={$vehicle->id}&date_from={$date}&date_to={$date}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.responsible', 'Responsavel Agenda');

        $this->getJson("/api/transport/agenda?date_from={$date}&date_to={$date}&driver_id={$driver->id}")
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.vehicle', "AGE1D23 - Fiat Cronos");

        $this->getJson('/api/transport/summary')
            ->assertOk()
            ->assertJsonPath('open', 1);
    }

    public function test_transport_maintenance_report_is_available_to_transport_manager(): void
    {
        [$user, $department, $vehicle] = $this->transportContext();

        VehicleMaintenance::create([
            'vehicle_id' => $vehicle->id,
            'created_by' => $user->id,
            'type' => 'licenciamento',
            'status' => 'agendada',
            'scheduled_at' => now()->toDateString(),
            'description' => 'Renovacao de licenciamento.',
        ]);

        Sanctum::actingAs($user);

        $this->assertSame('Transporte', $department->name);

        $this->getJson('/api/reports/options')
            ->assertOk()
            ->assertJsonFragment(['value' => 'transport_maintenances']);

        $this->getJson('/api/reports/preview?report=transport_maintenances')
            ->assertOk()
            ->assertJsonPath('title', 'Manutencoes da frota')
            ->assertJsonPath('total', 1);
    }

    /**
     * @return array{0: User, 1: Department, 2: Vehicle}
     */
    private function transportContext(): array
    {
        $department = Department::create(['name' => 'Transporte', 'slug' => 'transporte']);
        $user = User::factory()->create(['department_id' => $department->id]);
        $user->assignRole('Gerente Transporte');

        $vehicle = Vehicle::create([
            'brand' => 'Fiat',
            'model' => 'Cronos',
            'plate' => 'AGE1D23',
            'status' => 'disponivel',
            'current_km' => 1000,
        ]);

        return [$user, $department, $vehicle];
    }
}
