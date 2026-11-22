<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyWhatsappWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $appSecret = (string) config('services.whatsapp.app_secret', '');

        if ($appSecret === '') {
            Log::error('WhatsApp webhook authentication unavailable.', [
                'reason' => 'app_secret_not_configured',
            ]);

            return new JsonResponse(['message' => 'Webhook unavailable.'], 503);
        }

        $providedSignature = (string) $request->header('X-Hub-Signature-256', '');

        if (! preg_match('/\Asha256=[0-9a-f]{64}\z/', $providedSignature)) {
            $this->logAuthenticationFailure('missing_or_malformed_signature');

            return new JsonResponse(['message' => 'Unauthenticated.'], 401);
        }

        $expectedSignature = 'sha256='.hash_hmac('sha256', $request->getContent(), $appSecret);

        if (! hash_equals($expectedSignature, $providedSignature)) {
            $this->logAuthenticationFailure('signature_mismatch');

            return new JsonResponse(['message' => 'Unauthenticated.'], 401);
        }

        return $next($request);
    }

    private function logAuthenticationFailure(string $reason): void
    {
        Log::warning('WhatsApp webhook authentication failed.', [
            'reason' => $reason,
        ]);
    }
}
