<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AuthRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('auth.login_throttle.max_attempts', 3);
        config()->set('auth.login_throttle.max_attempts_per_ip', 10);
        config()->set('auth.login_throttle.decay_seconds', 60);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_valid_login_accepts_normalized_identifier(): void
    {
        $user = User::factory()->create(['email' => 'servidor@example.com']);

        $this->login('  SERVIDOR@EXAMPLE.COM  ', 'password')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonStructure(['token', 'user', 'roles', 'permissions']);
    }

    public function test_invalid_login_keeps_generic_validation_message(): void
    {
        User::factory()->create(['email' => 'servidor@example.com']);

        $this->login('servidor@example.com', 'senha-incorreta')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email')
            ->assertJsonPath('errors.email.0', 'Credenciais invalidas.');
    }

    public function test_excessive_attempts_are_temporarily_blocked(): void
    {
        User::factory()->create(['email' => 'servidor@example.com']);

        $this->failLoginThreeTimes('servidor@example.com');

        $this->login('servidor@example.com', 'senha-incorreta')
            ->assertTooManyRequests()
            ->assertHeader('Retry-After')
            ->assertJsonPath('message', 'Muitas tentativas de acesso. Tente novamente mais tarde.');
    }

    public function test_rate_limit_expires_after_configured_window(): void
    {
        User::factory()->create(['email' => 'servidor@example.com']);
        $this->failLoginThreeTimes('servidor@example.com');

        $this->login('servidor@example.com', 'senha-incorreta')->assertTooManyRequests();

        Carbon::setTestNow(now()->addSeconds(61));

        $this->login('servidor@example.com', 'senha-incorreta')->assertUnprocessable();
    }

    public function test_a_blocked_identifier_does_not_block_another_user_on_same_ip(): void
    {
        User::factory()->create(['email' => 'primeiro@example.com']);
        $second = User::factory()->create(['email' => 'segundo@example.com']);
        $this->failLoginThreeTimes('primeiro@example.com');

        $this->login('primeiro@example.com', 'senha-incorreta')->assertTooManyRequests();
        $this->login('segundo@example.com', 'password')
            ->assertOk()
            ->assertJsonPath('user.id', $second->id);
    }

    public function test_same_identifier_from_another_ip_has_an_independent_limit(): void
    {
        User::factory()->create(['email' => 'servidor@example.com']);
        $this->failLoginThreeTimes('servidor@example.com', '192.0.2.10');

        $this->login('servidor@example.com', 'senha-incorreta', '192.0.2.10')->assertTooManyRequests();
        $this->login('servidor@example.com', 'password', '192.0.2.11')->assertOk();
    }

    public function test_ip_limit_blocks_attempts_distributed_across_identifiers(): void
    {
        config()->set('auth.login_throttle.max_attempts', 10);
        config()->set('auth.login_throttle.max_attempts_per_ip', 3);

        foreach (['um@example.com', 'dois@example.com', 'tres@example.com'] as $email) {
            $this->login($email, 'senha-incorreta', '192.0.2.20')->assertUnprocessable();
        }

        $this->login('quatro@example.com', 'senha-incorreta', '192.0.2.20')
            ->assertTooManyRequests();
    }

    public function test_existing_and_unknown_accounts_have_the_same_failure_response(): void
    {
        User::factory()->create(['email' => 'existente@example.com']);

        $existing = $this->login('existente@example.com', 'senha-incorreta', '192.0.2.30');
        $unknown = $this->login('desconhecido@example.com', 'senha-incorreta', '192.0.2.31');

        $existing->assertUnprocessable();
        $unknown->assertUnprocessable();
        $this->assertSame($existing->json('message'), $unknown->json('message'));
        $this->assertSame($existing->json('errors'), $unknown->json('errors'));
    }

    public function test_successful_login_clears_identifier_failures(): void
    {
        User::factory()->create(['email' => 'servidor@example.com']);

        $this->login('servidor@example.com', 'senha-incorreta')->assertUnprocessable();
        $this->login('servidor@example.com', 'senha-incorreta')->assertUnprocessable();
        $this->login('servidor@example.com', 'password')->assertOk();

        $this->failLoginThreeTimes('servidor@example.com');
        $this->login('servidor@example.com', 'senha-incorreta')->assertTooManyRequests();
    }

    private function failLoginThreeTimes(string $email, string $ip = '127.0.0.1'): void
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->login($email, 'senha-incorreta', $ip)->assertUnprocessable();
        }
    }

    private function login(string $email, string $password, string $ip = '127.0.0.1')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->postJson('/api/auth/login', [
            'email' => $email,
            'password' => $password,
            'device_name' => 'teste-rate-limit',
        ]);
    }
}
