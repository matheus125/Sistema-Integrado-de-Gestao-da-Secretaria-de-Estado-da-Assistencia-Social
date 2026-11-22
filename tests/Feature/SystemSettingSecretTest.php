<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Settings\SettingValue;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SystemSettingSecretTest extends TestCase
{
    use RefreshDatabase;

    private const VERIFY_TOKEN = '2f7c10d9a84e63b51f0a927de46c83b5a912e74f05cd61ab';

    private string $backupPath;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.whatsapp.verify_token', self::VERIFY_TOKEN);
        $this->seed(SystemSettingSeeder::class);
        $this->backupPath = storage_path('framework/testing/fix004-backup.sql');
    }

    protected function tearDown(): void
    {
        File::delete($this->backupPath);

        parent::tearDown();
    }

    public function test_secret_is_encrypted_at_rest_and_can_be_read_by_the_integration(): void
    {
        $row = DB::table('system_settings')
            ->where('key', 'whatsapp_business_verify_token')
            ->firstOrFail();

        $this->assertNull($row->value);
        $this->assertNotEmpty($row->encrypted_value);
        $this->assertStringNotContainsString(self::VERIFY_TOKEN, $row->encrypted_value);
        $this->assertSame(self::VERIFY_TOKEN, SettingValue::get('whatsapp_business_verify_token'));
    }

    public function test_api_encrypts_new_secret_masks_response_and_preserves_blank_update(): void
    {
        $this->authenticateSettingsAdministrator();
        $secret = 'fake-whatsapp-access-token';

        $response = $this->putJson('/api/settings', ['settings' => [[
            'key' => 'whatsapp_business_token',
            'value' => $secret,
        ]]])->assertOk();

        $row = DB::table('system_settings')->where('key', 'whatsapp_business_token')->firstOrFail();
        $setting = collect($response->json('groups.whatsapp'))->firstWhere('key', 'whatsapp_business_token');

        $this->assertNull($row->value);
        $this->assertNotEmpty($row->encrypted_value);
        $this->assertStringNotContainsString($secret, $row->encrypted_value);
        $this->assertSame('', $setting['value']);
        $this->assertTrue($setting['has_secret']);
        $this->assertSame($secret, SettingValue::get('whatsapp_business_token'));

        $ciphertext = $row->encrypted_value;
        $this->putJson('/api/settings', ['settings' => [[
            'key' => 'whatsapp_business_token',
            'value' => '',
        ]]])->assertOk();

        $this->assertSame(
            $ciphertext,
            DB::table('system_settings')->where('key', 'whatsapp_business_token')->value('encrypted_value'),
        );
    }

    public function test_legacy_plaintext_remains_readable_until_controlled_migration(): void
    {
        $this->storeLegacySecret('legacy-token-for-test');

        $this->assertSame('legacy-token-for-test', SettingValue::get('whatsapp_business_token'));
        $this->assertNull(DB::table('system_settings')->where('key', 'whatsapp_business_token')->value('encrypted_value'));
    }

    public function test_migration_command_defaults_to_dry_run_and_requires_backup_to_apply(): void
    {
        $this->storeLegacySecret('legacy-token-for-test');

        $this->artisan('siges:encrypt-secrets')
            ->expectsOutputToContain('Segredos legados para migrar: 1')
            ->expectsOutputToContain('Nenhum registro foi alterado')
            ->assertSuccessful();

        $this->assertNotNull(DB::table('system_settings')->where('key', 'whatsapp_business_token')->value('value'));

        $this->artisan('siges:encrypt-secrets', ['--apply' => true])
            ->expectsOutputToContain('Conversao recusada')
            ->assertFailed();

        $this->createBackupArtifact();
        $this->artisan('siges:encrypt-secrets', [
            '--apply' => true,
            '--backup' => $this->backupPath,
        ])->expectsOutputToContain('1 segredo(s) criptografado(s) e verificado(s)')
            ->assertSuccessful();

        $row = DB::table('system_settings')->where('key', 'whatsapp_business_token')->firstOrFail();
        $this->assertNull($row->value);
        $this->assertNotEmpty($row->encrypted_value);
        $this->assertSame('legacy-token-for-test', SettingValue::get('whatsapp_business_token'));
    }

    public function test_rotation_reencrypts_existing_secrets_without_changing_values(): void
    {
        $setting = SystemSetting::query()->where('key', 'whatsapp_business_verify_token')->firstOrFail();
        $before = $setting->encrypted_value;
        $value = $setting->resolvedValue();
        $this->createBackupArtifact();

        $this->artisan('siges:encrypt-secrets', [
            '--apply' => true,
            '--backup' => $this->backupPath,
            '--rotate' => true,
        ])->assertSuccessful();

        $setting->refresh();
        $this->assertNotSame($before, $setting->encrypted_value);
        $this->assertSame($value, $setting->resolvedValue());
    }

    public function test_corrupted_ciphertext_fails_closed_instead_of_using_legacy_value(): void
    {
        DB::table('system_settings')
            ->where('key', 'whatsapp_business_token')
            ->update([
                'value' => json_encode(['value' => 'legacy-fallback-must-not-be-used'], JSON_THROW_ON_ERROR),
                'encrypted_value' => 'corrupted-ciphertext',
            ]);

        $this->expectException(DecryptException::class);

        SettingValue::get('whatsapp_business_token');
    }

    public function test_migration_rollback_restores_plaintext_before_dropping_encrypted_columns(): void
    {
        $migration = require database_path('migrations/2026_11_12_000002_add_encrypted_value_to_system_settings.php');

        $migration->down();

        $rawValue = DB::table('system_settings')
            ->where('key', 'whatsapp_business_verify_token')
            ->value('value');

        $this->assertSame(self::VERIFY_TOKEN, json_decode($rawValue, true, flags: JSON_THROW_ON_ERROR)['value']);
        $this->assertFalse(DB::getSchemaBuilder()->hasColumn('system_settings', 'encrypted_value'));
        $this->assertFalse(DB::getSchemaBuilder()->hasColumn('system_settings', 'secret_encrypted_at'));
    }

    private function authenticateSettingsAdministrator(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Administrador Geral');
        Sanctum::actingAs($user);
    }

    private function storeLegacySecret(string $value): void
    {
        DB::table('system_settings')
            ->where('key', 'whatsapp_business_token')
            ->update([
                'value' => json_encode(['value' => $value], JSON_THROW_ON_ERROR),
                'encrypted_value' => null,
                'secret_encrypted_at' => null,
            ]);
    }

    private function createBackupArtifact(): void
    {
        File::ensureDirectoryExists(dirname($this->backupPath));
        File::put($this->backupPath, 'isolated test backup artifact');
    }
}
