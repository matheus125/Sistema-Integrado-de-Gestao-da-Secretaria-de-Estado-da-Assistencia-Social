<?php

namespace App\Services\It;

use App\Models\ItTicket;
use App\Models\WhatsappMessageLog;
use App\Models\WhatsappMessageTemplate;
use App\Models\ItNotificationLog;
use App\Jobs\SendWhatsappMessage;
use App\Services\Settings\SettingValue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappOutboundService
{
    public function queue(ItTicket $ticket, string $recipient, string $message, string $event = 'manual'): void
    {
        SendWhatsappMessage::dispatch($ticket, $recipient, $message, $event);
    }

    public function updateDeliveryStatus(string $apiMessageId, string $status, ?string $error = null): ?WhatsappMessageLog
    {
        $log = WhatsappMessageLog::query()->where('api_message_id', $apiMessageId)->first();
        if (! $log) {
            return null;
        }

        $previous = $log->status;
        $log->update([
            'status' => match ($status) {
                'delivered', 'read' => $status,
                'failed' => 'failed',
                default => $log->status,
            },
            'error' => $error ?: $log->error,
            'sent_at' => in_array($status, ['sent', 'delivered', 'read'], true) ? ($log->sent_at ?: now()) : $log->sent_at,
        ]);

        if ($log->ticket_id && $log->status !== $previous && in_array($log->status, ['sent', 'delivered', 'read', 'failed'], true)
            && ! ItNotificationLog::query()->where('ticket_id', $log->ticket_id)->where('event', 'whatsapp_'.$log->status)->whereJsonContains('metadata->api_message_id', $apiMessageId)->exists()) {
            app(ItNotificationService::class)->ticketEvent($log->ticket()->firstOrFail(), 'whatsapp_'.$log->status, "Mensagem WhatsApp {$log->status} no chamado #{$log->ticket_id}.", null, ['api_message_id' => $apiMessageId]);
        }

        return $log->refresh();
    }

    public function send(ItTicket $ticket, string $recipient, string $message, string $event = 'manual'): WhatsappMessageLog
    {
        $log = WhatsappMessageLog::create([
            'ticket_id' => $ticket->id,
            'recipient' => $recipient,
            'message' => $message,
            'status' => 'pending',
            'attempts' => 1,
            'metadata' => ['event' => $event],
        ]);

        if (! SettingValue::get('whatsapp_business_enabled', false)) {
            return tap($log)->update(['status' => 'skipped', 'error' => 'Integracao WhatsApp desabilitada.']);
        }

        $phoneNumberId = SettingValue::get('whatsapp_business_phone_number_id');
        $token = SettingValue::get('whatsapp_business_token');

        if (blank($phoneNumberId) || blank($token)) {
            return tap($log)->update(['status' => 'failed', 'error' => 'Credenciais do WhatsApp Business nao configuradas.']);
        }

        try {
            $response = Http::retry(3, 150)
                ->withToken($token)
                ->acceptJson()
                ->post("https://graph.facebook.com/v20.0/{$phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => $recipient,
                    'type' => 'text',
                    'text' => ['body' => $message],
                ]);

            if ($response->successful()) {
                $log->update([
                    'status' => 'sent',
                    'attempts' => 1,
                    'api_message_id' => data_get($response->json(), 'messages.0.id'),
                    'sent_at' => now(),
                    'metadata' => ['event' => $event, 'response' => $response->json()],
                ]);
            } else {
                $log->update(['status' => 'failed', 'attempts' => 3, 'error' => $response->body()]);
                Log::warning('whatsapp outbound failed', ['ticket_id' => $ticket->id, 'status' => $response->status()]);
            }
        } catch (\Throwable $exception) {
            $log->update(['status' => 'failed', 'attempts' => 3, 'error' => $exception->getMessage()]);
            Log::error('whatsapp outbound exception', ['ticket_id' => $ticket->id, 'error' => $exception->getMessage()]);
        }

        return $log->refresh();
    }

    public function renderTemplate(string $key, array $variables = []): string
    {
        $template = WhatsappMessageTemplate::query()->where('key', $key)->where('active', true)->firstOrFail();
        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', fn (array $match) => (string) ($variables[$match[1]] ?? $match[0]), $template->body);
    }

    public function sendTemplate(ItTicket $ticket, string $recipient, string $key, array $variables = [], string $event = 'template'): WhatsappMessageLog
    {
        return $this->send($ticket, $recipient, $this->renderTemplate($key, $variables), $event);
    }
}
