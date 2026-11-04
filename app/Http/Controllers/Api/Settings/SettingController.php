<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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
            'settings.*.value' => ['nullable'],
        ]);

        DB::transaction(function () use ($request, $data) {
            foreach ($data['settings'] as $item) {
                $setting = SystemSetting::query()->where('key', $item['key'])->lockForUpdate()->firstOrFail();

                if ($setting->is_secret && blank($item['value'] ?? null)) {
                    continue;
                }

                $setting->update(['value' => ['value' => $this->castValue($setting->type, $item['value'] ?? null)]]);
            }

            activity('configuracoes')->causedBy($request->user())->log('configuracoes atualizadas');
        });

        return $this->index();
    }

    private function serialize(SystemSetting $setting): array
    {
        $value = $setting->value['value'] ?? null;

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
