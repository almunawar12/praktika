<?php

declare(strict_types=1);

namespace App\Http\Controllers\Report;

use App\DataTransferObjects\TableRequest;
use App\Enums\ReportFormat;
use App\Enums\ReportStatus;
use App\Enums\ReportType;
use App\Exports\ReportSpreadsheet;
use App\Http\Controllers\Controller;
use App\Http\Requests\Report\StoreReportRequest;
use App\Models\Company;
use App\Models\Department;
use App\Models\ReportExport;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ReportExport::class);

        $user = $request->user();
        $table = TableRequest::fromRequest($request);

        $exports = ReportExport::query()
            ->visibleTo($user)
            ->latest()
            ->paginate($table->perPage)
            ->withQueryString();

        // A sidebar shortcut arrives as ?type=…; honour it only if the user may
        // actually generate that kind, otherwise let the form pick its first option.
        $requested = ReportType::tryFrom((string) $request->query('type', ''));
        $initialType = $requested !== null && $user->can($requested->permission())
            ? $requested->value
            : null;

        return Inertia::render('reports/index', [
            'exports' => $exports,
            'table' => $table->toArray(),
            'initialType' => $initialType,
            // Only the report kinds this user is allowed to generate.
            'types' => collect(ReportType::cases())
                ->filter(fn (ReportType $type): bool => $user->can($type->permission()))
                ->map(fn (ReportType $type): array => ['value' => $type->value, 'label' => $type->label()])
                ->values(),
            'formats' => collect(ReportFormat::cases())
                ->map(fn (ReportFormat $format): array => ['value' => $format->value, 'label' => $format->label()])
                ->values(),
            'filterOptions' => [
                'departments' => $user->can('departments.viewAny')
                    ? Department::query()->orderBy('name')->get(['id', 'name'])
                    : [],
                // Scoped: a supervisor only ever sees their own company here.
                'companies' => $user->can('companies.viewAny')
                    ? Company::query()->visibleTo($user)->orderBy('name')->get(['id', 'name'])
                    : [],
            ],
        ]);
    }

    public function store(StoreReportRequest $request, ReportService $service): RedirectResponse
    {
        $export = ReportExport::create([
            'requested_by' => $request->user()->id,
            'type' => $request->enum('type', ReportType::class),
            'format' => $request->enum('format', ReportFormat::class),
            'filters' => $request->filters(),
            'status' => ReportStatus::Processing,
        ]);

        try {
            $dataset = $service->build($export);

            $path = 'reports/'.$export->id.'.'.$export->format->extension();
            $fileName = Str::slug($export->type->label())
                .'-'.CarbonImmutable::now()->format('Ymd-His')
                .'.'.$export->format->extension();

            $contents = match ($export->format) {
                ReportFormat::Pdf => Pdf::loadView('reports.pdf', ['dataset' => $dataset])->output(),
                ReportFormat::Xlsx => Excel::raw(new ReportSpreadsheet($dataset), ExcelWriter::XLSX),
            };

            // Private disk: reports contain student data and are only ever streamed
            // back to their owner through the authorized download route.
            Storage::disk('local')->put($path, $contents);

            $export->update([
                'status' => ReportStatus::Completed,
                'file_path' => $path,
                'file_name' => $fileName,
                'row_count' => count($dataset->rows),
                'completed_at' => CarbonImmutable::now(),
            ]);
        } catch (Throwable $exception) {
            $export->update([
                'status' => ReportStatus::Failed,
                'error' => Str::limit($exception->getMessage(), 1000),
            ]);
        }

        return back()->with('success', 'Laporan berhasil dibuat.');
    }

    public function download(ReportExport $report): StreamedResponse
    {
        $this->authorize('download', $report);

        abort_unless($report->isDownloadable(), 404, 'Laporan belum siap.');

        $path = (string) $report->file_path;

        abort_unless(Storage::disk('local')->exists($path), 404, 'Berkas laporan tidak ditemukan.');

        return Storage::disk('local')->download(
            $path,
            $report->file_name ?? 'laporan.'.$report->format->extension(),
            ['Content-Type' => $report->format->contentType()],
        );
    }

    public function destroy(ReportExport $report): RedirectResponse
    {
        $this->authorize('delete', $report);

        if ($report->file_path !== null) {
            Storage::disk('local')->delete($report->file_path);
        }

        $report->delete();

        return back()->with('success', 'Laporan dihapus.');
    }
}
