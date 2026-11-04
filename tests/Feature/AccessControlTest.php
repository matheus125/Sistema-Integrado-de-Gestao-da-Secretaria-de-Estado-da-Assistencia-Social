<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Department;
use App\Models\Driver;
use App\Models\ItTicket;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_module_catalog_only_returns_modules_visible_to_the_user(): void
    {
        $user = $this->userWithRole('Servidor Informatica');

        Sanctum::actingAs($user);

        $this->getJson('/api/modules')
            ->assertOk()
            ->assertJsonPath('modules.dashboard.name', 'Dashboard')
            ->assertJsonPath('modules.informatica.name', 'Gerencia de Informatica')
            ->assertJsonMissingPath('modules.transporte')
            ->assertJsonMissingPath('modules.materiais-patrimonio')
            ->assertJsonMissingPath('modules.administracao');
    }

    public function test_user_without_module_permission_cannot_access_other_management_apis(): void
    {
        Sanctum::actingAs($this->userWithRole('Servidor Informatica'));

        $this->getJson('/api/transport/requests')->assertForbidden();
        $this->getJson('/api/materials/assets')->assertForbidden();
        $this->getJson('/api/admin/users')->assertForbidden();
    }

    public function test_material_assets_are_scoped_to_user_department(): void
    {
        [$ownDepartment, $otherDepartment] = $this->departments();
        $user = $this->userWithRole('Servidor Materiais e Patrimonio', $ownDepartment);

        Asset::create([
            'department_id' => $ownDepartment->id,
            'asset_number' => 'PAT-OWN',
            'description' => 'Notebook do setor',
            'status' => 'ativo',
        ]);

        Asset::create([
            'department_id' => $otherDepartment->id,
            'asset_number' => 'PAT-OTHER',
            'description' => 'Projetor de outro setor',
            'status' => 'ativo',
        ]);

        Sanctum::actingAs($user);

        $payload = $this->getJson('/api/materials/assets')
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $payload);
        $this->assertSame('PAT-OWN', $payload[0]['asset_number']);
    }

    public function test_it_tickets_are_scoped_to_assignee_opened_by_or_department(): void
    {
        [$ownDepartment, $otherDepartment] = $this->departments();
        $user = $this->userWithRole('Servidor Informatica', $ownDepartment);
        $otherUser = $this->userWithRole('Servidor Informatica', $otherDepartment);

        ItTicket::create([
            'department_id' => $ownDepartment->id,
            'requester_name' => 'Servidor do setor',
            'description' => 'Computador sem rede',
            'priority' => 'media',
            'status' => 'aberto',
        ]);

        ItTicket::create([
            'department_id' => $otherDepartment->id,
            'assigned_to' => $user->id,
            'requester_name' => 'Chamado atribuido',
            'description' => 'Impressora sem toner',
            'priority' => 'alta',
            'status' => 'triagem',
        ]);

        ItTicket::create([
            'department_id' => $otherDepartment->id,
            'opened_by' => $otherUser->id,
            'requester_name' => 'Chamado externo',
            'description' => 'Solicitacao fora do setor',
            'priority' => 'baixa',
            'status' => 'aberto',
        ]);

        Sanctum::actingAs($user);

        $names = collect($this->getJson('/api/it/tickets')->assertOk()->json('data'))
            ->pluck('requester_name')
            ->all();

        $this->assertEqualsCanonicalizing(['Chamado atribuido', 'Servidor do setor'], $names);
    }

    public function test_transport_request_created_by_sector_user_is_forced_to_own_department(): void
    {
        [$ownDepartment, $otherDepartment] = $this->departments();
        $user = $this->userWithRole('Servidor Transporte', $ownDepartment);
        $vehicle = Vehicle::create([
            'brand' => 'Fiat',
            'model' => 'Cronos',
            'plate' => 'ABC1D23',
            'status' => 'disponivel',
        ]);
        $driver = Driver::create([
            'name' => 'Motorista Teste',
            'cnh' => '12345678901',
            'cnh_category' => 'B',
            'cnh_expires_at' => now()->addYear()->toDateString(),
            'status' => 'ativo',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/transport/requests', [
            'department_id' => $otherDepartment->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'route_description' => 'Entrega de documentos',
            'origin' => 'SEAS',
            'destination' => 'Arquivo central',
            'date' => now()->addDay()->toDateString(),
            'departure_time' => '09:00',
            'expected_arrival_time' => '11:00',
            'passengers_count' => 2,
            'responsible' => 'Servidor solicitante',
        ])
            ->assertCreated()
            ->assertJsonPath('department_id', $ownDepartment->id);
    }

    public function test_transport_vehicle_schedule_conflicts_use_time_windows(): void
    {
        [$department] = $this->departments();
        $user = $this->userWithRole('Servidor Transporte', $department);
        $vehicle = Vehicle::create([
            'brand' => 'Fiat',
            'model' => 'Cronos',
            'plate' => 'WIN1D23',
            'status' => 'disponivel',
        ]);

        VehicleRequest::create([
            'department_id' => $department->id,
            'vehicle_id' => $vehicle->id,
            'requested_by' => $user->id,
            'status' => 'analise',
            'route_description' => 'Agenda ja registrada',
            'origin' => 'SEAS',
            'destination' => 'Unidade externa',
            'date' => now()->addDay()->toDateString(),
            'departure_time' => '09:00',
            'expected_arrival_time' => '11:00',
            'passengers_count' => 1,
            'responsible' => 'Responsavel',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/transport/requests', [
            'department_id' => $department->id,
            'vehicle_id' => $vehicle->id,
            'route_description' => 'Agenda conflitante',
            'origin' => 'SEAS',
            'destination' => 'Arquivo central',
            'date' => now()->addDay()->toDateString(),
            'departure_time' => '10:30',
            'expected_arrival_time' => '12:00',
            'passengers_count' => 2,
            'responsible' => 'Servidor solicitante',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('vehicle_id');
    }

    public function test_transport_driver_schedule_conflicts_use_time_windows(): void
    {
        [$department] = $this->departments();
        $user = $this->userWithRole('Servidor Transporte', $department);
        $driver = Driver::create([
            'name' => 'Motorista Ocupado',
            'cnh' => '98765432101',
            'cnh_category' => 'B',
            'cnh_expires_at' => now()->addYear()->toDateString(),
            'status' => 'ativo',
        ]);
        $firstVehicle = Vehicle::create([
            'brand' => 'Fiat',
            'model' => 'Cronos',
            'plate' => 'DRV1D23',
            'status' => 'disponivel',
        ]);
        $secondVehicle = Vehicle::create([
            'brand' => 'Fiat',
            'model' => 'Argo',
            'plate' => 'DRV1D24',
            'status' => 'disponivel',
        ]);

        VehicleRequest::create([
            'department_id' => $department->id,
            'vehicle_id' => $firstVehicle->id,
            'driver_id' => $driver->id,
            'requested_by' => $user->id,
            'status' => 'analise',
            'route_description' => 'Agenda ja registrada',
            'origin' => 'SEAS',
            'destination' => 'Unidade externa',
            'date' => now()->addDay()->toDateString(),
            'departure_time' => '09:00',
            'expected_arrival_time' => '11:00',
            'passengers_count' => 1,
            'responsible' => 'Responsavel',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/transport/requests', [
            'department_id' => $department->id,
            'vehicle_id' => $secondVehicle->id,
            'driver_id' => $driver->id,
            'route_description' => 'Agenda conflitante',
            'origin' => 'SEAS',
            'destination' => 'Arquivo central',
            'date' => now()->addDay()->toDateString(),
            'departure_time' => '10:30',
            'expected_arrival_time' => '12:00',
            'passengers_count' => 2,
            'responsible' => 'Servidor solicitante',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('driver_id');
    }

    public function test_transport_request_validates_vehicle_capacity(): void
    {
        [$department] = $this->departments();
        $user = $this->userWithRole('Servidor Transporte', $department);
        $vehicle = Vehicle::create([
            'brand' => 'Fiat',
            'model' => 'Mobi',
            'plate' => 'CAP1D23',
            'capacity' => 4,
            'status' => 'disponivel',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/transport/requests', [
            'department_id' => $department->id,
            'vehicle_id' => $vehicle->id,
            'route_description' => 'Viagem com muitos passageiros',
            'origin' => 'SEAS',
            'destination' => 'Arquivo central',
            'date' => now()->addDay()->toDateString(),
            'departure_time' => '14:00',
            'expected_arrival_time' => '16:00',
            'passengers_count' => 5,
            'responsible' => 'Servidor solicitante',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('passengers_count');
    }

    public function test_transport_status_flow_requires_valid_transitions(): void
    {
        [$department] = $this->departments();
        $user = $this->userWithRole('Gerente Transporte', $department);
        $vehicle = Vehicle::create([
            'brand' => 'Fiat',
            'model' => 'Cronos',
            'plate' => 'FLW1D23',
            'status' => 'disponivel',
        ]);
        $vehicleRequest = VehicleRequest::create([
            'department_id' => $department->id,
            'vehicle_id' => $vehicle->id,
            'requested_by' => $user->id,
            'status' => 'solicitacao',
            'route_description' => 'Fluxo de aprovacao',
            'origin' => 'SEAS',
            'destination' => 'Unidade externa',
            'date' => now()->addDay()->toDateString(),
            'departure_time' => '13:00',
            'expected_arrival_time' => '15:00',
            'passengers_count' => 1,
            'responsible' => 'Responsavel',
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/transport/requests/{$vehicleRequest->id}/approve")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->postJson("/api/transport/requests/{$vehicleRequest->id}/status", ['status' => 'analise'])
            ->assertOk()
            ->assertJsonPath('status', 'analise');

        $this->postJson("/api/transport/requests/{$vehicleRequest->id}/approve")
            ->assertOk()
            ->assertJsonPath('status', 'aprovada');

        $this->postJson("/api/transport/requests/{$vehicleRequest->id}/status", ['status' => 'em_deslocamento'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('checklist');

        $this->postJson("/api/transport/requests/{$vehicleRequest->id}/departure-checklist", [
            'fuel_level' => 80,
            'km' => 10,
            'documents_ok' => true,
            'tires_ok' => true,
            'lights_ok' => true,
            'bodywork_ok' => true,
            'tools_ok' => true,
            'has_damage' => false,
        ])
            ->assertOk()
            ->assertJsonPath('status', 'em_deslocamento');

        $this->postJson("/api/transport/requests/{$vehicleRequest->id}/return-checklist", [
            'fuel_level' => 60,
            'km' => 24,
            'documents_ok' => true,
            'tires_ok' => true,
            'lights_ok' => true,
            'bodywork_ok' => true,
            'tools_ok' => true,
            'has_damage' => false,
            'observations' => 'Retorno sem ocorrencias.',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'concluida');
    }

    public function test_transport_requests_return_available_transitions_from_api(): void
    {
        [$department] = $this->departments();
        $user = $this->userWithRole('Gerente Transporte', $department);
        $vehicleRequest = VehicleRequest::create([
            'department_id' => $department->id,
            'requested_by' => $user->id,
            'status' => 'solicitacao',
            'route_description' => 'Fluxo exposto na API',
            'origin' => 'SEAS',
            'destination' => 'Unidade externa',
            'date' => now()->addDay()->toDateString(),
            'departure_time' => '10:00',
            'expected_arrival_time' => '11:00',
            'passengers_count' => 1,
            'responsible' => 'Responsavel',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/transport/requests')
            ->assertOk()
            ->assertJsonPath('data.0.id', $vehicleRequest->id)
            ->assertJsonPath('data.0.available_transitions', ['analise', 'cancelada', 'rejeitada']);

        $this->getJson("/api/transport/requests/{$vehicleRequest->id}")
            ->assertOk()
            ->assertJsonPath('available_transitions', ['analise', 'cancelada', 'rejeitada']);
    }

    public function test_transport_vehicle_status_and_km_follow_trip_lifecycle(): void
    {
        [$department] = $this->departments();
        $user = $this->userWithRole('Gerente Transporte', $department);
        $vehicle = Vehicle::create([
            'brand' => 'Fiat',
            'model' => 'Cronos',
            'plate' => 'KMV1D23',
            'status' => 'disponivel',
            'current_km' => 100,
        ]);
        $vehicleRequest = VehicleRequest::create([
            'department_id' => $department->id,
            'vehicle_id' => $vehicle->id,
            'requested_by' => $user->id,
            'status' => 'aprovada',
            'route_description' => 'Controle de km',
            'origin' => 'SEAS',
            'destination' => 'Unidade externa',
            'date' => now()->addDay()->toDateString(),
            'departure_time' => '13:00',
            'expected_arrival_time' => '15:00',
            'passengers_count' => 1,
            'responsible' => 'Responsavel',
            'initial_km' => 100,
            'final_km' => 142,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/transport/requests/{$vehicleRequest->id}/departure-checklist", [
            'fuel_level' => 80,
            'km' => 100,
            'documents_ok' => true,
            'tires_ok' => true,
            'lights_ok' => true,
            'bodywork_ok' => true,
            'tools_ok' => true,
            'has_damage' => false,
        ])
            ->assertOk()
            ->assertJsonPath('status', 'em_deslocamento');

        $this->assertSame('em_uso', $vehicle->fresh()->status);

        $this->postJson("/api/transport/requests/{$vehicleRequest->id}/return-checklist", [
            'fuel_level' => 60,
            'km' => 142,
            'documents_ok' => true,
            'tires_ok' => true,
            'lights_ok' => true,
            'bodywork_ok' => true,
            'tools_ok' => true,
            'has_damage' => false,
        ])
            ->assertOk()
            ->assertJsonPath('status', 'concluida');

        $vehicle->refresh();
        $this->assertSame('disponivel', $vehicle->status);
        $this->assertSame(142, $vehicle->current_km);
    }

    public function test_transport_trip_can_be_updated_while_its_own_vehicle_is_in_use(): void
    {
        [$department] = $this->departments();
        $user = $this->userWithRole('Gerente Transporte', $department);
        $vehicle = Vehicle::create([
            'brand' => 'Fiat',
            'model' => 'Cronos',
            'plate' => 'USE1D23',
            'status' => 'em_uso',
            'current_km' => 100,
        ]);
        $vehicleRequest = VehicleRequest::create([
            'department_id' => $department->id,
            'vehicle_id' => $vehicle->id,
            'requested_by' => $user->id,
            'status' => 'em_deslocamento',
            'route_description' => 'Viagem em andamento',
            'origin' => 'SEAS',
            'destination' => 'Unidade externa',
            'date' => now()->addDay()->toDateString(),
            'departure_time' => '13:00',
            'expected_arrival_time' => '15:00',
            'passengers_count' => 1,
            'responsible' => 'Responsavel',
            'initial_km' => 100,
        ]);

        Sanctum::actingAs($user);

        $this->putJson("/api/transport/requests/{$vehicleRequest->id}", [
            'final_km' => 132,
            'notes' => 'Retorno registrado pelo motorista.',
        ])
            ->assertOk()
            ->assertJsonPath('final_km', 132);
    }

    public function test_transport_availability_returns_vehicle_schedule_for_date(): void
    {
        [$department] = $this->departments();
        $user = $this->userWithRole('Gerente Transporte', $department);
        $vehicle = Vehicle::create([
            'brand' => 'Fiat',
            'model' => 'Cronos',
            'plate' => 'AVL1D23',
            'status' => 'disponivel',
        ]);
        $date = now()->addDay()->toDateString();

        $scheduledRequest = VehicleRequest::create([
            'department_id' => $department->id,
            'vehicle_id' => $vehicle->id,
            'requested_by' => $user->id,
            'status' => 'aprovada',
            'route_description' => 'Agenda da frota',
            'origin' => 'SEAS',
            'destination' => 'Unidade externa',
            'date' => $date,
            'departure_time' => '08:00',
            'expected_arrival_time' => '10:00',
            'passengers_count' => 1,
            'responsible' => 'Responsavel',
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/transport/availability?date={$date}")
            ->assertOk()
            ->assertJsonPath('vehicles.0.available', false)
            ->assertJsonPath('vehicles.0.schedule.0.id', $scheduledRequest->id)
            ->assertJsonPath('vehicles.0.schedule.0.departure_time', '08:00');
    }

    public function test_dashboard_only_returns_authorized_module_metrics_and_work_items(): void
    {
        [$ownDepartment, $otherDepartment] = $this->departments();
        $user = $this->userWithRole('Servidor Informatica', $ownDepartment);
        $otherUser = $this->userWithRole('Servidor Informatica', $otherDepartment);
        $vehicle = Vehicle::create([
            'brand' => 'Fiat',
            'model' => 'Cronos',
            'plate' => 'XYZ1A23',
            'status' => 'disponivel',
        ]);

        VehicleRequest::create([
            'department_id' => $ownDepartment->id,
            'vehicle_id' => $vehicle->id,
            'requested_by' => $otherUser->id,
            'status' => 'analise',
            'route_description' => 'Agenda de transporte',
            'origin' => 'SEAS',
            'destination' => 'Unidade externa',
            'date' => now()->addDay()->toDateString(),
            'departure_time' => '08:00',
            'passengers_count' => 1,
            'responsible' => 'Responsavel',
        ]);

        $ticket = ItTicket::create([
            'department_id' => $ownDepartment->id,
            'requester_name' => 'Servidor do setor',
            'description' => 'Sem acesso ao sistema',
            'type' => 'acesso',
            'priority' => 'urgente',
            'status' => 'triagem',
            'category' => 'Acesso',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonMissingPath('transportes')
            ->assertJsonMissingPath('materiais_patrimonio')
            ->assertJsonPath('informatica.chamados_abertos', 1)
            ->assertJsonPath('charts.chamados_por_prioridade.3.value', 1)
            ->assertJsonPath('charts.chamados_por_tipo.2.value', 1)
            ->assertJsonPath('work_items.0.module', 'Informatica')
            ->assertJsonPath('work_items.0.title', "Chamado #{$ticket->id} - Servidor do setor");
    }

    /**
     * @return array{0: Department, 1: Department}
     */
    private function departments(): array
    {
        return [
            Department::create(['name' => 'Informatica', 'slug' => 'informatica']),
            Department::create(['name' => 'Transporte', 'slug' => 'transporte']),
        ];
    }

    private function userWithRole(string $role, ?Department $department = null): User
    {
        $department ??= Department::create([
            'name' => str($role)->slug()->headline()->toString(),
            'slug' => str($role)->slug()->toString(),
        ]);

        $user = User::factory()->create(['department_id' => $department->id]);
        $user->assignRole($role);

        return $user;
    }
}
