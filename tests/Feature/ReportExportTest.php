<?php

namespace Tests\Feature;

use App\Jobs\GenerateReportExport;
use App\Models\ItTicket;
use App\Models\ReportExport;
use App\Models\User;
use App\Services\Reports\ReportExportGenerator;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->user = User::factory()->create();
        $this->user->assignRole('Administrador Geral');
        Sanctum::actingAs($this->user);
        Queue::fake();
        Storage::fake('local');

        ItTicket::create([
            'requester_name' => 'Solicitante Exportacao',
            'description' => 'Chamado usado para validar exportacoes',
            'type' => 'incidente',
            'priority' => 'media',
            'status' => 'aberto',
            'source' => 'manual',
        ]);
    }

    public function test_csv_xlsx_and_pdf_are_queued_generated_and_downloaded(): void
    {
        foreach (['csv', 'xlsx', 'pdf'] as $format) {
            $export = $this->queueAndGenerate($format);

            $status = $this->getJson("/api/reports/exports/{$export->id}")
                ->assertOk()
                ->assertJsonPath('status', 'completed')
                ->assertJsonPath('row_count', 1)
                ->assertJsonPath('download_url', "/reports/exports/{$export->id}/download");

            $download = $this->get('/api'.$status->json('download_url'))
                ->assertOk()
                ->assertDownload($export->filename);
            $content = $download->streamedContent();

            match ($format) {
                'csv' => $this->assertStringContainsString('Solicitante Exportacao', $content),
                'xlsx' => $this->assertStringStartsWith('PK', $content),
                'pdf' => $this->assertStringStartsWith('%PDF-', $content),
            };
        }
    }

    public function test_legacy_export_endpoint_queues_instead_of_generating_during_request(): void
    {
        $this->getJson('/api/reports/export?report=it_tickets&format=csv')
            ->assertStatus(202)
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('max_rows', 10000);

        Queue::assertPushed(GenerateReportExport::class);
    }

    public function test_export_over_format_limit_fails_safely_without_file(): void
    {
        config()->set('reports.exports.max_rows.pdf', 2);

        foreach (range(1, 2) as $number) {
            ItTicket::create([
                'requester_name' => "Excedente {$number}",
                'description' => 'Volume acima do limite seguro',
                'type' => 'incidente',
                'priority' => 'media',
                'status' => 'aberto',
                'source' => 'manual',
            ]);
        }

        $response = $this->postJson('/api/reports/exports', [
            'report' => 'it_tickets',
            'format' => 'pdf',
        ])->assertStatus(202);

        $export = ReportExport::findOrFail($response->json('id'));
        (new GenerateReportExport($export))->handle(app(ReportExportGenerator::class));

        $this->getJson("/api/reports/exports/{$export->id}")
            ->assertOk()
            ->assertJsonPath('status', 'failed')
            ->assertJsonPath('max_rows', 2)
            ->assertJsonPath('download_url', null)
            ->assertJsonPath('error', 'O relatório excede o limite seguro de 2 registros. Refine os filtros e tente novamente.');

        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_only_requesting_user_can_view_or_download_export(): void
    {
        $export = $this->queueAndGenerate('csv');
        $otherUser = User::factory()->create();
        $otherUser->assignRole('Administrador Geral');
        Sanctum::actingAs($otherUser);

        $this->getJson("/api/reports/exports/{$export->id}")->assertForbidden();
        $this->get("/api/reports/exports/{$export->id}/download")->assertForbidden();
    }

    public function test_pending_and_expired_exports_cannot_be_downloaded(): void
    {
        $pendingResponse = $this->postJson('/api/reports/exports', [
            'report' => 'it_tickets',
            'format' => 'csv',
        ])->assertStatus(202);

        $this->get("/api/reports/exports/{$pendingResponse->json('id')}/download")
            ->assertStatus(409);

        $export = ReportExport::findOrFail($pendingResponse->json('id'));
        $export->update([
            'status' => ReportExport::STATUS_COMPLETED,
            'disk' => 'local',
            'path' => "report-exports/{$export->id}/expired.csv",
            'filename' => 'expired.csv',
            'completed_at' => now()->subHours(2),
            'expires_at' => now()->subHour(),
        ]);
        Storage::disk('local')->put($export->path, 'expired');

        $this->getJson("/api/reports/exports/{$export->id}")
            ->assertOk()
            ->assertJsonPath('status', 'expired')
            ->assertJsonPath('download_url', null);
        $this->get("/api/reports/exports/{$export->id}/download")
            ->assertStatus(410);
    }

    public function test_export_job_uses_dedicated_queue_and_configured_timeout(): void
    {
        config()->set('reports.exports.queue', 'relatorios');
        config()->set('reports.exports.timeout', 45);
        $export = ReportExport::create([
            'user_id' => $this->user->id,
            'report' => 'it_tickets',
            'format' => 'csv',
            'filters' => ['report' => 'it_tickets'],
            'status' => ReportExport::STATUS_PENDING,
        ]);

        $job = new GenerateReportExport($export);

        $this->assertSame('relatorios', $job->queue);
        $this->assertSame(45, $job->timeout);
        $this->assertSame(1, $job->tries);
        $this->assertTrue($job->failOnTimeout);
    }

    public function test_expired_export_files_are_pruned(): void
    {
        $export = ReportExport::create([
            'user_id' => $this->user->id,
            'report' => 'it_tickets',
            'format' => 'csv',
            'filters' => ['report' => 'it_tickets'],
            'status' => ReportExport::STATUS_COMPLETED,
            'disk' => 'local',
            'path' => 'report-exports/expired/report.csv',
            'filename' => 'report.csv',
            'completed_at' => now()->subHours(2),
            'expires_at' => now()->subHour(),
        ]);
        Storage::disk('local')->put($export->path, 'content');

        $this->artisan('reports:prune-exports')->assertSuccessful();

        Storage::disk('local')->assertMissing($export->path);
        $this->assertDatabaseMissing('report_exports', ['id' => $export->id]);
    }

    private function queueAndGenerate(string $format): ReportExport
    {
        $response = $this->postJson('/api/reports/exports', [
            'report' => 'it_tickets',
            'format' => $format,
        ])->assertStatus(202)
            ->assertJsonPath('status', 'pending');

        $export = ReportExport::findOrFail($response->json('id'));
        Queue::assertPushed(GenerateReportExport::class, fn (GenerateReportExport $job) => $job->export->is($export));
        (new GenerateReportExport($export))->handle(app(ReportExportGenerator::class));

        return $export->refresh();
    }
}
