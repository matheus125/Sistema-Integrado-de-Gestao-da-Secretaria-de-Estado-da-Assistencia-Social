<?php

namespace App\Jobs;

use App\Exceptions\ReportExportLimitExceeded;
use App\Models\ReportExport;
use App\Services\Reports\ReportExportGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateReportExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout;

    public bool $failOnTimeout = true;

    public function __construct(public readonly ReportExport $export)
    {
        $this->timeout = (int) config('reports.exports.timeout', 120);
        $this->onQueue((string) config('reports.exports.queue', 'exports'));
    }

    public function handle(ReportExportGenerator $generator): void
    {
        $this->export->update([
            'status' => ReportExport::STATUS_PROCESSING,
            'error' => null,
        ]);

        try {
            $generator->generate($this->export);
        } catch (Throwable $exception) {
            $this->markFailed($exception);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->markFailed($exception);
    }

    private function markFailed(?Throwable $exception): void
    {
        $message = $exception instanceof ReportExportLimitExceeded
            ? $exception->getMessage()
            : 'A exportação não pôde ser concluída. Refine os filtros ou tente novamente mais tarde.';
        $this->export->refresh()->update([
            'status' => ReportExport::STATUS_FAILED,
            'error' => str($message)->limit(255)->toString(),
            'expires_at' => now()->addMinutes((int) config('reports.exports.expires_after_minutes', 60)),
        ]);

        Log::warning('Falha ao gerar exportação de relatório.', [
            'export_id' => $this->export->id,
            'report' => $this->export->report,
            'format' => $this->export->format,
            'exception' => $exception ? $exception::class : null,
        ]);
    }
}
