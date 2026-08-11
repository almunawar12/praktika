<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\ReportDataset;
use App\DataTransferObjects\ReportFilters;
use App\DataTransferObjects\StudentSummaryDataset;
use App\Enums\AttendanceStatus;
use App\Enums\PlacementStatus;
use App\Enums\ReportType;
use App\Enums\ReviewStatus;
use App\Enums\VerificationStatus;
use App\Models\Placement;
use App\Models\Student;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Facades\DB;

/**
 * Turns a set of filters into a table of rows.
 *
 * Every query runs through the reader's own visibility scope
 * (Placement::visibleTo), so a report can never widen what someone is allowed to
 * see: a guru's recap covers their students, a supervisor's covers their
 * company. The output is format-agnostic — see ReportDataset.
 */
class ReportService
{
    /**
     * One page of a report, aggregated only over the placements on that page.
     *
     * @return LengthAwarePaginator<int, array{key: string, cells: array<int, string|int|float>}>
     */
    public function paginate(ReportType $type, ReportFilters $filters, User $user, int $page = 1): LengthAwarePaginator
    {
        $placements = $this->placementQuery($filters, $user)
            ->paginate(perPage: $filters->perPage, page: $page);

        // A page of rows is not a page of placements, so the row paginator is its
        // own object rather than the placement one with its collection swapped.
        return (new Paginator(
            items: $this->rows($type, new Collection($placements->items()), $filters),
            total: $placements->total(),
            perPage: $placements->perPage(),
            currentPage: $placements->currentPage(),
            options: [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => 'page',
            ],
        ))->withQueryString();
    }

    /**
     * The whole report, for a download. Unlike paginate() this is never trimmed:
     * a file the user asked for must contain every row their filters describe.
     */
    public function dataset(ReportType $type, ReportFilters $filters, User $user): ReportDataset
    {
        $placements = $this->placementQuery($filters, $user)->get();

        return new ReportDataset(
            type: $type,
            title: $type->label(),
            scope: $filters->describeScope(),
            columns: $type->columns(),
            rows: array_column($this->rows($type, $placements, $filters), 'cells'),
            requestedBy: $user->name,
            generatedAt: CarbonImmutable::now()->format('d/m/Y H:i'),
        );
    }

    /**
     * @param  Collection<int, Placement>  $placements
     * @return array<int, array{key: string, cells: array<int, string|int|float>}>
     */
    private function rows(ReportType $type, Collection $placements, ReportFilters $filters): array
    {
        return match ($type) {
            ReportType::Attendance => $this->attendanceRows($placements, $filters),
            ReportType::Journal => $this->journalRows($placements, $filters),
            ReportType::Assessment => $this->assessmentRows($placements),
            ReportType::Monitoring => $this->monitoringRows($placements, $filters),
            // Rendered as a card, not a table — see studentSummary().
            ReportType::StudentSummary => [],
        };
    }

    /**
     * One student's whole PKL, or null when no student is chosen or the chosen
     * student has no placement the user may see. Both are ordinary states of the
     * screen, so neither throws.
     */
    public function studentSummary(ReportFilters $filters, User $user): ?StudentSummaryDataset
    {
        if ($filters->studentId === null) {
            return null;
        }

        $placement = Placement::query()
            ->visibleTo($user)
            ->whereIn('status', [PlacementStatus::Active->value, PlacementStatus::Completed->value])
            ->where('student_id', $filters->studentId)
            ->with(['student.schoolClass', 'company', 'teacher', 'finalScore'])
            ->latest('start_date')
            ->first();

        if ($placement === null) {
            return null;
        }

        $attendance = $this->studentAttendanceStats($placement, $filters);
        $journal = $this->studentJournalStats($placement, $filters);
        $score = $placement->finalScore;

        return new StudentSummaryDataset(
            studentName: $placement->student->name,
            nis: $placement->student->nis,
            className: $placement->student->schoolClass->name,
            companyName: $placement->company->name,
            teacherName: $placement->teacher->name,
            period: $placement->start_date->format('d/m/Y').' – '.$placement->end_date->format('d/m/Y'),
            scope: $filters->describeScope(),
            attendancePresent: $attendance['present'],
            attendancePermit: $attendance['permit'],
            attendanceSick: $attendance['sick'],
            attendanceAbsent: $attendance['absent'],
            attendanceRate: $attendance['rate'],
            journalTotal: $journal['total'],
            journalApproved: $journal['approved'],
            journalPending: $journal['pending'],
            journalRejected: $journal['rejected'],
            industryScore: $score === null ? null : number_format((float) $score->industry_score, 2),
            teacherScore: $score === null ? null : number_format((float) $score->teacher_score, 2),
            finalScore: $score === null ? null : number_format((float) $score->final_score, 2),
            grade: $score?->grade,
            assessmentStatus: match (true) {
                $score === null => 'Belum dinilai',
                $score->isPublished() => 'Dipublikasikan',
                default => 'Draft',
            },
            monitorings: $this->studentMonitoringVisits($placement, $filters),
            requestedBy: $user->name,
            generatedAt: CarbonImmutable::now()->format('d/m/Y H:i'),
        );
    }

