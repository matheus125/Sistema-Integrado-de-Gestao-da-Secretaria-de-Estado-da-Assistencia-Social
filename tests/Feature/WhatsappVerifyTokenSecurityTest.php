<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use App\Services\It\WhatsappVerifyToken;
use App\Services\Settings\SettingValue;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class WhatsappVerifyTokenSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const STRONG_TOKEN = '91af7c3e50bd2846c19e73a5f802dc4b6e15a9f37c24d80b';

    public function test_policy_rejects_missing_legacy_short_and_trivial_tokens(): void
    {
        $this->assertFalse(WhatsappVerifyToken::isSecure(null));
        $this->assertFalse(WhatsappVerifyToken::isSecure(''));
        $this->assertFalse(WhatsappVerifyToken::isSecure('siges-webhook-token'));
        $this->assertFalse(WhatsappVerifyToken::isSecure('short-token'));
        $this->assertFalse(WhatsappVerifyToken::isSecure(str_repeat('a', 64)));
        $this->assertFalse(WhatsappVerifyToken::isSecure(str_repeat('abcd1234', 8)));
        $this->assertTrue(WhatsappVerifyToken::isSecure(self::STRONG_TOKEN));
    }

    public function test_seeder_uses_only_a_strong_environment_token_and_has_no_fixed_fallback(): void
    {
        config()->set('services.whatsapp.verify_token', null);
        $this->seed(SystemSettingSeeder::class);

        $this->assertSame('', SettingValue::get('whatsapp_business_verify_token'));

        SystemSetting::query()->where('key', 'whatsapp_business_verify_token')->delete();
        config()->set('services.whatsapp.verify_token', self::STRONG_TOKEN);
        $this->seed(SystemSettingSeeder::class);

        $this->assertSame(self::STRONG_TOKEN, SettingValue::get('whatsapp_business_verify_token'));
    }

    public function test_seeder_rejects_an_insecure_environment_token(): void
    {
        config()->set('services.whatsapp.verify_token', 'siges-webhook-token');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('WHATSAPP_BUSINESS_VERIFY_TOKEN must be a strong, non-default value.');

        $this->seed(SystemSettingSeeder::class);
    }

    public function test_webhook_verification_fails_closed_without_a_strong_token(): void
    {
        $this->get('/api/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=siges-webhook-token&hub_challenge=unsafe')
            ->assertServiceUnavailable()
            ->assertJson(['message' => 'Webhook verification unavailable.']);

        $this->storeVerifyToken('siges-webhook-token');

        $this->get('/api/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=siges-webhook-token&hub_challenge=unsafe')
            ->assertServiceUnavailable();
    }

    public function test_webhook_verification_accepts_only_the_configured_strong_token(): void
    {
        $this->storeVerifyToken(self::STRONG_TOKEN);

        $this->get('/api/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=wrong-token&hub_challenge=challenge-123')
            ->assertForbidden();

        $this->get('/api/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token='.self::STRONG_TOKEN.'&hub_challenge=challenge-123')
            ->assertOk()
            ->assertContent('challenge-123');
    }

    public function test_settings_api_blocks_activation_until_a_strong_token_is_saved(): void
    {
        config()->set('services.whatsapp.verify_token', null);
        $this->seed(SystemSettingSeeder::class);
        $this->authenticateSettingsAdministrator();

        $this->putJson('/api/settings', ['settings' => [[
            'key' => 'whatsapp_business_enabled',
            'value' => true,
        ]]])->assertUnprocessable()
            ->assertJsonValidationErrors('settings.whatsapp_business_enabled');

        $this->assertFalse(SettingValue::get('whatsapp_business_enabled'));

        $this->putJson('/api/settings', ['settings' => [[
            'key' => 'whatsapp_business_verify_token',
            'value' => 'siges-webhook-token',
        ]]])->assertUnprocessable()
            ->assertJsonValidationErrors('settings.whatsapp_business_verify_token');

        $this->putJson('/api/settings', ['settings' => [
            ['key' => 'whatsapp_business_enabled', 'value' => true],
            ['key' => 'whatsapp_business_verify_token', 'value' => self::STRONG_TOKEN],
        ]])->assertOk();

        $this->assertTrue(SettingValue::get('whatsapp_business_enabled'));
        $this->assertSame(self::STRONG_TOKEN, SettingValue::get('whatsapp_business_verify_token'));
    }

    public function test_production_check_reports_insecure_and_secure_verify_token_states(): void
    {
        $this->storeVerifyToken('siges-webhook-token');

        $this->artisan('siges:production-check')
            ->expectsOutputToContain('FAIL Strong WhatsApp webhook verify token')
            ->assertFailed();

        SystemSetting::query()
            ->where('key', 'whatsapp_business_verify_token')
            ->firstOrFail()
            ->setSecretValue(self::STRONG_TOKEN)
            ->save();

        $this->artisan('siges:production-check')
            ->expectsOutputToContain('OK   Strong WhatsApp webhook verify token')
            ->assertFailed();
    }

    private function authenticateSettingsAdministrator(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Administrador Geral');
        Sanctum::actingAs($user);
    }

    private function storeVerifyToken(string $token): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'whatsapp_business_verify_token'],
            [
                'group' => 'whatsapp',
                'label' => 'Verify Token do webhook',
                'type' => 'password',
                'value' => ['value' => $token],
                'encrypted_value' => null,
                'secret_encrypted_at' => null,
                'is_secret' => true,
            ],
        );
    }
}
