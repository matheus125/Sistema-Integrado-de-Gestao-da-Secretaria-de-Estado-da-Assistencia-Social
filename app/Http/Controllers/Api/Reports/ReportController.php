<?php

namespace App\Http\Controllers\Api\Reports;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateReportExport;
use App\Models\ItTicket;
use App\Models\ReportExport;
use App\Services\Reports\ReportBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private readonly ReportBuilder $reports) {}

    public function options(Request $request): JsonResponse
    {
        $allowed = collect(ReportBuilder::REPORTS)
            ->filter(fn (string $label, string $value) => $this->reports->canView($request, $value));

        return response()->json([
            'reports' => $allowed->map(fn ($label, $value) => compact('label', 'value'))->values(),
            'it_ticket_types' => collect(ItTicket::TYPES)
                ->map(fn (array $meta, string $value) => ['value' => $value, 'label' => $meta['label']])
                ->values(),
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $report = $this->reports->build($request);

        return response()->json([
            'title' => $report['title'],
            'headings' => $report['headings'],
            'rows' => array_slice($report['rows'], 0, 50),
            'total' => count($report['rows']),
        ]);
    }

    public function export(Request $request): JsonResponse
    {
        return $this->queueExport($request);
    }

    public function storeExport(Request $request): JsonResponse
    {
        return $this->queueExport($request);
    }

    public function showExport(Request $request, ReportExport $reportExport): JsonResponse
    {
        $this->authorizeExportOwner($request, $reportExport);

        return response()->json($this->exportPayload($reportExport));
    }

    public function downloadExport(Request $request, ReportExport $reportExport): StreamedResponse
    {
        $this->authorizeExportOwner($request, $reportExport);
        abort_if($reportExport->status !== ReportExport::STATUS_COMPLETED, 409, 'A exportação ainda não está disponível.');
        abort_if($reportExport->expires_at?->isPast(), 410, 'A exportação expirou. Solicite um novo arquivo.');
        abort_unless($reportExport->disk && $reportExport->path && Storage::disk($reportExport->disk)->exists($reportExport->path), 404);

        return Storage::disk($reportExport->disk)->download($reportExport->path, $reportExport->filename);
    }

    private function queueExport(Request $request): JsonResponse
    {
        $data = $request->validate([
            'report' => ['required', Rule::in(array_keys(ReportBuilder::REPORTS))],
            'format' => ['required', Rule::in(['csv', 'xlsx', 'pdf'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'status' => ['nullable', 'string', 'max:80'],
            'type' => ['nullable', Rule::in(array_keys(ItTicket::TYPES))],
        ]);

        abort_unless($this->reports->canView($request, $data['report']), 403);
        abort_if($data['format'] === 'pdf' && $request->user()->cannot('relatorios.emitir-pdf'), 403);
        abort_if($data['format'] !== 'pdf' && $request->user()->cannot('relatorios.exportar'), 403);

        $export = ReportExport::create([
            'user_id' => $request->user()->id,
            'report' => $data['report'],
            'format' => $data['format'],
            'filters' => collect($data)->except('format')->all(),
            'status' => ReportExport::STATUS_PENDING,
        ]);

        GenerateReportExport::dispatch($export);

        return response()->json($this->exportPayload($export), 202);
    }

    private function exportPayload(ReportExport $export): array
    {
        $expired = $export->expires_at?->isPast() ?? false;

        return [
            'id' => $export->id,
            'report' => $export->report,
            'format' => $export->format,
            'status' => $expired ? 'expired' : $export->status,
            'row_count' => $export->row_count,
            'error' => $export->error,
            'max_rows' => (int) config("reports.exports.max_rows.{$export->format}"),
            'expires_at' => $export->expires_at,
            'download_url' => $export->status === ReportExport::STATUS_COMPLETED && ! $expired
                ? "/reports/exports/{$export->id}/download"
                : null,
        ];
    }

    private function authorizeExportOwner(Request $request, ReportExport $export): void
    {
        abort_unless((int) $export->user_id === (int) $request->user()->id, 403);
    }
}
