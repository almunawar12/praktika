<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Enums\ReportType;
use App\Models\AcademicYear;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * The scope a report is being read at, taken straight from the URL.
 *
 * A report is no longer an artefact that gets stored; it is a query. This is
 * that query's parameters, normalised once so the service, the download, and
 * the activity log all read the same values.
 */
final readonly class ReportFilters
{
    private const DEFAULT_PER_PAGE = 15;

    private const MIN_PER_PAGE = 5;

    private const MAX_PER_PAGE = 100;

    public function __construct(
        public ?string $dateFrom,
        public ?string $dateTo,
        public ?string $departmentId,
        public ?string $companyId,
        public ?string $schoolClassId,
        public ?string $teacherId,
        public ?string $search,
        public ?string $studentId,
        public int $perPage,
    ) {}

    public static function fromRequest(Request $request, ReportType $type): self
    {
        $from = self::text($request, 'date_from');
        $to = self::text($request, 'date_to');

        // Only when the user has said nothing about the period: an explicit
        // half-open range is a deliberate choice and must not be widened.
        if ($from === null && $to === null) {
            $year = AcademicYear::query()->where('is_active', true)->first();

            if ($year !== null) {
                $from = $year->start_date->toDateString();
                $to = $year->end_date->toDateString();
            }
        }

        return new self(
            dateFrom: $from,
            dateTo: $to,
            departmentId: self::text($request, 'department_id'),
            companyId: self::text($request, 'company_id'),
            schoolClassId: self::text($request, 'school_class_id'),
            teacherId: self::text($request, 'teacher_id'),
            search: self::text($request, 'search'),
            // Only Rekap Siswa is about a single student; elsewhere the key is noise.
            studentId: $type === ReportType::StudentSummary ? self::text($request, 'student_id') : null,
            perPage: max(self::MIN_PER_PAGE, min(self::MAX_PER_PAGE, $request->integer('per_page', self::DEFAULT_PER_PAGE))),
        );
    }

    /**
     * The period in words, printed on the PDF and the spreadsheet header.
     */
    public function describeScope(): string
    {
        if ($this->dateFrom !== null && $this->dateTo !== null) {
            return 'Periode '.CarbonImmutable::parse($this->dateFrom)->format('d/m/Y')
                .' – '.CarbonImmutable::parse($this->dateTo)->format('d/m/Y');
        }

        if ($this->dateFrom !== null) {
            return 'Sejak '.CarbonImmutable::parse($this->dateFrom)->format('d/m/Y');
        }

        if ($this->dateTo !== null) {
            return 'Sampai '.CarbonImmutable::parse($this->dateTo)->format('d/m/Y');
        }

        return 'Seluruh periode';
    }

    /**
     * Echoed back to the frontend so the filter bar can render its current state,
     * and recorded on the activity log entry for a download.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'department_id' => $this->departmentId,
            'company_id' => $this->companyId,
            'school_class_id' => $this->schoolClassId,
            'teacher_id' => $this->teacherId,
            'search' => $this->search,
            'student_id' => $this->studentId,
            'per_page' => $this->perPage,
        ];
    }

    /**
     * `all` is what the shared FilterSelect submits for "no filter"; treat it,
     * blanks, and whitespace the same way.
     */
    private static function text(Request $request, string $key): ?string
    {
        $value = trim((string) $request->string($key));

        return $value === '' || $value === 'all' ? null : $value;
    }
}
