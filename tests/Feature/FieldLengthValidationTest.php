<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\ItTicket;
use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FieldLengthValidationTest extends TestCase
{
    use RefreshDatabase;

    private Department $department;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->department = Department::create(['name' => 'Tecnologia', 'slug' => 'tecnologia-limites']);
        $administrator = User::factory()->create(['department_id' => $this->department->id]);
        $administrator->assignRole('Administrador Geral');
        Sanctum::actingAs($administrator);
    }

    public function test_ticket_description_accepts_boundary_and_rejects_excess_without_writing(): void
    {
        $ticketId = $this->postJson('/api/it/tickets', $this->ticketPayload(str_repeat('a', 10000)))
            ->assertCreated()
            ->json('id');

        $this->putJson("/api/it/tickets/{$ticketId}", ['description' => str_repeat('b', 10001)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('description')
            ->assertJsonPath('errors.description.0', 'O campo descricao nao pode ter mais que 10000 caracteres.');

        $this->assertSame(str_repeat('a', 10000), ItTicket::query()->findOrFail($ticketId)->description);

        $this->postJson('/api/it/tickets', $this->ticketPayload(str_repeat('c', 10001)))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('description');
        $this->assertDatabaseCount('it_tickets', 1);
    }

    public function test_ticket_messages_and_tasks_reject_oversized_text_without_writing(): void
    {
        $ticket = ItTicket::create([
            'department_id' => $this->department->id,
            'requester_name' => 'Solicitante',
            'description' => 'Falha no equipamento',
            'status' => 'aberto',
            'source' => 'manual',
        ]);

        $this->postJson("/api/it/tickets/{$ticket->id}/messages", ['message' => str_repeat('m', 10000)])
            ->assertCreated();
        $this->postJson("/api/it/tickets/{$ticket->id}/messages", ['message' => str_repeat('m', 10001)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('message');
        $this->postJson("/api/it/tickets/{$ticket->id}/tasks", [
            'title' => 'Diagnóstico',
            'description' => str_repeat('d', 4001),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('description');

        $this->assertDatabaseCount('it_ticket_messages', 1);
        $this->assertDatabaseCount('it_ticket_tasks', 0);
    }

    public function test_transport_and_category_text_limits_are_enforced(): void
    {
        $this->postJson('/api/transport/vehicles', [
            'brand' => 'Fiat',
            'model' => 'Cronos',
            'plate' => 'LIM1A23',
            'status' => 'disponivel',
            'notes' => str_repeat('n', 4001),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('notes');

        $this->postJson('/api/transport/requests', [
            'department_id' => $this->department->id,
            'route_description' => str_repeat('r', 4001),
            'origin' => 'Sede',
            'destination' => 'Unidade',
            'date' => now()->addDay()->toDateString(),
            'departure_time' => '08:00',
            'passengers_count' => 1,
            'responsible' => 'Responsável',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('route_description');

        $this->postJson('/api/it/categories', [
            'name' => 'Categoria limitada',
            'description' => str_repeat('x', 2001),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('description');

        $this->assertDatabaseCount('vehicles', 0);
        $this->assertDatabaseCount('vehicle_requests', 0);
        $this->assertDatabaseCount('it_ticket_categories', 0);
    }

    public function test_dynamic_text_setting_rejects_excess_without_changing_value(): void
    {
        $this->seed(SystemSettingSeeder::class);

        $this->putJson('/api/settings', [
            'settings' => [[
                'key' => 'institution_name',
                'value' => str_repeat('s', 4097),
            ]],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('settings.0.value');

        $this->assertDatabaseHas('system_settings', ['key' => 'institution_name']);
        $this->assertNotSame(str_repeat('s', 4097), SystemSetting::query()->where('key', 'institution_name')->firstOrFail()->resolvedValue());
    }

    public function test_primary_vue_forms_declare_the_same_user_facing_limits(): void
    {
        $helpDesk = file_get_contents(base_path('frontend/quasar/src/pages/ItHelpDeskPage.vue'));
        $transport = file_get_contents(base_path('frontend/quasar/src/pages/TransportPage.vue'));
        $materials = file_get_contents(base_path('frontend/quasar/src/pages/MaterialsPage.vue'));
        $helper = file_get_contents(base_path('frontend/quasar/src/utils/form-validation.js'));

        $this->assertStringContainsString('ticketForm.description', $helpDesk);
        $this->assertStringContainsString(':maxlength="10000"', $helpDesk);
        $this->assertStringContainsString('maxLength(10000)', $helpDesk);
        $this->assertStringContainsString('requestForm.route_description', $transport);
        $this->assertStringContainsString(':maxlength="4000"', $transport);
        $this->assertStringContainsString('stockForm.notes', $materials);
        $this->assertStringContainsString('Use no máximo ${limit} caracteres', $helper);
    }

    private function ticketPayload(string $description): array
    {
        return [
            'department_id' => $this->department->id,
            'requester_name' => 'Solicitante',
            'description' => $description,
            'priority' => 'media',
            'type' => 'incidente',
        ];
    }
}
