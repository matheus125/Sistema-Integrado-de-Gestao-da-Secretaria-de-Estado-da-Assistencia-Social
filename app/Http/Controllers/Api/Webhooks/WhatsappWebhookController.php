<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\It\WhatsappOutboundService;
use App\Services\It\WhatsappTicketService;
use App\Services\It\WhatsappVerifyToken;
use App\Services\Settings\SettingValue;
use App\Support\BrazilianPhone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WhatsappWebhookController extends Controller
{
    public function verify(Request $request): Response|JsonResponse
    {
        $mode = $request->query('hub_mode') ?? $request->query('hub.mode');
        $token = $request->query('hub_verify_token') ?? $request->query('hub.verify_token');
        $challenge = $request->query('hub_challenge') ?? $request->query('hub.challenge');
        $expectedToken = SettingValue::get('whatsapp_business_verify_token');

        if (! WhatsappVerifyToken::isSecure($expectedToken)) {
            return response()->json(['message' => 'Webhook verification unavailable.'], 503);
        }

        if ($mode === 'subscribe' && is_string($token) && hash_equals($expectedToken, $token)) {
            return response((string) $challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response()->json(['message' => 'Invalid verify token.'], 403);
    }

    public function receive(Request $request, WhatsappTicketService $service, WhatsappOutboundService $outbound): JsonResponse
    {
        if (! SettingValue::get('whatsapp_business_enabled', false)) {
            return response()->json(['message' => 'WhatsApp integration disabled.'], 202);
        }

        $payload = $request->all();
        foreach ($this->messagesFromPayload($payload) as $message) {
            $service->receiveTextMessage(
                phone: $message['phone'],
                name: $message['name'],
                message: $message['text'],
                metadata: $message['metadata'],
            );
        }

        foreach (data_get($payload, 'entry', []) as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                foreach (data_get($change, 'value.statuses', []) as $status) {
                    if (filled($status['id'] ?? null) && filled($status['status'] ?? null)) {
                        $outbound->updateDeliveryStatus((string) $status['id'], (string) $status['status'], data_get($status, 'errors.0.title'));
                    }
                }
            }
        }

        return response()->json(['status' => 'received']);
    }

    /**
     * @return array<int, array{phone: string, name: string, text: string, metadata: array<string, mixed>}>
     */
    private function messagesFromPayload(array $payload): array
    {
        $messages = [];

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];
                $contacts = collect($value['contacts'] ?? [])->keyBy('wa_id');

                foreach ($value['messages'] ?? [] as $message) {
                    if (($message['type'] ?? null) !== 'text') {
                        Log::info('whatsapp unsupported message ignored', ['type' => $message['type'] ?? null]);

                        continue;
                    }

                    $phone = (string) ($message['from'] ?? '');
                    $contact = $contacts->get($phone, []);
                    $text = trim((string) data_get($message, 'text.body', ''));

                    if (! BrazilianPhone::isValid($phone) || $text === '') {
                        if ($phone !== '' && $text !== '') {
                            Log::warning('whatsapp message with invalid Brazilian phone ignored');
                        }

                        continue;
                    }

                    $messages[] = [
                        'phone' => $phone,
                        'name' => (string) data_get($contact, 'profile.name', $phone),
                        'text' => $text,
                        'metadata' => [
                            'message_id' => $message['id'] ?? null,
                            'timestamp' => $message['timestamp'] ?? null,
                            'phone_number_id' => data_get($value, 'metadata.phone_number_id'),
                            'display_phone_number' => data_get($value, 'metadata.display_phone_number'),
                        ],
                    ];
                }
            }
        }

        return $messages;
    }
}
