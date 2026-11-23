<?php

namespace App\Console\Commands;

use App\Models\ReportExport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneReportExports extends Command
{
    protected $signature = 'reports:prune-exports';

    protected $description = 'Remove arquivos de relatórios expirados e seus registros';

    public function handle(): int
    {
        $removed = 0;

        ReportExport::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->chunkById(100, function ($exports) use (&$removed): void {
                foreach ($exports as $export) {
                    if ($export->disk && $export->path) {
                        Storage::disk($export->disk)->delete($export->path);
                    }
                    $export->delete();
                    $removed++;
                }
            });

        $this->info("Exportações expiradas removidas: {$removed}");

        return self::SUCCESS;
    }
}
