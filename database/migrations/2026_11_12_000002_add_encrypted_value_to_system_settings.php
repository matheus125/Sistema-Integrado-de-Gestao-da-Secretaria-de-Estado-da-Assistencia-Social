<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->longText('encrypted_value')->nullable()->after('value');
            $table->timestamp('secret_encrypted_at')->nullable()->after('is_secret');
        });
    }

    public function down(): void
    {
        $restored = DB::table('system_settings')
            ->whereNotNull('encrypted_value')
            ->get(['id', 'key', 'encrypted_value'])
            ->map(function (object $setting): array {
                $payload = Crypt::decrypt($setting->encrypted_value);

                if (! is_array($payload) || ! array_key_exists('value', $payload)) {
                    throw new RuntimeException("Cannot restore encrypted setting [{$setting->key}].");
                }

                return [
                    'id' => $setting->id,
                    'value' => json_encode(['value' => $payload['value']], JSON_THROW_ON_ERROR),
                ];
            });

        DB::transaction(function () use ($restored): void {
            foreach ($restored as $setting) {
                DB::table('system_settings')
                    ->where('id', $setting['id'])
                    ->update(['value' => $setting['value']]);
            }
        });

        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn(['encrypted_value', 'secret_encrypted_at']);
        });
    }
};
