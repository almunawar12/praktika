<?php

declare(strict_types=1);

namespace App\Http\Controllers\Report;

use App\DataTransferObjects\TableRequest;
use App\Enums\ReportFormat;
use App\Enums\ReportType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Report\StoreReportRequest;
use App\Jobs\GenerateReportJob;
use App\Models\Company;
use App\Models\Department;
use App\Models\ReportExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function store(StoreReportRequest $request): RedirectResponse
    {
        $export = ReportExport::create([
            'requested_by' => $request->user()->id,
            'type' => $request->enum('type', ReportType::class),
            'format' => $request->enum('format', ReportFormat::class),
            'filters' => $request->filters(),
        ]);

        GenerateReportJob::dispatch($export);

        return back()->with('success', 'Laporan sedang dibuat. Anda akan dapat mengunduhnya begitu selesai.');
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
