<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\It\WhatsappVerifyToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SettingController extends Controller
{
    public function index(): JsonResponse
    {
        $settings = SystemSetting::query()
            ->orderBy('group')
            ->orderBy('label')
            ->get()
            ->groupBy('group')
            ->map(fn ($items) => $items->map(fn (SystemSetting $setting) => $this->serialize($setting))->values())
            ->toArray();

        return response()->json(['groups' => $settings]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*.key' => ['required', 'string', Rule::exists('system_settings', 'key')],
            'settings.*.value' => [
                'nullable',
                static function (string $attribute, mixed $value, \Closure $fail): void {
                    if (is_string($value) && mb_strlen($value) > 4096) {
                        $fail("O campo {$attribute} não pode ter mais que 4096 caracteres.");
                    }
                },
            ],
        ]);

        DB::transaction(function () use ($request, $data): void {
            $requestedKeys = collect($data['settings'])->pluck('key');
            $whatsappKeys = collect([
                'whatsapp_business_enabled',
                'whatsapp_business_verify_token',
            ]);
            $touchesWhatsappSecurity = $requestedKeys->intersect($whatsappKeys)->isNotEmpty();
            $keysToLock = $touchesWhatsappSecurity
                ? $requestedKeys->merge($whatsappKeys)->unique()
                : $requestedKeys;

            $settings = SystemSetting::query()
                ->whereIn('key', $keysToLock)
                ->lockForUpdate()
                ->get()
                ->keyBy('key');
            $updates = [];

            foreach ($data['settings'] as $item) {
                $setting = $settings->get($item['key']);

                if ($setting->is_secret && blank($item['value'] ?? null)) {
                    continue;
                }

                $updates[$setting->key] = [
                    'setting' => $setting,
                    'value' => $this->castValue($setting->type, $item['value'] ?? null),
                ];
            }

            $this->validateTokenSessionSecurity($updates);

            if ($touchesWhatsappSecurity) {
                $this->validateWhatsappSecurity($settings, $updates);
            }

            foreach ($updates as $update) {
                $setting = $update['setting'];
                $value = $update['value'];

                if ($setting->is_secret) {
                    $setting->setSecretValue($value)->save();
                } else {
                    $setting->update(['value' => ['value' => $value]]);
                }
            }

            activity('configuracoes')->causedBy($request->user())->log('configuracoes atualizadas');
        });

        return $this->index();
    }

    /**
     * @param  array<string, array{setting: SystemSetting, value: mixed}>  $updates
     */
    private function validateTokenSessionSecurity(array $updates): void
    {
        if (! array_key_exists('session_timeout_minutes', $updates)) {
            return;
        }

        $timeout = $updates['session_timeout_minutes']['value'];

        if (! is_int($timeout) || $timeout < 5 || $timeout > 1440) {
            throw ValidationException::withMessages([
                'settings.session_timeout_minutes' => 'O tempo de inatividade deve estar entre 5 e 1440 minutos.',
            ]);
        }
    }

    /**
     * @param  Collection<string, SystemSetting>  $settings
     * @param  array<string, array{setting: SystemSetting, value: mixed}>  $updates
     */
    private function validateWhatsappSecurity($settings, array $updates): void
    {
        $tokenUpdate = $updates['whatsapp_business_verify_token']['value'] ?? null;

        if (array_key_exists('whatsapp_business_verify_token', $updates)
            && ! WhatsappVerifyToken::isSecure($tokenUpdate)) {
            throw ValidationException::withMessages([
                'settings.whatsapp_business_verify_token' => 'O Verify Token deve ser exclusivo, não padrão e possuir pelo menos 32 caracteres.',
            ]);
        }

        $enabled = $updates['whatsapp_business_enabled']['value']
            ?? $settings->get('whatsapp_business_enabled')?->resolvedValue(false)
            ?? false;
        $token = array_key_exists('whatsapp_business_verify_token', $updates)
            ? $tokenUpdate
            : $settings->get('whatsapp_business_verify_token')?->resolvedValue();

        if ($enabled && ! WhatsappVerifyToken::isSecure($token)) {
            throw ValidationException::withMessages([
                'settings.whatsapp_business_enabled' => 'Configure um Verify Token forte antes de habilitar a integração WhatsApp.',
            ]);
        }
    }

    private function serialize(SystemSetting $setting): array
    {
        $value = $setting->resolvedValue();

        return [
            'id' => $setting->id,
            'group' => $setting->group,
            'key' => $setting->key,
            'label' => $setting->label,
            'type' => $setting->type,
            'value' => $setting->is_secret && filled($value) ? '' : $value,
            'has_secret' => $setting->is_secret && filled($value),
            'is_secret' => $setting->is_secret,
            'description' => $setting->description,
        ];
    }

    private function castValue(string $type, mixed $value): mixed
    {
        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'number' => is_numeric($value) ? (int) $value : 0,
            default => $value,
        };
    }
}
