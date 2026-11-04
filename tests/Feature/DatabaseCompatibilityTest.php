<?php

namespace Tests\Feature;

use App\Models\ItTicket;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleMaintenance;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DatabaseCompatibilityTest extends TestCase
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

    public function test_recurrences_preserve_the_key_field(): void
    {
        $user = User::firstOrFail();
        for ($i = 0; $i < 3; $i++) {
            ItTicket::create(['requester_name'=>'Servidor', 'description'=>'Recorrencia', 'opened_by'=>$user->id]);
        }
        $this->getJson('/api/it/dashboard/recurrences')->assertOk()
            ->assertJsonPath('by_requester.0.key', $user->id)
            ->assertJsonPath('by_requester.0.total', 3);
    }

    public function test_maintenance_due_below_500_km_does_not_underflow(): void
    {
        $vehicle = Vehicle::create(['brand'=>'Fiat', 'model'=>'Cronos', 'plate'=>'SQL1A23', 'current_km'=>0]);
        $maintenance = VehicleMaintenance::create(['vehicle_id'=>$vehicle->id, 'type'=>'revisao', 'status'=>'agendada', 'next_due_km'=>100, 'description'=>'Revisao inicial']);
        $this->getJson('/api/transport/maintenances/summary')->assertOk()->assertJsonPath('soon', 1);
        $this->getJson('/api/transport/maintenances?due=soon')->assertOk()->assertJsonPath('data.0.id', $maintenance->id);
    }

    public function test_metrics_preserve_fractional_minutes(): void
    {
        $created = now()->subMinutes(10)->startOfSecond();
        $ticket = ItTicket::create(['requester_name'=>'Servidor', 'description'=>'Precisao SLA',
            'first_response_at'=>$created->copy()->addSeconds(90), 'resolved_at'=>$created->copy()->addSeconds(150), 'status'=>'resolvido']);
        $ticket->forceFill(['created_at'=>$created])->save();
        $response = $this->getJson('/api/it/dashboard/operational')->assertOk();
        $this->assertEquals(1.5, $response->json('indicators.mtta_minutes'));
        $this->assertEquals(2.5, $response->json('indicators.mttr_minutes'));
    }
}