    /**
     * @return array{present: int, permit: int, sick: int, absent: int, rate: string}
     */
    private function studentAttendanceStats(Placement $placement, ReportFilters $filters): array
    {
        $query = DB::table('attendances')
            ->where('placement_id', $placement->id)
            ->where('verification_status', '!=', VerificationStatus::Rejected->value);

        $this->applyDateRange($query, $filters, 'date');

        $counts = $query->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $present = (int) ($counts[AttendanceStatus::Present->value] ?? 0);
        $permit = (int) ($counts[AttendanceStatus::Permit->value] ?? 0);
        $sick = (int) ($counts[AttendanceStatus::Sick->value] ?? 0);
        $absent = (int) ($counts[AttendanceStatus::Absent->value] ?? 0);
        $total = $present + $permit + $sick + $absent;

        $rate = $total === 0 ? 0.0 : round($present / $total * 100, 1);

        return ['present' => $present, 'permit' => $permit, 'sick' => $sick, 'absent' => $absent, 'rate' => "{$rate}%"];
    }

    /**
     * @return array{total: int, approved: int, pending: int, rejected: int}
     */
    private function studentJournalStats(Placement $placement, ReportFilters $filters): array
    {
        $query = DB::table('journals')->whereNull('deleted_at')->where('placement_id', $placement->id);

        $this->applyDateRange($query, $filters, 'date');

        $counts = $query->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $approved = (int) ($counts[ReviewStatus::Approved->value] ?? 0);
        $pending = (int) ($counts[ReviewStatus::Pending->value] ?? 0);
        $rejected = (int) ($counts[ReviewStatus::Rejected->value] ?? 0);

        return ['total' => $approved + $pending + $rejected, 'approved' => $approved, 'pending' => $pending, 'rejected' => $rejected];
    }

    /**
     * @return array<int, array{visit_date: string, note: string, recommendation: ?string}>
     */
    private function studentMonitoringVisits(Placement $placement, ReportFilters $filters): array
    {
        $query = DB::table('monitorings')->whereNull('deleted_at')->where('placement_id', $placement->id);

        $this->applyDateRange($query, $filters, 'visit_date');

        return $query
            ->orderBy('visit_date')
            ->get(['visit_date', 'note', 'recommendation'])
            ->map(fn (object $row): array => [
                'visit_date' => CarbonImmutable::parse((string) $row->visit_date)->format('d/m/Y'),
                'note' => (string) $row->note,
                'recommendation' => $row->recommendation === null ? null : (string) $row->recommendation,
            ])
            ->all();
    }

    /**
     * Placements the user may see, narrowed by the filters.
     *
     * Draft and cancelled placements are left out: a report is about PKL that is
     * happening or has happened, not proposals. Ordering is a correlated
     * subquery rather than a join, so visibleTo()'s unqualified columns stay
     * unambiguous.
     *
     * @return EloquentBuilder<Placement>
     */
    private function placementQuery(ReportFilters $filters, User $user): EloquentBuilder
    {
        return Placement::query()
            ->visibleTo($user)
            ->whereIn('status', [PlacementStatus::Active->value, PlacementStatus::Completed->value])
            ->when($filters->companyId, fn (EloquentBuilder $query, string $id) => $query->where('company_id', $id))
            ->when($filters->teacherId, fn (EloquentBuilder $query, string $id) => $query->where('teacher_id', $id))
            ->when($filters->schoolClassId, fn (EloquentBuilder $query, string $id) => $query
                ->whereHas('student', fn ($student) => $student->where('class_id', $id)))
            ->when($filters->departmentId, fn (EloquentBuilder $query, string $id) => $query
                ->whereHas('student.schoolClass', fn ($class) => $class->where('department_id', $id)))
            ->when($filters->search, fn (EloquentBuilder $query, string $search) => $query
                ->whereHas('student', fn ($student) => $student
                    ->where('name', 'ilike', "%{$search}%")
                    ->orWhere('nis', 'ilike', "%{$search}%")))
            ->with([
                'student:id,name,nis',
                'company:id,name',
                'teacher:id,name',
            ])
            ->orderBy(Student::query()->select('name')->whereColumn('students.id', 'placements.student_id'));
    }

