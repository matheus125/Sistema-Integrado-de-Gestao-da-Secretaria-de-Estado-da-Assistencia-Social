<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\ItSlaCalendar;
use App\Models\ItSlaRule;
use App\Models\ItTicket;
use App\Models\ItTicketCategory;
use App\Models\User;
use App\Services\It\SlaService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ItHelpDeskWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_ticket_resolution_requires_summary_and_records_status_history(): void
    {
        [$user, $department] = $this->itContext();
        $ticket = ItTicket::create([
            'department_id' => $department->id,
            'opened_by' => $user->id,
            'assigned_to' => $user->id,
            'requester_name' => 'Servidor Teste',
            'description' => 'Computador nao liga',
            'category' => 'Hardware',
            'priority' => 'alta',
            'status' => 'triagem',
            'source' => 'manual',
            'sla_due_at' => now()->addHours(8),
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/it/tickets/{$ticket->id}/status", [
            'status' => 'resolvido',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('resolution_summary');

        $this->postJson("/api/it/tickets/{$ticket->id}/status", [
            'status' => 'resolvido',
            'resolution_summary' => 'Fonte substituida e equipamento testado.',
            'service_time_minutes' => 45,
        ])
            ->assertOk()
            ->assertJsonPath('status', 'resolvido')
            ->assertJsonPath('resolved_by', $user->id)
            ->assertJsonPath('resolution_summary', 'Fonte substituida e equipamento testado.');

        $ticket->refresh();
        $this->assertSame(45, $ticket->service_time_minutes);
        $this->assertNotNull($ticket->resolved_at);
        $this->assertDatabaseHas('it_ticket_status_histories', [
            'ticket_id' => $ticket->id,
            'from_status' => 'triagem',
            'to_status' => 'resolvido',
            'reason' => 'Fonte substituida e equipamento testado.',
        ]);
    }

    public function test_pending_status_requires_reason_and_ticket_can_be_reopened(): void
    {
        [$user, $department] = $this->itContext();
        $ticket = ItTicket::create([
            'department_id' => $department->id,
            'opened_by' => $user->id,
            'assigned_to' => $user->id,
            'requester_name' => 'Servidor Teste',
            'description' => 'Sem acesso ao sistema',
            'category' => 'Acesso',
            'priority' => 'media',
            'status' => 'em_atendimento',
            'source' => 'manual',
            'sla_due_at' => now()->addDay(),
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/it/tickets/{$ticket->id}/status", [
            'status' => 'aguardando_usuario',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        $this->postJson("/api/it/tickets/{$ticket->id}/status", [
            'status' => 'aguardando_usuario',
            'reason' => 'Aguardando confirmacao do usuario.',
        ])
            ->assertOk()
            ->assertJsonPath('pending_reason', 'Aguardando confirmacao do usuario.');

        $ticket->update([
            'status' => 'resolvido',
            'resolved_at' => now(),
            'resolved_by' => $user->id,
            'resolution_summary' => 'Acesso restaurado.',
        ]);

        $this->postJson("/api/it/tickets/{$ticket->id}/status", [
            'status' => 'em_atendimento',
            'reason' => 'Usuario informou recorrencia.',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'em_atendimento')
            ->assertJsonPath('reopen_count', 1);
    }

    public function test_requester_can_open_and_list_only_own_tickets_without_forcing_technical_fields(): void
    {
        $department = Department::create(['name' => 'Informatica', 'slug' => 'gerencia-de-informatica']);
        $otherDepartment = Department::create(['name' => 'Financeiro', 'slug' => 'financeiro']);
        $requester = User::factory()->create(['department_id' => $department->id]);
        $requester->assignRole('Servidor Informatica');
        $otherUser = User::factory()->create(['department_id' => $otherDepartment->id]);
        $otherUser->assignRole('Servidor Informatica');

        Sanctum::actingAs($requester);

        $ticketId = $this->postJson('/api/it/tickets', [
            'department_id' => $otherDepartment->id,
            'assigned_to' => $otherUser->id,
            'requester_name' => 'Solicitante',
            'description' => 'Nao consigo acessar o sistema.',
            'priority' => 'urgente',
            'internal_technical_note' => 'nao deveria ser aceito',
            'source' => 'whatsapp',
        ])
            ->assertCreated()
            ->assertJsonPath('opened_by.id', $requester->id)
            ->assertJsonPath('department_id', $department->id)
            ->json('id');

        $this->assertDatabaseHas('it_tickets', [
            'id' => $ticketId,
            'opened_by' => $requester->id,
            'department_id' => $department->id,
            'source' => 'manual',
            'internal_technical_note' => null,
        ]);

        ItTicket::create([
            'department_id' => $department->id,
            'opened_by' => $otherUser->id,
            'requester_name' => 'Outro solicitante',
            'description' => 'Chamado de outra pessoa',
            'priority' => 'baixa',
            'status' => 'aberto',
        ]);

        $this->getJson('/api/it/tickets?mine=1')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $ticketId);

        $this->getJson('/api/it/tickets/summary?mine=1')
            ->assertOk()
            ->assertJsonPath('open', 1);
    }

    public function test_requester_can_follow_reopen_message_evaluate_and_manage_own_attachment(): void
    {
        $department = Department::create(['name' => 'Informatica', 'slug' => 'gerencia-de-informatica']);
        $requester = User::factory()->create(['department_id' => $department->id]);
        $requester->assignRole('Servidor Informatica');
        $ticket = ItTicket::create([
            'department_id' => $department->id,
            'opened_by' => $requester->id,
            'requester_name' => 'Solicitante',
            'description' => 'Falha resolvida',
            'priority' => 'media',
            'status' => 'resolvido',
            'resolution_summary' => 'Ajuste aplicado.',
            'resolved_at' => now(),
        ]);

        Sanctum::actingAs($requester);
        Storage::fake('local');

        $this->postJson("/api/it/tickets/{$ticket->id}/messages", [
            'message' => 'O problema voltou.',
            'direction' => 'inbound',
            'visibility' => 'public',
        ])->assertCreated()->assertJsonPath('direction', 'inbound');

        $this->postJson("/api/it/tickets/{$ticket->id}/messages", [
            'message' => 'nota interna indevida',
            'direction' => 'internal',
            'visibility' => 'internal',
        ])->assertForbidden();

        $this->postJson("/api/it/tickets/{$ticket->id}/status", [
            'status' => 'em_atendimento',
            'reason' => 'Solicitante informou recorrencia.',
        ])->assertOk()->assertJsonPath('status', 'em_atendimento');

        $ticket->refresh()->update(['status' => 'resolvido', 'resolution_summary' => 'Resolvido novamente.']);
        $this->postJson("/api/it/tickets/{$ticket->id}/evaluation", [
            'rating' => 5,
            'comment' => 'Atendimento rapido.',
        ])->assertCreated()->assertJsonPath('rating', 5);

        $file = UploadedFile::fake()->create('evidencia.txt', 4, 'text/plain');
        $attachmentId = $this->post("/api/attachments/it-ticket/{$ticket->id}", ['file' => $file])
            ->assertCreated()
            ->json('id');
        Storage::disk('local')->assertExists("attachments/it-ticket/{$ticket->id}/{$file->hashName()}");

        $this->deleteJson("/api/attachments/{$attachmentId}")->assertOk();
    }

    public function test_message_direction_cannot_be_forged_and_attachment_type_is_restricted(): void
    {
        [$technician, $department] = $this->itContext();
        $requester = User::factory()->create(['department_id' => $department->id]);
        $requester->assignRole('Servidor Informatica');
        $ticket = ItTicket::create([
            'department_id' => $department->id,
            'opened_by' => $requester->id,
            'assigned_to' => $technician->id,
            'requester_name' => 'Solicitante',
            'description' => 'Teste de protecao',
            'priority' => 'media',
            'status' => 'aberto',
        ]);

        Sanctum::actingAs($technician);

        $this->postJson("/api/it/tickets/{$ticket->id}/messages", [
            'message' => 'Mensagem forjada',
            'direction' => 'inbound',
            'visibility' => 'public',
        ])->assertForbidden();

        $this->postJson("/api/it/tickets/{$ticket->id}/messages", [
            'message' => 'Nota tecnica',
            'direction' => 'internal',
            'visibility' => 'internal',
        ])->assertCreated();

        Sanctum::actingAs($requester);
        $this->getJson("/api/it/tickets/{$ticket->id}/timeline")
            ->assertOk()
            ->assertJsonMissing(['message' => 'Nota tecnica']);

        Sanctum::actingAs($technician);
        Storage::fake('local');
        $phpFile = UploadedFile::fake()->create('payload.php', 4, 'application/x-php');
        $this->withHeaders(['Accept' => 'application/json'])
            ->post("/api/attachments/it-ticket/{$ticket->id}", ['file' => $phpFile])
            ->assertUnprocessable();
    }

    public function test_it_filters_summary_and_detail_include_operational_context(): void
    {
        [$user, $department] = $this->itContext();
        $otherDepartment = Department::create(['name' => 'Financeiro', 'slug' => 'financeiro']);

        $ownTicket = ItTicket::create([
            'department_id' => $department->id,
            'opened_by' => $user->id,
            'assigned_to' => $user->id,
            'requester_name' => 'Servidor Urgente',
            'description' => 'Internet indisponivel',
            'category' => 'Rede',
            'priority' => 'urgente',
            'status' => 'em_atendimento',
            'source' => 'manual',
            'sla_due_at' => now()->subHour(),
        ]);

        ItTicket::create([
            'department_id' => $otherDepartment->id,
            'requester_name' => 'Outro setor',
            'description' => 'Chamado fora do escopo',
            'category' => 'Software',
            'priority' => 'baixa',
            'status' => 'aberto',
            'source' => 'manual',
            'sla_due_at' => now()->addDay(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/it/tickets?priority=urgente&sla=overdue')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownTicket->id)
            ->assertJsonPath('data.0.available_transitions', ['aguardando_usuario', 'resolvido']);

        $this->getJson('/api/it/tickets/summary')
            ->assertOk()
            ->assertJsonPath('open', 2)
            ->assertJsonPath('overdue', 1);

        $this->getJson("/api/it/tickets/{$ownTicket->id}")
            ->assertOk()
            ->assertJsonPath('available_transitions', ['aguardando_usuario', 'resolvido']);
    }

    public function test_kanban_view_paginates_large_filtered_result_with_available_transitions(): void
    {
        [$user, $department] = $this->itContext();

        foreach (range(1, 180) as $number) {
            ItTicket::create([
                'department_id' => $department->id,
                'opened_by' => $user->id,
                'requester_name' => "Servidor {$number}",
                'description' => "Chamado Kanban {$number}",
                'priority' => 'media',
                'status' => $number % 2 ? 'aberto' : 'triagem',
                'source' => 'manual',
            ]);
        }

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/it/tickets?kanban=1&per_page=25&page=3')
            ->assertOk()
            ->assertJsonPath('meta.view', 'kanban')
            ->assertJsonPath('meta.current_page', 3)
            ->assertJsonPath('meta.last_page', 8)
            ->assertJsonPath('meta.per_page', 25)
            ->assertJsonPath('meta.total', 180)
            ->assertJsonPath('meta.from', 51)
            ->assertJsonPath('meta.to', 75)
            ->assertJsonPath('links.prev', url('/api/it/tickets?kanban=1&per_page=25&page=2'))
            ->assertJsonPath('links.next', url('/api/it/tickets?kanban=1&per_page=25&page=4'));

        $this->assertCount(25, $response->json('data'));
        $triageTicket = collect($response->json('data'))->firstWhere('status', 'triagem');
        $this->assertSame(['em_atendimento', 'aguardando_usuario', 'resolvido'], $triageTicket['available_transitions']);
    }

    public function test_read_only_user_can_view_kanban_but_cannot_move_a_ticket(): void
    {
        $department = Department::create(['name' => 'Informatica', 'slug' => 'gerencia-de-informatica']);
        $user = User::factory()->create(['department_id' => $department->id]);
        $user->assignRole('Servidor Informatica');
        $ticket = ItTicket::create([
            'department_id' => $department->id,
            'opened_by' => $user->id,
            'requester_name' => 'Servidor',
            'description' => 'Chamado somente leitura',
            'priority' => 'media',
            'status' => 'aberto',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/it/tickets?kanban=1')->assertOk();
        $this->postJson("/api/it/tickets/{$ticket->id}/status", ['status' => 'triagem'])->assertForbidden();
    }

    public function test_it_categories_can_be_managed_hierarchically(): void
    {
        [$user] = $this->itContext();

        Sanctum::actingAs($user);

        $parentId = $this->postJson('/api/it/categories', [
            'name' => 'Hardware',
            'description' => 'Equipamentos fisicos',
            'color' => 'primary',
            'icon' => 'memory',
            'sort_order' => 10,
        ])
            ->assertCreated()
            ->assertJsonPath('name', 'Hardware')
            ->json('id');

        $this->postJson('/api/it/categories', [
            'parent_id' => $parentId,
            'name' => 'Impressora',
            'sort_order' => 20,
        ])
            ->assertCreated()
            ->assertJsonPath('parent.id', $parentId);

        $this->getJson('/api/it/categories')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Hardware')
            ->assertJsonPath('data.0.children_count', 1);
    }

    public function test_ticket_category_id_keeps_legacy_category_text_in_sync(): void
    {
        [$user, $department] = $this->itContext();
        $category = ItTicketCategory::create([
            'name' => 'Sistema interno',
            'slug' => 'sistema-interno',
            'active' => true,
        ]);

        Sanctum::actingAs($user);

        $ticketId = $this->postJson('/api/it/tickets', [
            'department_id' => $department->id,
            'category_id' => $category->id,
            'requester_name' => 'Servidor Teste',
            'description' => 'Erro ao acessar sistema interno',
            'priority' => 'media',
        ])
            ->assertCreated()
            ->assertJsonPath('category_id', $category->id)
            ->assertJsonPath('category_model.name', 'Sistema interno')
            ->assertJsonPath('category', 'Sistema interno')
            ->json('id');

        $this->assertDatabaseHas('it_tickets', [
            'id' => $ticketId,
            'category_id' => $category->id,
            'category' => 'Sistema interno',
        ]);
    }

    public function test_ticket_type_is_validated_filterable_and_reported(): void
    {
        [$user, $department] = $this->itContext();

        Sanctum::actingAs($user);

        $ticketId = $this->postJson('/api/it/tickets', [
            'department_id' => $department->id,
            'type' => 'instalacao',
            'requester_name' => 'Servidor Teste',
            'description' => 'Instalar pacote Office',
            'priority' => 'media',
        ])
            ->assertCreated()
            ->assertJsonPath('type', 'instalacao')
            ->json('id');

        $this->postJson('/api/it/tickets', [
            'department_id' => $department->id,
            'type' => 'tipo-invalido',
            'requester_name' => 'Servidor Teste',
            'description' => 'Tipo invalido',
            'priority' => 'media',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');

        $this->getJson('/api/it/tickets?type=instalacao')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ticketId);

        $this->getJson('/api/it/tickets/summary')
            ->assertOk()
            ->assertJsonPath('by_type.instalacao', 1);

        $this->getJson('/api/reports/preview?report=it_tickets&type=instalacao')
            ->assertOk()
            ->assertJsonPath('headings.3', 'Tipo')
            ->assertJsonPath('rows.0.3', 'instalacao')
            ->assertJsonPath('total', 1);
    }

    public function test_managerial_help_desk_reports_are_available_with_shared_filters(): void
    {
        [$user, $department] = $this->itContext();
        ItTicket::create([
            'department_id' => $department->id,
            'opened_by' => $user->id,
            'assigned_to' => $user->id,
            'requester_name' => 'Solicitante',
            'description' => 'Falha recorrente no equipamento',
            'category' => 'Hardware',
            'type' => 'incidente',
            'priority' => 'alta',
            'status' => 'resolvido',
            'source' => 'manual',
            'recurring_problem' => true,
            'identified_cause' => 'Superaquecimento',
            'created_at' => now()->subDay(),
            'first_response_at' => now()->subHours(20),
            'resolved_at' => now()->subHours(2),
            'service_time_minutes' => 60,
        ]);
        Sanctum::actingAs($user);

        $reports = ['it_sla', 'it_technicians', 'it_departments', 'it_categories', 'it_types', 'it_recurrence', 'it_assets', 'it_evaluations', 'it_root_causes', 'it_metrics', 'it_backlog', 'it_aging'];
        foreach ($reports as $report) {
            $this->getJson("/api/reports/preview?report={$report}&date_from=".now()->subDays(2)->toDateString())
                ->assertOk()
                ->assertJsonStructure(['title', 'headings', 'rows', 'total']);
        }

        $this->getJson('/api/reports/options')
            ->assertOk()
            ->assertJsonFragment(['value' => 'it_metrics', 'label' => 'Indicadores MTTA e MTTR']);
    }

    public function test_it_options_expose_structured_ticket_types(): void
    {
        [$user] = $this->itContext();

        Sanctum::actingAs($user);

        $this->getJson('/api/it/options')
            ->assertOk()
            ->assertJsonPath('types.0.value', 'incidente')
            ->assertJsonPath('types.0.label', 'Incidente')
            ->assertJsonPath('types.7.value', 'mudanca');
    }

    public function test_category_used_by_tickets_is_deactivated_instead_of_deleted(): void
    {
        [$user, $department] = $this->itContext();
        $category = ItTicketCategory::create([
            'name' => 'Rede',
            'slug' => 'rede',
            'active' => true,
        ]);

        ItTicket::create([
            'department_id' => $department->id,
            'opened_by' => $user->id,
            'assigned_to' => $user->id,
            'category_id' => $category->id,
            'category' => $category->name,
            'requester_name' => 'Servidor Teste',
            'description' => 'Internet indisponivel',
            'priority' => 'alta',
            'status' => 'triagem',
            'source' => 'manual',
            'sla_due_at' => now()->addHours(8),
        ]);

        Sanctum::actingAs($user);

        $this->deleteJson("/api/it/categories/{$category->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Categoria em uso por chamados. Ela foi desativada.');

        $this->assertDatabaseHas('it_ticket_categories', [
            'id' => $category->id,
            'active' => false,
        ]);
    }

    public function test_sla_rules_can_be_managed_through_api(): void
    {
        [$user, $department] = $this->itContext();
        $category = ItTicketCategory::create([
            'name' => 'Rede',
            'slug' => 'rede',
            'active' => true,
        ]);
        $calendar = ItSlaCalendar::query()->firstOrFail();

        Sanctum::actingAs($user);

        $ruleId = $this->postJson('/api/it/sla-rules', [
            'calendar_id' => $calendar->id,
            'name' => 'Rede urgente',
            'priority' => 'urgente',
            'category_id' => $category->id,
            'type' => 'incidente',
            'department_id' => $department->id,
            'first_response_minutes' => 15,
            'start_minutes' => 30,
            'resolution_minutes' => 120,
            'rule_priority' => 80,
            'active' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('name', 'Rede urgente')
            ->assertJsonPath('calendar.id', $calendar->id)
            ->json('id');

        $this->putJson("/api/it/sla-rules/{$ruleId}", [
            'resolution_minutes' => 180,
            'rule_priority' => 90,
        ])
            ->assertOk()
            ->assertJsonPath('resolution_minutes', 180)
            ->assertJsonPath('rule_priority', 90);

        $this->getJson('/api/it/sla-rules')
            ->assertOk()
            ->assertJsonPath('data.data.0.id', $ruleId);
    }

    public function test_ticket_creation_uses_most_specific_sla_rule_and_sets_due_dates(): void
    {
        [$user, $department] = $this->itContext();
        $category = ItTicketCategory::create([
            'name' => 'Sistema interno',
            'slug' => 'sistema-interno',
            'active' => true,
        ]);
        $calendar = ItSlaCalendar::query()->firstOrFail();

        ItSlaRule::query()->create([
            'calendar_id' => $calendar->id,
            'name' => 'Alta generica',
            'priority' => 'alta',
            'first_response_minutes' => 60,
            'resolution_minutes' => 480,
            'rule_priority' => 10,
            'active' => true,
        ]);
        $specific = ItSlaRule::query()->create([
            'calendar_id' => $calendar->id,
            'name' => 'Sistema interno alta',
            'priority' => 'alta',
            'category_id' => $category->id,
            'type' => 'problema',
            'department_id' => $department->id,
            'first_response_minutes' => 20,
            'start_minutes' => 40,
            'resolution_minutes' => 180,
            'rule_priority' => 20,
            'active' => true,
        ]);

        Sanctum::actingAs($user);
        Carbon::setTestNow(Carbon::parse('2026-09-08 10:00:00'));

        try {
            $ticketId = $this->postJson('/api/it/tickets', [
                'department_id' => $department->id,
                'category_id' => $category->id,
                'type' => 'problema',
                'requester_name' => 'Servidor Teste',
                'description' => 'Sistema interno apresenta falha intermitente',
                'priority' => 'alta',
            ])
                ->assertCreated()
                ->assertJsonPath('sla_rule_id', $specific->id)
                ->json('id');
        } finally {
            Carbon::setTestNow();
        }

        $ticket = ItTicket::query()->findOrFail($ticketId);

        $this->assertSame($specific->id, $ticket->sla_rule_id);
        $this->assertNotNull($ticket->first_response_due_at);
        $this->assertNotNull($ticket->start_due_at);
        $this->assertNotNull($ticket->sla_due_at);
    }

    public function test_sla_service_uses_configured_fallback_when_no_specific_rule_matches(): void
    {
        [$user, $department] = $this->itContext();
        $calendar = ItSlaCalendar::query()->firstOrFail();
        ItSlaRule::query()->update(['active' => false]);
        $fallback = ItSlaRule::query()->create([
            'calendar_id' => $calendar->id,
            'name' => 'Fallback teste',
            'first_response_minutes' => 90,
            'resolution_minutes' => 360,
            'rule_priority' => -10,
            'is_fallback' => true,
            'active' => true,
        ]);

        Sanctum::actingAs($user);

        $ticketId = $this->postJson('/api/it/tickets', [
            'department_id' => $department->id,
            'requester_name' => 'Servidor Teste',
            'description' => 'Chamado sem regra especifica',
            'priority' => 'baixa',
        ])
            ->assertCreated()
            ->assertJsonPath('sla_rule_id', $fallback->id)
            ->json('id');

        $this->assertDatabaseHas('it_tickets', [
            'id' => $ticketId,
            'sla_rule_id' => $fallback->id,
        ]);
    }

    public function test_fallback_rules_cannot_be_scoped_and_are_not_selected_before_matching_rules(): void
    {
        [$user, $department] = $this->itContext();
        $calendar = ItSlaCalendar::query()->firstOrFail();
        Sanctum::actingAs($user);

        $this->postJson('/api/it/sla-rules', [
            'calendar_id' => $calendar->id,
            'name' => 'Fallback indevido',
            'priority' => 'urgente',
            'first_response_minutes' => 10,
            'resolution_minutes' => 20,
            'is_fallback' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('is_fallback');

        ItSlaRule::query()->update(['active' => false]);
        $generic = ItSlaRule::create([
            'calendar_id' => $calendar->id,
            'name' => 'Regra geral',
            'first_response_minutes' => 30,
            'resolution_minutes' => 60,
            'active' => true,
        ]);
        $fallback = ItSlaRule::create([
            'calendar_id' => $calendar->id,
            'name' => 'Fallback',
            'first_response_minutes' => 5,
            'resolution_minutes' => 10,
            'is_fallback' => true,
            'active' => true,
        ]);

        $resolved = app(SlaService::class)->resolveRule([
            'priority' => 'baixa',
            'department_id' => $department->id,
        ]);

        $this->assertSame($generic->id, $resolved->id);
        $this->assertNotSame($fallback->id, $resolved->id);
    }

    public function test_global_search_finds_ticket_by_number_and_description(): void
    {
        [$user, $department] = $this->itContext();
        $ticket = ItTicket::create([
            'department_id' => $department->id,
            'opened_by' => $user->id,
            'assigned_to' => $user->id,
            'requester_name' => 'Solicitante Busca',
            'description' => 'Falha no roteador do setor',
            'status' => 'triagem',
            'source' => 'manual',
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/search?q='.urlencode(str_pad((string) $ticket->id, 2, '0', STR_PAD_LEFT)))
            ->assertOk()
            ->assertJsonPath('tickets.0.id', $ticket->id);
        $this->getJson('/api/search?q=roteador')
            ->assertOk()
            ->assertJsonPath('tickets.0.id', $ticket->id);
    }

    public function test_bulk_action_updates_priority_and_closes_selected_tickets(): void
    {
        [$user, $department] = $this->itContext();
        $tickets = collect(range(1, 2))->map(fn () => ItTicket::create([
            'department_id' => $department->id,
            'opened_by' => $user->id,
            'requester_name' => 'Solicitante',
            'description' => 'Chamado em massa',
            'status' => 'triagem',
            'source' => 'manual',
        ]));
        Sanctum::actingAs($user);

        $this->postJson('/api/it/tickets/bulk-action', ['ticket_ids' => $tickets->pluck('id')->all(), 'action' => 'priority', 'value' => 'urgente'])
            ->assertOk()->assertJsonPath('updated', 2);
        $this->postJson('/api/it/tickets/bulk-action', ['ticket_ids' => $tickets->pluck('id')->all(), 'action' => 'close', 'reason' => 'Encerramento em lote'])
            ->assertOk()->assertJsonPath('updated', 2);
        $this->assertDatabaseHas('it_tickets', ['id' => $tickets->first()->id, 'priority' => 'urgente', 'status' => 'encerrado']);
        $this->assertDatabaseHas('it_ticket_status_histories', ['ticket_id' => $tickets->first()->id, 'to_status' => 'encerrado']);
    }

    /**
     * @return array{0: User, 1: Department}
     */
    private function itContext(): array
    {
        $department = Department::create(['name' => 'Informatica', 'slug' => 'gerencia-de-informatica']);
        $user = User::factory()->create(['department_id' => $department->id]);
        $user->assignRole('Gerente Informatica');

        return [$user, $department];
    }
}
