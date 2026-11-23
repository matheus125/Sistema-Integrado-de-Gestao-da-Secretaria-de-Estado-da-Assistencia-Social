<?php

namespace App\Services\Reports;

use App\Exports\ArrayReportExport;
use App\Models\ReportExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;

class ReportExportGenerator
{
    public function generate(ReportExport $export): void
    {
        $export->loadMissing('user');
        $limit = (int) config("reports.exports.max_rows.{$export->format}");
        $report = app(ReportBuilder::class)->buildForExport($export->user, $export->filters, $limit);
        $disk = (string) config('reports.exports.disk', 'local');
        $filename = str($export->report)
            ->replace('_', '-')
            ->append('-'.$export->created_at->format('Ymd-His').'.'.$export->format)
            ->toString();
        $path = "report-exports/{$export->id}/{$filename}";

        $stored = match ($export->format) {
            'csv' => $this->storeCsv($disk, $path, $report),
            'xlsx' => Excel::store(new ArrayReportExport($report['headings'], $report['rows']), $path, $disk, ExcelWriter::XLSX),
            'pdf' => Storage::disk($disk)->put($path, Pdf::loadView('reports.generic', $report)->setPaper('a4', 'landscape')->output()),
            default => false,
        };

        if (! $stored) {
            throw new RuntimeException('Não foi possível armazenar o arquivo exportado.');
        }

        $export->update([
            'status' => ReportExport::STATUS_COMPLETED,
            'disk' => $disk,
            'path' => $path,
            'filename' => $filename,
            'row_count' => count($report['rows']),
            'error' => null,
            'completed_at' => now(),
            'expires_at' => now()->addMinutes((int) config('reports.exports.expires_after_minutes', 60)),
        ]);
    }

    private function storeCsv(string $disk, string $path, array $report): bool
    {
        $stream = fopen('php://temp', 'w+b');

        if ($stream === false) {
            return false;
        }

        fputcsv($stream, $report['headings'], ';');
        foreach ($report['rows'] as $row) {
            fputcsv($stream, $row, ';');
        }
        rewind($stream);

        try {
            return Storage::disk($disk)->writeStream($path, $stream);
        } finally {
            fclose($stream);
        }
    }
}
