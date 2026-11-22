<?php

namespace Tests\Feature;

use App\Models\ItTicket;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Administrador Geral');
        Sanctum::actingAs($user);

        ItTicket::create([
            'requester_name' => 'Solicitante Exportacao',
            'description' => 'Chamado usado para validar exportacoes',
            'type' => 'incidente',
            'priority' => 'media',
            'status' => 'aberto',
            'source' => 'manual',
        ]);
    }

    public function test_csv_report_export_remains_valid(): void
    {
        $response = $this->get('/api/reports/export?report=it_tickets&format=csv')
            ->assertOk()
            ->assertDownload()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();

        $this->assertStringContainsString('Solicitante Exportacao', $content);
        $this->assertStringContainsString(';', $content);
    }

    public function test_xlsx_report_export_remains_valid(): void
    {
        $response = $this->get('/api/reports/export?report=it_tickets&format=xlsx')
            ->assertOk()
            ->assertDownload()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);
        $content = $response->baseResponse->getFile()->getContent();

        $this->assertStringStartsWith('PK', $content);
        $this->assertStringContainsString('xl/workbook.xml', $content);
    }

    public function test_pdf_report_export_remains_valid(): void
    {
        $response = $this->get('/api/reports/export?report=it_tickets&format=pdf')
            ->assertOk()
            ->assertDownload()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }
}
