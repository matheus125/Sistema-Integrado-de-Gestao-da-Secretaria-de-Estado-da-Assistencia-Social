<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\ItEscalationRule;
use App\Models\ItKnowledgeArticle;
use App\Models\ItNotificationLog;
use App\Models\WhatsappMessageLog;
use App\Models\WhatsappMessageTemplate;
use App\Models\ItSupportTeam;
use App\Models\ItTicketCategory;
use App\Models\ItTicket;
use App\Services\It\EscalationService;
use App\Services\It\WhatsappTicketService;
use App\Services\It\WhatsappOutboundService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ItAdvancedOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_whatsapp_inbound_message_is_idempotent_by_provider_id(): void
    {
        $service = app(WhatsappTicketService::class);

        $first = $service->receiveTextMessage('5592999999999', 'Solicitante', 'Preciso de suporte', [
            'message_id' => 'wamid.test-001',
            'provider' => 'meta',
        ]);
        $second = $service->receiveTextMessage('5592999999999', 'Solicitante', 'Preciso de suporte', [
            'message_id' => 'wamid.test-001',
            'provider' => 'meta',
        ]);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('it_ticket_messages', 1);
        $this->assertDatabaseHas('it_ticket_messages', [
            'ticket_id' => $first->id,
            'direction' => 'inbound',
        ]);
    }

    public function test_escalation_notifies_configured_recipients_once_and_marks_overdue(): void
    {
        $department = Department::create(['name' => 'TI', 'slug' => 'ti-escalacao']);
        $technician = \App\Models\User::factory()->create(['department_id' => $department->id]);
        $technician->assignRole('Servidor Informatica');
        $manager = \App\Models\User::factory()->create(['department_id' => $department->id]);
        $manager->assignRole('Gerente Informatica');

        $rule = ItEscalationRule::create([
            'name' => 'Teste de escalonamento',
            'remaining_percent' => null,
            'minutes_after_due' => 0,
            'level' => 'teste',
            'notify_technician' => true,
            'notify_manager' => true,
            'mark_overdue' => true,
            'active' => true,
        ]);
        $ticket = ItTicket::create([
            'department_id' => $department->id,
            'assigned_to' => $technician->id,
            'requester_name' => 'Solicitante',
            'description' => 'Chamado vencido',
            'category' => 'Teste',
            'priority' => 'alta',
            'status' => 'em_atendimento',
            'source' => 'manual',
            'sla_due_at' => now()->subMinute(),
        ]);

        $service = app(EscalationService::class);
        $this->assertGreaterThanOrEqual(1, $service->process($ticket));
        $this->assertDatabaseHas('it_ticket_escalations', ['ticket_id' => $ticket->id, 'rule_id' => $rule->id]);
        $this->assertDatabaseHas('it_tickets', ['id' => $ticket->id, 'sla_overdue' => 1]);
        $this->assertSame(2, ItNotificationLog::query()
            ->where('ticket_id', $ticket->id)
            ->where('event', 'sla_escalated')
            ->whereJsonContains('metadata->rule_id', $rule->id)
            ->count());

        $service->process($ticket->fresh());
        $this->assertSame(2, ItNotificationLog::query()
            ->where('ticket_id', $ticket->id)
            ->where('event', 'sla_escalated')
            ->whereJsonContains('metadata->rule_id', $rule->id)
            ->count());
    }

    public function test_knowledge_article_can_be_published_and_support_team_keeps_member_settings(): void
    {
        $department = Department::create(['name' => 'TI', 'slug' => 'ti-admin']);
        $manager = \App\Models\User::factory()->create(['department_id' => $department->id]);
        $manager->assignRole('Gerente Informatica');
        $technician = \App\Models\User::factory()->create(['department_id' => $department->id]);
        $technician->assignRole('Servidor Informatica');
        Sanctum::actingAs($manager);

        $team = $this->postJson('/api/it/support-teams', [
            'name' => 'Equipe Teste',
            'technician_ids' => [$technician->id],
            'technician_settings' => [$technician->id => [
                'specialties' => ['rede', 'incidente'],
                'available' => true,
                'max_active_tickets' => 4,
            ]],
        ])->assertCreated()->json();

        $this->assertDatabaseHas('it_support_team_user', [
            'team_id' => $team['id'],
            'user_id' => $technician->id,
            'max_active_tickets' => 4,
        ]);
        $this->assertSame(['rede', 'incidente'], json_decode(ItSupportTeam::find($team['id'])->technicians()->first()->pivot->specialties, true));

        $article = ItKnowledgeArticle::create([
            'author_id' => $manager->id,
            'title' => 'Teste',
            'content' => 'Procedimento',
            'active' => true,
            'public' => false,
        ]);
        $this->postJson("/api/it/knowledge/{$article->id}/publish", ['public' => true])
            ->assertOk()
            ->assertJsonPath('public', true)
            ->assertJsonPath('active', true);
    }

    public function test_whatsapp_template_renders_variables_and_delivery_status_notifies_once(): void
    {
        $department = Department::create(['name' => 'TI', 'slug' => 'ti-whatsapp-status']);
        $user = \App\Models\User::factory()->create(['department_id' => $department->id]);
        $ticket = ItTicket::create(['department_id' => $department->id, 'opened_by' => $user->id, 'requester_name' => 'Solicitante', 'description' => 'Teste WhatsApp', 'status' => 'aberto', 'source' => 'whatsapp', 'whatsapp_conversation_id' => '5592999990000']);
        $template = WhatsappMessageTemplate::create(['name' => 'Resposta', 'key' => 'resposta_teste', 'body' => 'Ola {{nome}}, chamado #{{ticket_id}}.', 'variables' => ['nome', 'ticket_id'], 'active' => true]);
        $service = app(WhatsappOutboundService::class);

        $this->assertSame('Ola Ana, chamado #'.$ticket->id.'.', $service->renderTemplate($template->key, ['nome' => 'Ana', 'ticket_id' => $ticket->id]));
        $log = WhatsappMessageLog::create(['ticket_id' => $ticket->id, 'recipient' => '5592999990000', 'message' => 'Ola', 'status' => 'sent', 'api_message_id' => 'wamid-status-1', 'attempts' => 1]);
        $service->updateDeliveryStatus($log->api_message_id, 'delivered');
        $service->updateDeliveryStatus($log->api_message_id, 'delivered');

        $this->assertDatabaseHas('whatsapp_message_logs', ['id' => $log->id, 'status' => 'delivered']);
        $this->assertSame(1, ItNotificationLog::query()->where('ticket_id', $ticket->id)->where('event', 'whatsapp_delivered')->count());
    }
}
