<?php

namespace Tests\Integration;

use App\Models\Department;
use App\Models\Driver;
use App\Models\Product;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MysqlConcurrencyTest extends TestCase
{
    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('mysql', DB::connection()->getDriverName());
        $this->assertStringStartsWith('siges_fix013_', DB::connection()->getDatabaseName());

        $this->seed(RolesAndPermissionsSeeder::class);
        $department = Department::create(['name' => 'Administração', 'slug' => 'administracao-concorrencia']);
        $this->actor = User::factory()->create(['department_id' => $department->id]);
        $this->actor->assignRole('Administrador Geral');
    }

    public function test_mysql_serializes_stock_schedule_assignment_and_ticket_numbering(): void
    {
        $this->assertStockCannotBecomeNegative();
        $this->assertScheduleCannotBeDoubleBooked();
        $this->assertAssignmentAndTicketNumbersRemainConsistent();
    }

    private function assertStockCannotBecomeNegative(): void
    {
        $product = Product::create([
            'internal_code' => 'CONC-STOCK',
            'description' => 'Produto concorrente',
            'unit' => 'UN',
            'quantity' => 10,
            'minimum_quantity' => 0,
            'status' => 'ativo',
        ]);
        $payload = [
            'product_id' => $product->id,
            'department_id' => $this->actor->department_id,
            'type' => 'saida',
            'quantity' => 7,
            'movement_date' => now()->toDateString(),
        ];

        $results = $this->runWorkersWithBarrier('stock', [$payload, $payload], function () use ($product): void {
            Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
        });

        $this->assertCount(1, array_filter($results, fn (array $result): bool => $result['result'] === 'created'));
        $this->assertCount(1, array_filter($results, fn (array $result): bool => $result['result'] === 'validation'));
        $this->assertSame('3.00', $product->fresh()->quantity);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    private function assertScheduleCannotBeDoubleBooked(): void
    {
        $vehicle = Vehicle::create([
            'brand' => 'Fiat',
            'model' => 'Cronos',
            'plate' => 'CON1C23',
            'status' => 'disponivel',
            'capacity' => 4,
        ]);
        $driver = Driver::create([
            'name' => 'Motorista Concorrência',
            'cnh' => 'CONCORRENCIA-013',
            'cnh_category' => 'B',
            'cnh_expires_at' => now()->addYear()->toDateString(),
            'status' => 'ativo',
        ]);
        $payload = [
            'department_id' => $this->actor->department_id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'route_description' => 'Agenda concorrente',
            'origin' => 'Sede',
            'destination' => 'Unidade',
            'date' => now()->addDay()->toDateString(),
            'departure_time' => '08:00',
            'expected_arrival_time' => '10:00',
            'passengers_count' => 2,
            'responsible' => 'Responsável',
        ];

        $results = $this->runWorkersWithBarrier('transport', [$payload, $payload], function () use ($vehicle, $driver): void {
            Vehicle::query()->whereKey($vehicle->id)->lockForUpdate()->firstOrFail();
            Driver::query()->whereKey($driver->id)->lockForUpdate()->firstOrFail();
        });

        $this->assertCount(1, array_filter($results, fn (array $result): bool => $result['result'] === 'created'));
        $rejected = array_values(array_filter($results, fn (array $result): bool => $result['result'] === 'validation'));
        $this->assertCount(1, $rejected);
        $this->assertNotEmpty(array_intersect(['vehicle_id', 'driver_id'], $rejected[0]['fields']));
        $this->assertDatabaseCount('vehicle_requests', 1);
    }

    private function assertAssignmentAndTicketNumbersRemainConsistent(): void
    {
        $itDepartment = Department::create(['name' => 'Gerência de Informática', 'slug' => 'gerencia-de-informatica']);
        $technicians = User::factory()->count(2)->create(['department_id' => $itDepartment->id, 'status' => 'ativo']);
        $payload = [
            'department_id' => $this->actor->department_id,
            'requester_name' => 'Solicitante concorrente',
            'description' => 'Chamado simultâneo',
            'priority' => 'media',
            'type' => 'incidente',
        ];

        $results = $this->runWorkersWithBarrier('ticket', [$payload, $payload], function () use ($itDepartment): void {
            Department::query()->whereKey($itDepartment->id)->lockForUpdate()->firstOrFail();
        });

        $this->assertSame(['created', 'created'], array_column($results, 'result'));
        $this->assertCount(2, array_unique(array_column($results, 'id')));
        $this->assertEqualsCanonicalizing($technicians->pluck('id')->all(), array_column($results, 'assigned_to'));
        $this->assertDatabaseCount('it_tickets', 2);
        $this->assertDatabaseCount('it_ticket_messages', 2);
        $this->assertDatabaseCount('it_ticket_status_histories', 2);
    }

    /**
     * @param  list<array<string, mixed>>  $payloads
     * @return list<array<string, mixed>>
     */
    private function runWorkersWithBarrier(string $scenario, array $payloads, callable $lockBarrier): array
    {
        DB::beginTransaction();

        try {
            $lockBarrier();
            $processes = collect($payloads)->map(function (array $payload) use ($scenario): Process {
                $process = new Process([
                    PHP_BINARY,
                    base_path('tests/Support/mysql_concurrency_worker.php'),
                    $scenario,
                    (string) $this->actor->id,
                    base64_encode(json_encode($payload, JSON_THROW_ON_ERROR)),
                ], base_path(), [
                    'APP_ENV' => 'testing',
                    'DB_CONNECTION' => 'mysql',
                    'DB_DATABASE' => DB::connection()->getDatabaseName(),
                ]);
                $process->setTimeout(20);
                $process->start();

                return $process;
            });

            usleep(300_000);
            DB::commit();
        } catch (\Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }

        return $processes->map(function (Process $process): array {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());

            return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        })->values()->all();
    }
}
