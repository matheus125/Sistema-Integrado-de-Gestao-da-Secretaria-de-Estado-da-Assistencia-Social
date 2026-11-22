<?php

namespace App\Services\Settings;

use App\Models\SystemSetting;

class SettingValue
{
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = SystemSetting::query()->where('key', $key)->first();

        return $setting?->resolvedValue($default) ?? $default;
    }
}
