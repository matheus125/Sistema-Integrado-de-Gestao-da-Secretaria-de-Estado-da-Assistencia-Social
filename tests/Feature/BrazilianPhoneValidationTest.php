<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\ItTicket;
use App\Models\User;
use App\Support\BrazilianPhone;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BrazilianPhoneValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $administrator = User::factory()->create();
        $administrator->assignRole('Administrador Geral');
        Sanctum::actingAs($administrator);
    }

    public function test_supported_brazilian_phone_formats_are_normalized(): void
    {
        $validPhones = [
            ['(92) 3234-5678', '9232345678'],
            ['92 99999-0000', '92999990000'],
            ['+55 (92) 99999-0000', '92999990000'],
            ['5592999990000', '92999990000'],
            ['(11) 2345.6789', '1123456789'],
        ];

        foreach ($validPhones as [$input, $normalized]) {
            $this->assertTrue(BrazilianPhone::isValid($input), $input);
            $this->assertSame($normalized, BrazilianPhone::normalize($input));
            $this->assertSame('55'.$normalized, BrazilianPhone::toE164($input));
        }
    }

    public function test_letters_foreign_numbers_invalid_ddd_and_invalid_subscriber_formats_are_rejected(): void
    {
        $invalidPhones = [
            'abc',
            '(92) 9999-ABCD',
            '+1 202 555 0100',
            '(20) 99999-0000',
            '(92) 89999-0000',
            '(92) 1234-5678',
            '9299999000',
            '929999900000',
        ];

        foreach ($invalidPhones as $phone) {
            $this->assertFalse(BrazilianPhone::isValid($phone), $phone);
        }
    }

    public function test_administration_normalizes_valid_phone_and_rejects_invalid_phone(): void
    {
        $userId = $this->postJson('/api/admin/users', [
            'name' => 'Usuario Telefone',
            'email' => 'telefone@example.test',
            'password' => 'password-secure',
            'phone' => '+55 (92) 99999-0000',
            'status' => 'ativo',
            'roles' => ['Consulta'],
        ])->assertCreated()
            ->assertJsonPath('phone', '92999990000')
            ->json('id');

        $this->assertDatabaseHas('users', ['id' => $userId, 'phone' => '92999990000']);

        $this->putJson('/api/admin/users/'.$userId, [
            'name' => 'Usuario Telefone',
            'email' => 'telefone@example.test',
            'phone' => 'telefone invalido',
            'status' => 'ativo',
            'roles' => ['Consulta'],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('phone');

        $this->assertDatabaseHas('users', ['id' => $userId, 'phone' => '92999990000']);
    }

    public function test_transport_normalizes_valid_phone_and_rejects_invalid_phone(): void
    {
        $driverId = $this->postJson('/api/transport/drivers', [
            'name' => 'Motorista Telefone',
            'cnh' => '12345678901',
            'cnh_category' => 'B',
            'cnh_expires_at' => now()->addYear()->toDateString(),
            'phone' => '(92) 3234-5678',
            'status' => 'ativo',
        ])->assertCreated()
            ->assertJsonPath('phone', '9232345678')
            ->json('id');

        $this->assertDatabaseHas('drivers', ['id' => $driverId, 'phone' => '9232345678']);

        $this->putJson('/api/transport/drivers/'.$driverId, [
            'name' => 'Motorista Telefone',
            'cnh' => '12345678901',
            'cnh_category' => 'B',
            'cnh_expires_at' => now()->addYear()->toDateString(),
            'phone' => '(20) 99999-0000',
            'status' => 'ativo',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('phone');

        $this->assertSame('9232345678', Driver::query()->findOrFail($driverId)->phone);
    }

    public function test_help_desk_normalizes_valid_phone_and_rejects_invalid_phone(): void
    {
        $ticketId = $this->postJson('/api/it/tickets', [
            'requester_name' => 'Solicitante Telefone',
            'requester_phone' => '+55 (92) 99999-0000',
            'description' => 'Teste de normalizacao do telefone.',
            'priority' => 'media',
        ])->assertCreated()
            ->assertJsonPath('requester_phone', '92999990000')
            ->json('id');

        $this->assertDatabaseHas('it_tickets', [
            'id' => $ticketId,
            'requester_phone' => '92999990000',
        ]);

        $this->putJson('/api/it/tickets/'.$ticketId, [
            'requester_phone' => 'abc',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('requester_phone');

        $this->assertSame('92999990000', ItTicket::query()->findOrFail($ticketId)->requester_phone);
    }

    public function test_legacy_audit_reports_counts_without_changing_phone_values(): void
    {
        $legacyUser = User::factory()->create(['phone' => 'telefone legado']);
        $legacyDriver = Driver::create([
            'name' => 'Motorista Legado',
            'cnh' => '98765432100',
            'cnh_category' => 'B',
            'cnh_expires_at' => now()->addYear()->toDateString(),
            'phone' => '+55 (92) 99999-0000',
            'status' => 'ativo',
        ]);

        $this->artisan('siges:audit-phones', ['--fail-on-invalid' => true])
            ->expectsOutputToContain('Nenhum dado foi exibido ou alterado.')
            ->expectsOutputToContain('Foram encontrados 1 telefone(s) inválido(s).')
            ->assertFailed();

        $this->assertSame('telefone legado', $legacyUser->fresh()->phone);
        $this->assertSame('+55 (92) 99999-0000', $legacyDriver->fresh()->phone);
    }
}
