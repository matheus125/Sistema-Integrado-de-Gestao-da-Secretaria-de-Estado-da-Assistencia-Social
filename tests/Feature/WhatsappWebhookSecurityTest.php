<?php

namespace Tests\Feature;

use App\Models\ItTicket;
use App\Models\SystemSetting;
use App\Models\WhatsappMessageLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class WhatsappWebhookSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const APP_SECRET = 'fake-meta-app-secret-for-tests';

    private const VERIFY_TOKEN = '67c3d8510f4a92be76d1c3508ae429fb65c7d1083e2a94bf';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.whatsapp.app_secret', self::APP_SECRET);
        $this->setWhatsappSetting('whatsapp_business_enabled', true, 'boolean');
    }

    public function test_valid_signature_accepts_and_processes_a_legitimate_event(): void
    {
        $payload = $this->messagePayload('wamid.valid-001');

        $this->postSignedWebhook($payload)->assertOk()->assertJson(['status' => 'received']);

        $this->assertDatabaseCount('it_tickets', 1);
        $this->assertDatabaseCount('it_ticket_messages', 1);
        $this->assertDatabaseHas('it_ticket_messages', [
            'direction' => 'inbound',
            'provider_message_id' => 'wamid.valid-001',
        ]);
    }

    public function test_valid_signed_event_with_invalid_brazilian_phone_is_ignored(): void
    {
        $payload = $this->messagePayload('wamid.invalid-phone-001', phone: '5520999990000');

        $this->postSignedWebhook($payload)->assertOk()->assertJson(['status' => 'received']);

        $this->assertDatabaseCount('it_tickets', 0);
        $this->assertDatabaseCount('it_ticket_messages', 0);
    }

    public function test_invalid_signature_is_rejected_without_database_changes(): void
    {
        $payload = $this->messagePayload('wamid.invalid-001');

        $this->postRawWebhook($payload, 'sha256='.str_repeat('0', 64))->assertUnauthorized();

        $this->assertDatabaseCount('it_tickets', 0);
        $this->assertDatabaseCount('it_ticket_messages', 0);
    }

    public function test_missing_signature_is_rejected_without_database_changes(): void
    {
        $this->postRawWebhook($this->messagePayload('wamid.missing-001'))
            ->assertUnauthorized();

        $this->assertDatabaseCount('it_tickets', 0);
        $this->assertDatabaseCount('it_ticket_messages', 0);
    }

    public function test_missing_app_secret_fails_closed_without_database_changes(): void
    {
        config()->set('services.whatsapp.app_secret', null);

        $this->postRawWebhook(
            $this->messagePayload('wamid.no-secret-001'),
            'sha256='.str_repeat('a', 64),
        )->assertServiceUnavailable();

        $this->assertDatabaseCount('it_tickets', 0);
        $this->assertDatabaseCount('it_ticket_messages', 0);
    }

    public function test_tampered_payload_is_rejected(): void
    {
        $originalPayload = $this->messagePayload('wamid.tampered-001', 'Mensagem original');
        $signature = $this->signatureFor($originalPayload);
        $tamperedPayload = $this->messagePayload('wamid.tampered-001', 'Mensagem adulterada');

        $this->postRawWebhook($tamperedPayload, $signature)->assertUnauthorized();

        $this->assertDatabaseCount('it_tickets', 0);
        $this->assertDatabaseCount('it_ticket_messages', 0);
    }

    public function test_repeated_provider_event_does_not_duplicate_ticket_or_message(): void
    {
        $payload = $this->messagePayload('wamid.repeated-001');

        $this->postSignedWebhook($payload)->assertOk();
        ItTicket::query()->firstOrFail()->update(['status' => 'encerrado']);
        $this->postSignedWebhook($payload)->assertOk();

        $this->assertDatabaseCount('it_tickets', 1);
        $this->assertDatabaseCount('it_ticket_messages', 1);
    }

    public function test_repeated_legacy_event_is_found_by_metadata_during_migration_transition(): void
    {
        $ticket = ItTicket::create([
            'requester_name' => 'Solicitante Teste',
            'requester_phone' => '92999999999',
            'description' => 'Preciso de suporte',
            'status' => 'encerrado',
            'source' => 'whatsapp',
            'whatsapp_conversation_id' => '5592999999999',
        ]);
        $ticket->messages()->create([
            'direction' => 'inbound',
            'message' => 'Preciso de suporte',
            'metadata' => ['message_id' => 'wamid.legacy-001'],
        ]);

        $this->postSignedWebhook($this->messagePayload('wamid.legacy-001'))->assertOk();

        $this->assertDatabaseCount('it_tickets', 1);
        $this->assertDatabaseCount('it_ticket_messages', 1);
    }

    public function test_rejected_request_does_not_change_delivery_status(): void
    {
        $log = WhatsappMessageLog::create([
            'recipient' => '5592999999999',
            'message' => 'Resposta de teste',
            'status' => 'sent',
            'api_message_id' => 'wamid.outbound-001',
            'attempts' => 1,
        ]);

        $this->postRawWebhook(
            $this->statusPayload('wamid.outbound-001', 'delivered'),
            'sha256='.str_repeat('f', 64),
        )->assertUnauthorized();

        $this->assertSame('sent', $log->fresh()->status);
        $this->assertDatabaseCount('it_tickets', 0);
        $this->assertDatabaseCount('it_ticket_messages', 0);
    }

    public function test_valid_signed_delivery_status_event_continues_to_work(): void
    {
        $log = WhatsappMessageLog::create([
            'recipient' => '5592999999999',
            'message' => 'Resposta de teste',
            'status' => 'sent',
            'api_message_id' => 'wamid.outbound-002',
            'attempts' => 1,
        ]);

        $this->postSignedWebhook($this->statusPayload('wamid.outbound-002', 'delivered'))->assertOk();

        $this->assertSame('delivered', $log->fresh()->status);
    }

    public function test_initial_webhook_verification_remains_available_without_app_secret(): void
    {
        config()->set('services.whatsapp.app_secret', null);
        $this->setWhatsappSetting('whatsapp_business_verify_token', self::VERIFY_TOKEN, 'password');

        $this->get('/api/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token='.self::VERIFY_TOKEN.'&hub_challenge=challenge-123')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertContent('challenge-123');
    }

    private function postSignedWebhook(string $payload): TestResponse
    {
        return $this->postRawWebhook($payload, $this->signatureFor($payload));
    }

    private function postRawWebhook(string $payload, ?string $signature = null): TestResponse
    {
        $server = ['CONTENT_TYPE' => 'application/json'];

        if ($signature !== null) {
            $server['HTTP_X_HUB_SIGNATURE_256'] = $signature;
        }

        return $this->call('POST', '/api/webhooks/whatsapp', [], [], [], $server, $payload);
    }

    private function signatureFor(string $payload): string
    {
        return 'sha256='.hash_hmac('sha256', $payload, self::APP_SECRET);
    }

    private function messagePayload(
        string $messageId,
        string $message = 'Preciso de suporte',
        string $phone = '5592999999999',
    ): string {
        return $this->encodePayload([
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'waba-test-id',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => [
                            'display_phone_number' => '5592000000000',
                            'phone_number_id' => 'phone-number-test-id',
                        ],
                        'contacts' => [[
                            'wa_id' => $phone,
                            'profile' => ['name' => 'Solicitante Teste'],
                        ]],
                        'messages' => [[
                            'from' => $phone,
                            'id' => $messageId,
                            'timestamp' => '1794441600',
                            'type' => 'text',
                            'text' => ['body' => $message],
                        ]],
                    ],
                ]],
            ]],
        ]);
    }

    private function statusPayload(string $messageId, string $status): string
    {
        return $this->encodePayload([
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'waba-test-id',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'statuses' => [[
                            'id' => $messageId,
                            'status' => $status,
                        ]],
                    ],
                ]],
            ]],
        ]);
    }

    private function encodePayload(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function setWhatsappSetting(string $key, mixed $value, string $type): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => $key],
            [
                'group' => 'whatsapp',
                'label' => $key,
                'type' => $type,
                'value' => ['value' => $value],
                'is_secret' => $type === 'password',
            ],
        );
    }
}