    /**
     * @param  Collection<int, Placement>  $placements
     * @return array<int, array{key: string, cells: array<int, string|int|float>}>
     */
    private function attendanceRows(Collection $placements, ReportFilters $filters): array
    {
        // placement_id => [status => count]
        $counts = $this->countByStatus(
            DB::table('attendances')
                ->whereIn('placement_id', $placements->pluck('id'))
                ->where('verification_status', '!=', VerificationStatus::Rejected->value),
            $filters,
            'date',
        );

        return $placements->map(function (Placement $placement) use ($counts): array {
            $row = $counts[$placement->id] ?? [];

            $present = $row[AttendanceStatus::Present->value] ?? 0;
            $permit = $row[AttendanceStatus::Permit->value] ?? 0;
            $sick = $row[AttendanceStatus::Sick->value] ?? 0;
            $absent = $row[AttendanceStatus::Absent->value] ?? 0;
            $total = $present + $permit + $sick + $absent;

            $rate = $total === 0 ? 0.0 : round($present / $total * 100, 1);

            return [
                'key' => $placement->id,
                'cells' => [
                    $placement->student->name,
                    $placement->student->nis,
                    $placement->company->name,
                    $present,
                    $permit,
                    $sick,
                    $absent,
                    "{$rate}%",
                ],
            ];
        })->all();
    }

    /**
     * @param  Collection<int, Placement>  $placements
     * @return array<int, array{key: string, cells: array<int, string|int|float>}>
     */
    private function journalRows(Collection $placements, ReportFilters $filters): array
    {
        $counts = $this->countByStatus(
            DB::table('journals')
                ->whereNull('deleted_at')
                ->whereIn('placement_id', $placements->pluck('id')),
            $filters,
            'date',
        );

        return $placements->map(function (Placement $placement) use ($counts): array {
            $row = $counts[$placement->id] ?? [];

            $approved = $row[ReviewStatus::Approved->value] ?? 0;
            $pending = $row[ReviewStatus::Pending->value] ?? 0;
            $rejected = $row[ReviewStatus::Rejected->value] ?? 0;

            return [
                'key' => $placement->id,
                'cells' => [
                    $placement->student->name,
                    $placement->student->nis,
                    $placement->company->name,
                    $approved + $pending + $rejected,
                    $approved,
                    $pending,
                    $rejected,
                ],
            ];
        })->all();
    }

    /**
     * @param  Collection<int, Placement>  $placements
     * @return array<int, array{key: string, cells: array<int, string|int|float>}>
     */
    private function assessmentRows(Collection $placements): array
    {
        $placements->load('finalScore');

        return $placements->map(function (Placement $placement): array {
            $score = $placement->finalScore;

            $status = match (true) {
                $score === null => 'Belum dinilai',
                $score->isPublished() => 'Dipublikasikan',
                default => 'Draft',
            };

            return [
                'key' => $placement->id,
                'cells' => [
                    $placement->student->name,
                    $placement->student->nis,
                    $placement->company->name,
                    $score === null ? '-' : number_format((float) $score->industry_score, 2),
                    $score === null ? '-' : number_format((float) $score->teacher_score, 2),
                    $score === null ? '-' : number_format((float) $score->final_score, 2),
                    $score === null ? '-' : $score->grade,
                    $status,
                ],
            ];
        })->all();
    }

    /**
     * @param  Collection<int, Placement>  $placements
     * @return array<int, array{key: string, cells: array<int, string|int|float>}>
     */
    private function monitoringRows(Collection $placements, ReportFilters $filters): array
    {
        $query = DB::table('monitorings')
            ->whereIn('placement_id', $placements->pluck('id'))
            ->selectRaw('placement_id, count(*) as total, max(visit_date) as last_visit')
            ->groupBy('placement_id');

        $this->applyDateRange($query, $filters, 'visit_date');

        // placement_id => ['total' => int, 'last' => ?string]
        $stats = [];

        foreach ($query->get() as $stat) {
            $stats[(string) $stat->placement_id] = [
                'total' => (int) $stat->total,
                'last' => $stat->last_visit === null ? null : (string) $stat->last_visit,
            ];
        }

        return $placements->map(function (Placement $placement) use ($stats): array {
            $stat = $stats[$placement->id] ?? ['total' => 0, 'last' => null];

            return [
                'key' => $placement->id,
                'cells' => [
                    $placement->student->name,
                    $placement->student->nis,
                    $placement->company->name,
                    $placement->teacher->name,
                    $stat['total'],
                    $stat['last'] === null ? '-' : CarbonImmutable::parse($stat['last'])->format('d/m/Y'),
                ],
            ];
        })->all();
    }

    /**
     * Group a table's rows into `[placement_id => [status => count]]`, filtered to
     * the placements on this report and its date range.
     *
     * @return array<string, array<string, int>>
     */
    private function countByStatus(Builder $query, ReportFilters $filters, string $dateColumn): array
    {
        $this->applyDateRange($query, $filters, $dateColumn);

        $rows = $query
            ->selectRaw('placement_id, status, count(*) as total')
            ->groupBy('placement_id', 'status')
            ->get();

        $counts = [];

        foreach ($rows as $row) {
            $counts[(string) $row->placement_id][(string) $row->status] = (int) $row->total;
        }

        return $counts;
    }

    private function applyDateRange(Builder $query, ReportFilters $filters, string $column): void
    {
        if ($filters->dateFrom !== null) {
            $query->whereDate($column, '>=', $filters->dateFrom);
        }

        if ($filters->dateTo !== null) {
            $query->whereDate($column, '<=', $filters->dateTo);
        }
    }
}
