<?php

declare(strict_types=1);

namespace App\Http\Controllers\Report;

use App\DataTransferObjects\ReportFilters;
use App\DataTransferObjects\TableRequest;
use App\Enums\PlacementStatus;
use App\Enums\ReportFormat;
use App\Enums\ReportType;
use App\Exports\ReportSpreadsheet;
use App\Http\Controllers\Controller;
use App\Http\Requests\Report\ReportFilterRequest;
use App\Models\Company;
use App\Models\Department;
use App\Models\Placement;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reports are read, not generated: each type is a page whose filters are its URL.
 * Nothing is written to disk — a download re-runs the same query and streams it.
 */
class ReportController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', ReportType::class);

        $user = $request->user();

        $first = collect(ReportType::cases())
            ->first(fn (ReportType $type): bool => $user->can($type->permission()));

        return redirect()->route('reports.show', ['type' => $first->value]);
    }

    public function show(ReportFilterRequest $request, ReportType $type, ReportService $service): Response
    {
        $this->authorize('view', $type);

        $user = $request->user();
        $filters = ReportFilters::fromRequest($request, $type);

        return Inertia::render('reports/show', [
            'type' => ['value' => $type->value, 'label' => $type->label()],
            'columns' => $type->columns(),
            'scope' => $filters->describeScope(),
            'rows' => $type === ReportType::StudentSummary
                ? null
                : $service->paginate($type, $filters, $user, $request->integer('page', 1)),
            'summary' => $type === ReportType::StudentSummary
                ? $service->studentSummary($filters, $user)
                : null,
            'filters' => $filters->toArray(),
            'table' => TableRequest::fromRequest($request, [
                'date_from', 'date_to', 'department_id', 'company_id',
                'school_class_id', 'teacher_id', 'student_id',
            ])->toArray(),
            'formats' => collect(ReportFormat::cases())
                ->map(fn (ReportFormat $format): array => ['value' => $format->value, 'label' => $format->label()])
                ->values(),
            'filterOptions' => $this->filterOptions($request, $type),
        ]);
    }

    public function download(ReportFilterRequest $request, ReportType $type, ReportService $service): StreamedResponse
    {
        $this->authorize('view', $type);

        $user = $request->user();
        $filters = ReportFilters::fromRequest($request, $type);

        // Rekap Siswa has no spreadsheet shape; it is always the PDF card.
        $format = $type === ReportType::StudentSummary
            ? ReportFormat::Pdf
            : ($request->enum('format', ReportFormat::class) ?? ReportFormat::Pdf);

        if ($type === ReportType::StudentSummary) {
            $summary = $service->studentSummary($filters, $user);

            abort_if($summary === null, 404, 'Siswa tidak memiliki penempatan yang dapat dilaporkan.');

            $contents = Pdf::loadView('reports.student-summary', ['dataset' => $summary])->output();
        } else {
            $dataset = $service->dataset($type, $filters, $user);

            $contents = match ($format) {
                ReportFormat::Pdf => Pdf::loadView('reports.pdf', ['dataset' => $dataset])->output(),
                ReportFormat::Xlsx => Excel::raw(new ReportSpreadsheet($dataset), ExcelWriter::XLSX),
            };
        }

        $fileName = Str::slug($type->label())
            .'-'.CarbonImmutable::now()->format('Ymd-His')
            .'.'.$format->extension();

        // No model to log against, so the download is recorded by hand — this is
        // the moment student data leaves the app.
        activity('report')
            ->causedBy($user)
            ->withProperties([
                'type' => $type->value,
                'format' => $format->value,
                'filters' => $filters->toArray(),
            ])
            ->log('report.download');

        return response()->streamDownload(
            fn () => print ($contents),
            $fileName,
            ['Content-Type' => $format->contentType()],
        );
    }

    /**
     * Option lists for the filter bar, each one scoped to what the user may see.
     *
     * @return array<string, mixed>
     */
    private function filterOptions(Request $request, ReportType $type): array
    {
        $user = $request->user();

        return [
            'departments' => $user->can('departments.viewAny')
                ? Department::query()->orderBy('name')->get(['id', 'name'])
                : [],
            'classes' => $user->can('school-classes.viewAny')
                ? SchoolClass::query()->orderBy('name')->get(['id', 'name'])
                : [],
            // Scoped: a supervisor only ever sees their own company here.
            'companies' => $user->can('companies.viewAny')
                ? Company::query()->visibleTo($user)->orderBy('name')->get(['id', 'name'])
                : [],
            'teachers' => $user->can('teachers.viewAny')
                ? Teacher::query()->orderBy('name')->get(['id', 'name'])
                : [],
            'students' => $type === ReportType::StudentSummary
                ? Placement::query()
                    ->visibleTo($user)
                    ->whereIn('status', [PlacementStatus::Active->value, PlacementStatus::Completed->value])
                    ->with('student.schoolClass')
                    ->get()
                    ->pluck('student')
                    ->unique('id')
                    ->sortBy('name')
                    ->map(fn (Student $student): array => [
                        'value' => $student->id,
                        'label' => "{$student->name} — {$student->nis} ({$student->schoolClass->name})",
                    ])
                    ->values()
                : [],
        ];
    }
}
