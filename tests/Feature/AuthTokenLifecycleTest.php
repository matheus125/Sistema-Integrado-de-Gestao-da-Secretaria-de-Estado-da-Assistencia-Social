<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTokenLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'correct-password-for-tests';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('sanctum.expiration', 120);
        config()->set('sanctum.idle_timeout', 15);
        config()->set('sanctum.max_tokens_per_user', 2);
        config()->set('sanctum.prune_expired_hours', 24);

        SystemSetting::query()->create([
            'group' => 'seguranca',
            'key' => 'session_timeout_minutes',
            'label' => 'Tempo de sessao em minutos',
            'type' => 'number',
            'value' => ['value' => 15],
            'is_secret' => false,
        ]);
    }

    public function test_login_issues_hashed_token_with_absolute_expiration(): void
    {
        $user = $this->createUser();
        $response = $this->login($user, 'Notebook corporativo')
            ->assertOk()
            ->assertJsonPath('idle_timeout_minutes', 15)
            ->assertJsonStructure(['token', 'token_expires_at', 'user']);

        $plainTextToken = $response->json('token');
        $storedToken = PersonalAccessToken::query()->sole();

        $this->assertNotSame($plainTextToken, $storedToken->token);
        $this->assertSame(hash('sha256', explode('|', $plainTextToken, 2)[1]), $storedToken->token);
        $this->assertSame('Notebook corporativo', $storedToken->name);
        $this->assertNotNull($storedToken->expires_at);

        $this->asToken($plainTextToken)->getJson('/api/auth/me')->assertOk();

        $this->travel(121)->minutes();
        $this->asToken($plainTextToken)->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_activity_renews_idle_window_and_inactivity_rejects_token(): void
    {
        config()->set('sanctum.expiration', 240);
        $user = $this->createUser();
        $token = $this->login($user)->json('token');

        $this->travel(10)->minutes();
        $this->asToken($token)->getJson('/api/auth/me')->assertOk();
        $firstUse = PersonalAccessToken::query()->sole()->last_used_at;

        $this->travel(10)->minutes();
        $this->asToken($token)->getJson('/api/auth/me')->assertOk();
        $this->assertTrue(PersonalAccessToken::query()->sole()->last_used_at->gt($firstUse));

        $this->travel(16)->minutes();
        $this->asToken($token)->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_logout_revokes_current_token_and_logout_all_revokes_every_device(): void
    {
        $user = $this->createUser();
        $firstToken = $this->login($user, 'Dispositivo 1')->json('token');
        $secondToken = $this->login($user, 'Dispositivo 2')->json('token');

        $this->asToken($secondToken)->postJson('/api/auth/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->asToken($secondToken)->getJson('/api/auth/me')->assertUnauthorized();

        $thirdToken = $this->login($user, 'Dispositivo 3')->assertOk()->json('token');
        $this->asToken($thirdToken)->postJson('/api/auth/logout-all')
            ->assertOk()
            ->assertJsonPath('revoked', 2);

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->asToken($firstToken)->getJson('/api/auth/me')->assertUnauthorized();
        $this->asToken($thirdToken)->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_user_can_list_and_revoke_only_own_devices(): void
    {
        $user = $this->createUser();
        $otherUser = $this->createUser('other@example.test');
        $firstToken = $this->login($user, 'Desktop')->json('token');
        $secondToken = $this->login($user, 'Notebook')->json('token');
        $otherAccessToken = $otherUser->createToken('Outro usuario', ['*'], now()->addHour())->accessToken;

        $response = $this->asToken($secondToken)->getJson('/api/auth/devices')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $firstDevice = collect($response->json('data'))->firstWhere('name', 'Desktop');
        $currentDevice = collect($response->json('data'))->firstWhere('name', 'Notebook');

        $this->assertFalse($firstDevice['current']);
        $this->assertTrue($currentDevice['current']);
        $this->assertArrayNotHasKey('token', $currentDevice);

        $this->asToken($secondToken)
            ->deleteJson('/api/auth/devices/'.$otherAccessToken->getKey())
            ->assertNotFound();

        $this->asToken($secondToken)
            ->deleteJson('/api/auth/devices/'.$firstDevice['id'])
            ->assertOk()
            ->assertJsonPath('current', false);

        $this->asToken($firstToken)->getJson('/api/auth/me')->assertUnauthorized();
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $otherAccessToken->getKey()]);
    }

    public function test_login_keeps_only_configured_number_of_recent_tokens(): void
    {
        $user = $this->createUser();
        $firstToken = $this->login($user, 'Primeiro')->json('token');
        $this->login($user, 'Segundo')->assertOk();
        $this->login($user, 'Terceiro')->assertOk();

        $this->assertSame(['Segundo', 'Terceiro'], $user->tokens()->orderBy('id')->pluck('name')->all());
        $this->asToken($firstToken)->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_inactive_user_cannot_login_or_keep_using_existing_token(): void
    {
        $user = $this->createUser();
        $token = $this->login($user)->json('token');

        $user->update(['status' => 'inativo']);

        $this->login($user)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
        $this->asToken($token)->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_admin_status_or_password_change_revokes_user_tokens(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $administrator = $this->createUser('administrator@example.test');
        $administrator->assignRole('Administrador Geral');
        Sanctum::actingAs($administrator);

        $target = $this->createUser('target@example.test');
        $target->createToken('Target device');

        $this->putJson('/api/admin/users/'.$target->id, [
            'name' => $target->name,
            'email' => $target->email,
            'status' => 'inativo',
            'roles' => [],
        ])->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $target->id,
        ]);

        $target->update(['status' => 'ativo']);
        $target->createToken('Target device 2');

        $this->putJson('/api/admin/users/'.$target->id, [
            'name' => $target->name,
            'email' => $target->email,
            'status' => 'ativo',
            'password' => 'new-secure-password',
            'roles' => [],
        ])->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $target->id,
        ]);
    }

    public function test_prune_command_removes_expired_absolute_and_inactive_tokens(): void
    {
        $user = $this->createUser();
        $active = $user->createToken('Ativo', ['*'], now()->addHour())->accessToken;
        $expired = $user->createToken('Expirado', ['*'], now()->subMinute())->accessToken;
        $inactive = $user->createToken('Inativo', ['*'], now()->addHour())->accessToken;
        $absolute = $user->createToken('Absoluto', ['*'], now()->addHour())->accessToken;

        $inactive->forceFill(['last_used_at' => now()->subMinutes(16)])->save();
        $absolute->forceFill(['created_at' => now()->subMinutes(121)])->save();

        $this->artisan('siges:prune-tokens', ['--hours' => 0])
            ->expectsOutputToContain('3 token(s) expirado(s) ou inativo(s) removido(s)')
            ->assertSuccessful();

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $active->getKey()]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $expired->getKey()]);
    }

    public function test_session_timeout_setting_accepts_only_safe_range(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $administrator = $this->createUser('settings-admin@example.test');
        $administrator->assignRole('Administrador Geral');
        Sanctum::actingAs($administrator);

        $this->putJson('/api/settings', ['settings' => [[
            'key' => 'session_timeout_minutes',
            'value' => 2,
        ]]])->assertUnprocessable()
            ->assertJsonValidationErrors('settings.session_timeout_minutes');

        $this->putJson('/api/settings', ['settings' => [[
            'key' => 'session_timeout_minutes',
            'value' => 30,
        ]]])->assertOk();

        $this->assertSame(30, SystemSetting::query()->where('key', 'session_timeout_minutes')->firstOrFail()->resolvedValue());
    }

    private function createUser(?string $email = null): User
    {
        return User::factory()->create([
            'email' => $email ?? 'user@example.test',
            'password' => Hash::make(self::PASSWORD),
            'status' => 'ativo',
        ]);
    }

    private function login(User $user, string $deviceName = 'SIGES Web')
    {
        return $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => self::PASSWORD,
            'device_name' => $deviceName,
        ]);
    }

    private function asToken(string $token): static
    {
        Auth::forgetGuards();

        return $this->withToken($token);
    }
}
