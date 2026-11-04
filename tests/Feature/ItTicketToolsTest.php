<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\ItChecklistTemplate;
use App\Models\ItTicket;
use App\Models\ItResponseTemplate;
use App\Models\ItAutomationRule;
use App\Models\ItServiceCatalog;
use App\Models\ItTicketApproval;
use App\Models\Asset;
use App\Models\ItPreventiveMaintenance;
use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ItTicketToolsTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $department = Department::create(['name' => 'TI', 'slug' => 'ti-tools']);
        $user = User::factory()->create(['department_id' => $department->id]);
        $user->assignRole('Gerente Informatica');
        Sanctum::actingAs($user);
        return [$user, $department];
    }

    public function test_template_is_applied_to_new_ticket_and_required_checklist_blocks_resolution(): void
    {
        [$user, $department] = $this->context();
        $category = \App\Models\ItTicketCategory::create(['name' => 'Instalacao', 'slug' => 'instalacao-tools', 'active' => true]);
        $template = ItChecklistTemplate::create(['category_id' => $category->id, 'type' => 'instalacao', 'name' => 'Instalacao padrao', 'active' => true]);
        $template->items()->create(['label' => 'Validar permissao', 'required' => true, 'sort_order' => 1]);

        $ticket = $this->postJson('/api/it/tickets', [
            'department_id' => $department->id,
            'category_id' => $category->id,
            'type' => 'instalacao',
            'requester_name' => 'Solicitante',
            'description' => 'Instalar aplicativo',
            'priority' => 'media',
        ])->assertCreated()->json();

        $this->assertDatabaseHas('it_ticket_checklist_items', ['ticket_id' => $ticket['id'], 'required' => 1, 'status' => 'pendente']);
        $this->postJson("/api/it/tickets/{$ticket['id']}/status", ['status' => 'resolvido', 'resolution_summary' => 'Concluido'])
            ->assertUnprocessable();
    }

    public function test_task_completion_and_relationships_are_scoped_and_validated(): void
    {
        [$user, $department] = $this->context();
        $ticket = ItTicket::create(['department_id' => $department->id, 'opened_by' => $user->id, 'requester_name' => 'A', 'description' => 'A', 'status' => 'em_atendimento', 'source' => 'manual']);
        $related = ItTicket::create(['department_id' => $department->id, 'opened_by' => $user->id, 'requester_name' => 'B', 'description' => 'B', 'status' => 'aberto', 'source' => 'manual']);

        $task = $this->postJson("/api/it/tickets/{$ticket->id}/tasks", ['title' => 'Validar', 'required' => true])->assertCreated()->json();
        $this->patchJson("/api/it/tickets/{$ticket->id}/tasks/{$task['id']}", ['status' => 'concluida'])
            ->assertOk()->assertJsonPath('status', 'concluida');
        $this->assertDatabaseHas('it_ticket_tasks', ['id' => $task['id'], 'completed_by' => $user->id]);

        $this->postJson("/api/it/tickets/{$ticket->id}/relationships", ['related_ticket_id' => $related->id, 'type' => 'relacionado_a'])->assertCreated();
        $this->postJson("/api/it/tickets/{$ticket->id}/relationships", ['related_ticket_id' => $ticket->id, 'type' => 'relacionado_a'])->assertUnprocessable();
    }

    public function test_ticket_can_be_cancelled_with_reason_and_timeline_contains_the_status_event(): void
    {
        [$user, $department] = $this->context();
        $ticket = ItTicket::create([
            'department_id' => $department->id,
            'opened_by' => $user->id,
            'requester_name' => 'Solicitante',
            'description' => 'Chamado duplicado',
            'status' => 'triagem',
            'source' => 'manual',
        ]);

        $this->postJson("/api/it/tickets/{$ticket->id}/cancel", ['reason' => 'Chamado duplicado.'])
            ->assertOk()
            ->assertJsonPath('status', 'cancelado')
            ->assertJsonPath('cancellation_reason', 'Chamado duplicado.');

        $this->assertDatabaseHas('it_ticket_status_histories', [
            'ticket_id' => $ticket->id,
            'from_status' => 'triagem',
            'to_status' => 'cancelado',
            'reason' => 'Chamado duplicado.',
        ]);

        $this->getJson("/api/it/tickets/{$ticket->id}/timeline")
            ->assertOk()
            ->assertJsonPath('data.0.type', 'status');
    }

    public function test_response_templates_support_global_and_personal_scopes(): void
    {
        [$user] = $this->context();
        $this->postJson('/api/it/response-templates', [
            'name' => 'Retorno padrão',
            'key' => 'retorno-padrao',
            'body' => 'Olá {{nome}}, seu chamado #{{ticket_id}} foi atualizado.',
            'scope' => 'global',
        ])->assertCreated();

        $personal = $this->postJson('/api/it/response-templates', [
            'name' => 'Minha resposta',
            'body' => 'Vou verificar seu chamado.',
            'scope' => 'pessoal',
        ])->assertCreated()->json();

        $this->getJson('/api/it/response-templates')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Retorno padrão'])
            ->assertJsonFragment(['id' => $personal['id']]);
        $this->assertDatabaseHas('it_response_templates', ['id' => $personal['id'], 'user_id' => $user->id]);
    }

    public function test_automation_rule_runs_when_ticket_status_changes(): void
    {
        [$user, $department] = $this->context();
        $ticket = ItTicket::create([
            'department_id' => $department->id,
            'opened_by' => $user->id,
            'assigned_to' => $user->id,
            'requester_name' => 'Solicitante',
            'description' => 'Automação',
            'priority' => 'media',
            'status' => 'triagem',
            'source' => 'manual',
        ]);
        ItAutomationRule::create([
            'name' => 'Priorizar incidentes resolvidos',
            'event' => 'status_changed',
            'conditions' => ['status' => 'resolvido'],
            'actions' => [['type' => 'message', 'message' => 'Regra executada.']],
            'created_by' => $user->id,
            'active' => true,
        ]);

        $this->postJson("/api/it/tickets/{$ticket->id}/status", ['status' => 'resolvido', 'resolution_summary' => 'Concluído'])
            ->assertOk();
        $this->assertDatabaseHas('it_ticket_messages', ['ticket_id' => $ticket->id, 'message' => 'Regra executada.']);
    }

    public function test_service_catalog_can_be_created_and_listed(): void
    {
        [$user] = $this->context();
        $service = $this->postJson('/api/it/service-catalog', [
            'name' => 'Acesso VPN',
            'key' => 'acesso-vpn',
            'description' => 'Solicitação de acesso remoto.',
            'type' => 'acesso',
            'requires_approval' => true,
            'approval_role' => 'Gerente Informatica',
        ])->assertCreated()->json();

        $this->getJson('/api/it/service-catalog')->assertOk()->assertJsonPath('data.0.id', $service['id']);
        $this->assertDatabaseHas('it_service_catalog', ['id' => $service['id'], 'created_by' => $user->id, 'requires_approval' => 1]);
    }

    public function test_service_requiring_approval_blocks_status_until_decision(): void
    {
        [$user, $department] = $this->context();
        $service = ItServiceCatalog::create([
            'name' => 'Acesso privilegiado', 'key' => 'acesso-privilegiado', 'type' => 'acesso',
            'requires_approval' => true, 'approval_role' => 'Gerente Informatica', 'created_by' => $user->id,
        ]);

        $ticket = $this->postJson('/api/it/tickets', [
            'department_id' => $department->id,
            'service_catalog_id' => $service->id,
            'requester_name' => 'Solicitante',
            'description' => 'Preciso de acesso elevado',
            'priority' => 'alta',
        ])->assertCreated()->json();

        $approval = ItTicketApproval::query()->where('ticket_id', $ticket['id'])->firstOrFail();
        $this->postJson("/api/it/tickets/{$ticket['id']}/status", ['status' => 'em_atendimento'])->assertUnprocessable();
        $this->postJson("/api/it/approvals/{$approval->id}/decide", ['status' => 'aprovado', 'comment' => 'Aprovado.'])->assertOk();
        $this->postJson("/api/it/tickets/{$ticket['id']}/status", ['status' => 'em_atendimento'])->assertOk();
    }

    public function test_impact_and_urgency_calculate_priority_for_new_and_updated_tickets(): void
    {
        [$user, $department] = $this->context();
        $ticket = $this->postJson('/api/it/tickets', [
            'department_id' => $department->id,
            'requester_name' => 'Solicitante',
            'description' => 'Indisponibilidade total',
            'impact' => 'alto',
            'urgency' => 'alta',
        ])->assertCreated()->json();

        $this->assertSame('urgente', $ticket['priority']);
        $this->assertDatabaseHas('it_tickets', [
            'id' => $ticket['id'],
            'impact' => 'alto',
            'urgency' => 'alta',
            'priority' => 'urgente',
        ]);

        $this->putJson("/api/it/tickets/{$ticket['id']}", [
            'impact' => 'baixo',
            'urgency' => 'baixa',
        ])->assertOk()->assertJsonPath('priority', 'baixa');
    }

    public function test_preventive_maintenance_generates_ticket_and_advances_after_completion(): void
    {
        [$user, $department] = $this->context();
        $asset = Asset::create([
            'department_id' => $department->id,
            'asset_number' => 'PAT-PREV-001',
            'description' => 'Servidor de arquivos',
            'status' => 'ativo',
        ]);

        $plan = $this->postJson('/api/it/preventive-maintenance', [
            'asset_id' => $asset->id,
            'name' => 'Revisão mensal do servidor',
            'description' => 'Verificar discos, temperatura e backups.',
            'frequency_days' => 30,
            'next_maintenance_at' => now()->subDay()->toDateString(),
            'lead_days' => 7,
            'auto_create_ticket' => true,
        ])->assertCreated()->json();

        $this->postJson('/api/it/preventive-maintenance/generate')
            ->assertOk()->assertJsonPath('generated', 1);
        $this->assertDatabaseHas('it_tickets', [
            'preventive_maintenance_id' => $plan['id'],
            'primary_asset_id' => $asset->id,
            'source' => 'preventiva',
            'type' => 'manutencao',
        ]);

        $this->postJson("/api/it/preventive-maintenance/{$plan['id']}/complete", [
            'completed_at' => '2026-09-09',
        ])->assertOk()->assertJsonPath('next_maintenance_at', '2026-10-09');
        $this->assertDatabaseHas('it_preventive_maintenances', [
            'id' => $plan['id'],
            'last_maintenance_at' => '2026-09-09',
            'next_maintenance_at' => '2026-10-09',
        ]);
    }

    public function test_help_desk_settings_are_grouped_and_can_be_updated_idempotently(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\SystemSettingSeeder::class);
        $department = Department::create(['name' => 'Administracao', 'slug' => 'administracao-settings']);
        $user = User::factory()->create(['department_id' => $department->id]);
        $user->assignRole('Administrador Geral');
        Sanctum::actingAs($user);

        $this->getJson('/api/settings')
            ->assertOk()
            ->assertJsonStructure(['groups' => ['geral', 'sla', 'chamados', 'notificacoes', 'whatsapp', 'automacoes', 'avaliacao']]);

        $this->putJson('/api/settings', ['settings' => [
            ['key' => 'it_sla_alert_lead_minutes', 'value' => 90],
            ['key' => 'it_automation_enabled', 'value' => false],
            ['key' => 'whatsapp_business_enabled', 'value' => true],
        ]])->assertOk();

        $this->assertSame(90, SystemSetting::where('key', 'it_sla_alert_lead_minutes')->first()->value['value']);
        $this->assertFalse(SystemSetting::where('key', 'it_automation_enabled')->first()->value['value']);
        $this->assertTrue(SystemSetting::where('key', 'whatsapp_business_enabled')->first()->value['value']);
    }
}
