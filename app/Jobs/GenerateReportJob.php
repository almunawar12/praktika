<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ReportFormat;
use App\Enums\ReportStatus;
use App\Exports\ReportSpreadsheet;
use App\Models\ReportExport;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * Builds a report file off the request/response cycle.
 *
 * The export row is created `pending` by the controller; this job produces the
 * file, then flips the row to `completed`. Rendering a spreadsheet over hundreds
 * of placements is not something to make an admin wait on synchronously.
 */
class GenerateReportJob implements ShouldQueue
{
    use Queueable;

    /**
     * A report either builds or it does not — retrying a deterministic render just
     * repeats the same failure. One transient DB retry is enough.
     */
    public int $tries = 2;

    public function __construct(private readonly ReportExport $export) {}

    public function handle(ReportService $service): void
    {
        $this->export->update(['status' => ReportStatus::Processing]);

        $dataset = $service->build($this->export);

        $path = 'reports/'.$this->export->id.'.'.$this->export->format->extension();
        $fileName = Str::slug($this->export->type->label())
            .'-'.CarbonImmutable::now()->format('Ymd-His')
            .'.'.$this->export->format->extension();

        $contents = match ($this->export->format) {
            ReportFormat::Pdf => Pdf::loadView('reports.pdf', ['dataset' => $dataset])->output(),
            ReportFormat::Xlsx => Excel::raw(new ReportSpreadsheet($dataset), ExcelWriter::XLSX),
        };

        // Private disk: reports contain student data and are only ever streamed back
        // to their owner through the authorized download route.
        Storage::disk('local')->put($path, $contents);

        $this->export->update([
            'status' => ReportStatus::Completed,
            'file_path' => $path,
            'file_name' => $fileName,
            'row_count' => count($dataset->rows),
            'completed_at' => CarbonImmutable::now(),
            'error' => null,
        ]);
    }

    /**
     * Called by the queue once retries are exhausted. Records why, so the owner
     * sees a real reason instead of a row stuck on "processing" forever.
     */
    public function failed(Throwable $exception): void
    {
        $this->export->update([
            'status' => ReportStatus::Failed,
            'file_path' => null,
            'error' => Str::limit($exception->getMessage(), 1000),
        ]);
    }
}
