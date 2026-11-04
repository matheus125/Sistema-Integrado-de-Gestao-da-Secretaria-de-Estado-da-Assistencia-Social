<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\ItSlaCalendar;
use App\Models\ItSlaRule;
use App\Models\ItTicket;
use App\Models\User;
use App\Services\It\SlaService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ItSlaCalendarTest extends TestCase
{
    use RefreshDatabase;

    private function calendarData(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Expediente', 'weekdays' => [1, 2, 3, 4, 5],
            'starts_at' => '08:00', 'ends_at' => '17:00',
            'break_starts_at' => '12:00', 'break_ends_at' => '13:00',
            'holidays' => [], 'exceptions' => [], 'count_weekends' => false, 'active' => true,
        ], $overrides);
    }

    private function login(string $role = 'Gerente Informatica'): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $department = Department::create(['name' => 'TI', 'slug' => 'gerencia-de-informatica']);
        $user = User::factory()->create(['department_id' => $department->id]);
        $user->assignRole($role);
        Sanctum::actingAs($user);

        return $user;
    }

    public static function deadlines(): array
    {
        return [
            'cross lunch' => ['2026-09-08 11:30', 60, [], '2026-09-08 13:30'],
            'during lunch' => ['2026-09-08 12:15', 30, [], '2026-09-08 13:30'],
            'before opening' => ['2026-09-08 06:00', 60, [], '2026-09-08 09:00'],
            'exact closing' => ['2026-09-08 16:00', 60, [], '2026-09-08 17:00'],
            'after closing' => ['2026-09-08 17:00', 60, [], '2026-09-09 09:00'],
            'weekend and holiday' => ['2026-09-04 16:30', 60, ['holidays' => ['2026-09-07']], '2026-09-08 08:30'],
            'multiple days' => ['2026-09-08 08:00', 960, [], '2026-09-09 17:00'],
            'all weekdays' => ['2026-09-05 08:00', 60, ['count_weekends' => true], '2026-09-05 09:00'],
            'zero in pause' => ['2026-09-08 12:30', 0, [], '2026-09-08 13:00'],
            'closed exception' => ['2026-09-08 08:00', 60, ['exceptions' => [['date' => '2026-09-08', 'working' => false]]], '2026-09-09 09:00'],
            'holiday overridden' => ['2026-09-08 08:00', 60, ['holidays' => ['2026-09-08'], 'exceptions' => [['date' => '2026-09-08', 'working' => true, 'starts_at' => '10:00', 'ends_at' => '14:00']]], '2026-09-08 11:00'],
            'exception lunch' => ['2026-09-08 10:30', 60, ['exceptions' => [['date' => '2026-09-08', 'working' => true, 'starts_at' => '10:00', 'ends_at' => '15:00', 'break_starts_at' => '11:00', 'break_ends_at' => '12:00']]], '2026-09-08 12:30'],
        ];
    }

    #[DataProvider('deadlines')]
    public function test_business_deadlines(string $from, int $minutes, array $overrides, string $expected): void
    {
        config(['app.timezone' => 'America/Manaus']);
        $calendar = new ItSlaCalendar($this->calendarData($overrides));
        $start = Carbon::parse($from, 'America/Manaus');
        $result = app(SlaService::class)->addBusinessMinutes($start, $minutes, $calendar);
        $this->assertSame($expected, $result->format('Y-m-d H:i'));
        $this->assertSame($from, $start->format('Y-m-d H:i'));
    }

    public function test_calendar_crud_and_partial_validation(): void
    {
        $this->login();
        $id = $this->postJson('/api/it/sla-calendars', $this->calendarData())->assertCreated()->json('id');
        $this->getJson('/api/it/sla-calendars')->assertOk()->assertJsonStructure(['data', 'timezone']);
        $this->putJson("/api/it/sla-calendars/{$id}", ['starts_at' => '18:00'])->assertUnprocessable()->assertJsonValidationErrors('ends_at');
        $this->putJson("/api/it/sla-calendars/{$id}", ['name' => 'Novo nome'])->assertOk()->assertJsonPath('name', 'Novo nome');
        $this->putJson("/api/it/sla-calendars/{$id}", ['active' => false])->assertOk();
    }

    public static function invalidCalendars(): array
    {
        return [
            [['ends_at' => '07:00'], 'ends_at'],
            [['break_ends_at' => null], 'break_ends_at'],
            [['break_starts_at' => '07:00'], 'break_ends_at'],
            [['weekdays' => []], 'weekdays'],
            [['exceptions' => [['date' => '2026-09-08', 'working' => true, 'starts_at' => '19:00', 'ends_at' => '10:00']]], 'exceptions.0.ends_at'],
            [['exceptions' => [['date' => 'invalid', 'working' => true]]], 'exceptions.0.date'],
        ];
    }

    #[DataProvider('invalidCalendars')]
    public function test_rejects_invalid_calendar(array $overrides, string $field): void
    {
        $this->login();
        $this->postJson('/api/it/sla-calendars', $this->calendarData($overrides))->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    public function test_read_only_user_cannot_manage_calendars(): void
    {
        $this->login('Servidor Informatica');
        $calendar = ItSlaCalendar::firstOrFail();
        $this->getJson('/api/it/sla-calendars')->assertOk();
        $this->postJson('/api/it/sla-calendars', $this->calendarData())->assertForbidden();
        $this->putJson("/api/it/sla-calendars/{$calendar->id}", ['name' => 'Alterado'])->assertForbidden();
    }

    public function test_inactive_calendars_are_not_selected_and_used_calendar_cannot_be_disabled(): void
    {
        $this->login();
        $calendar = ItSlaCalendar::firstOrFail();
        $this->putJson("/api/it/sla-calendars/{$calendar->id}", ['active' => false])->assertUnprocessable()->assertJsonValidationErrors('active');
        $inactive = ItSlaCalendar::create($this->calendarData(['active' => false]));
        $this->postJson('/api/it/sla-rules', ['name' => 'Inativa', 'calendar_id' => $inactive->id, 'first_response_minutes' => 10, 'resolution_minutes' => 60])->assertUnprocessable()->assertJsonValidationErrors('calendar_id');
        $rule = ItSlaRule::create(['name' => 'Legado inativo', 'calendar_id' => $inactive->id, 'priority' => 'alta', 'type' => 'incidente', 'resolution_minutes' => 60, 'first_response_minutes' => 10, 'active' => true]);
        $this->putJson("/api/it/sla-rules/{$rule->id}", ['active' => true])->assertUnprocessable()->assertJsonValidationErrors('calendar_id');
        $this->assertNotSame($rule->id, app(SlaService::class)->resolveRule(['priority' => 'alta', 'type' => 'incidente'])->id);
    }

    public function test_ticket_api_calculates_all_deadlines_using_saved_calendar(): void
    {
        $user = $this->login();
        config(['app.timezone' => 'America/Manaus']);
        $calendarId = $this->postJson('/api/it/sla-calendars', $this->calendarData())->assertCreated()->json('id');
        $this->postJson('/api/it/sla-rules', ['name' => 'Especial', 'calendar_id' => $calendarId, 'priority' => 'alta', 'type' => 'incidente', 'first_response_minutes' => 60, 'start_minutes' => 90, 'resolution_minutes' => 120])->assertCreated();
        Carbon::setTestNow(Carbon::parse('2026-09-08 11:30', 'America/Manaus'));
        try {
            $id = $this->postJson('/api/it/tickets', ['department_id' => $user->department_id, 'requester_name' => 'Servidor', 'description' => 'Falha', 'priority' => 'alta', 'type' => 'incidente'])->assertCreated()->json('id');
            $ticket = ItTicket::findOrFail($id);
            $this->assertSame('13:30', $ticket->first_response_due_at->setTimezone('America/Manaus')->format('H:i'));
            $this->assertSame('14:00', $ticket->start_due_at->setTimezone('America/Manaus')->format('H:i'));
            $this->assertSame('14:30', $ticket->sla_due_at->setTimezone('America/Manaus')->format('H:i'));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_invalid_legacy_window_fails_explicitly(): void
    {
        $this->expectException(ValidationException::class);
        app(SlaService::class)->addBusinessMinutes(Carbon::parse('2026-09-08 08:00'), 60, new ItSlaCalendar($this->calendarData(['ends_at' => '07:00'])));
    }

    public function test_timezone_and_seconds_are_preserved(): void
    {
        config(['app.timezone' => 'America/Manaus']);
        $result = app(SlaService::class)->addBusinessMinutes(Carbon::parse('2026-09-08 15:30:30', 'UTC'), 60, new ItSlaCalendar($this->calendarData()));
        $this->assertSame('2026-09-08 17:30:30 +00:00', $result->format('Y-m-d H:i:s P'));
    }
}
