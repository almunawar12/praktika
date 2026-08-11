<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\ReportDataset;
use App\DataTransferObjects\StudentSummaryDataset;
use App\Enums\AttendanceStatus;
use App\Enums\PlacementStatus;
use App\Enums\ReportType;
use App\Enums\ReviewStatus;
use App\Enums\VerificationStatus;
use App\Models\Placement;
use App\Models\ReportExport;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Turns a requested report into a table of rows.
 *
 * Every query runs through the requester's own visibility scope
 * (Placement::visibleTo), so a report can never widen what its owner is allowed
 * to see: a guru's recap covers their students, a supervisor's covers their
 * company. The output is format-agnostic — see ReportDataset.
 */
class ReportService
{
    public function build(ReportExport $export): ReportDataset
    {
        // The job re-fetches the export from its id, so the owner is not loaded yet;
        // both the visibility scope and the header need it.
        $export->loadMissing('requester');

        $placements = $this->scopedPlacements($export);

        $rows = match ($export->type) {
            ReportType::Attendance => $this->attendanceRows($placements, $export),
            ReportType::Journal => $this->journalRows($placements, $export),
            ReportType::Assessment => $this->assessmentRows($placements),
            ReportType::Monitoring => $this->monitoringRows($placements, $export),
        };

        return new ReportDataset(
            type: $export->type,
            title: $export->type->label(),
            scope: $this->describeScope($export),
            columns: $export->type->columns(),
            rows: $rows,
            requestedBy: $export->requester->name,
            generatedAt: CarbonImmutable::now()->format('d/m/Y H:i'),
        );
    }

    public function buildStudentSummary(ReportExport $export): StudentSummaryDataset
    {
        $export->loadMissing('requester');

        $studentId = $this->filter($export, 'student_id');

        if ($studentId === null) {
            throw new \RuntimeException('Siswa belum dipilih.');
        }

        $placement = Placement::query()
            ->visibleTo($export->requester)
            ->whereIn('status', [PlacementStatus::Active->value, PlacementStatus::Completed->value])
            ->where('student_id', $studentId)
            ->with(['student.schoolClass', 'company', 'teacher', 'finalScore'])
            ->latest('start_date')
            ->first();

        if ($placement === null) {
            throw new \RuntimeException('Siswa tidak memiliki penempatan aktif atau selesai yang dapat dilaporkan.');
        }

        $attendance = $this->studentAttendanceStats($placement, $export);
        $journal = $this->studentJournalStats($placement, $export);
        $score = $placement->finalScore;

        return new StudentSummaryDataset(
            studentName: $placement->student->name,
            nis: $placement->student->nis,
            className: $placement->student->schoolClass->name,
            companyName: $placement->company->name,
            teacherName: $placement->teacher->name,
            period: $placement->start_date->format('d/m/Y').' – '.$placement->end_date->format('d/m/Y'),
            scope: $this->describeScope($export),
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
            monitorings: $this->studentMonitoringVisits($placement, $export),
            requestedBy: $export->requester->name,
            generatedAt: CarbonImmutable::now()->format('d/m/Y H:i'),
        );
    }

    /**
     * @return array{present: int, permit: int, sick: int, absent: int, rate: string}
     */
    private function studentAttendanceStats(Placement $placement, ReportExport $export): array
    {
        $query = DB::table('attendances')
            ->where('placement_id', $placement->id)
            ->where('verification_status', '!=', VerificationStatus::Rejected->value);

        $this->applyDateRange($query, $export, 'date');

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
    private function studentJournalStats(Placement $placement, ReportExport $export): array
    {
        $query = DB::table('journals')->whereNull('deleted_at')->where('placement_id', $placement->id);

        $this->applyDateRange($query, $export, 'date');

        $counts = $query->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $approved = (int) ($counts[ReviewStatus::Approved->value] ?? 0);
        $pending = (int) ($counts[ReviewStatus::Pending->value] ?? 0);
        $rejected = (int) ($counts[ReviewStatus::Rejected->value] ?? 0);

        return ['total' => $approved + $pending + $rejected, 'approved' => $approved, 'pending' => $pending, 'rejected' => $rejected];
    }

    /**
     * @return array<int, array{visit_date: string, note: string, recommendation: ?string}>
     */
    private function studentMonitoringVisits(Placement $placement, ReportExport $export): array
    {
        $query = DB::table('monitorings')->whereNull('deleted_at')->where('placement_id', $placement->id);

        $this->applyDateRange($query, $export, 'visit_date');

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
     * Placements the owner may see, narrowed by the report's filters.
     *
     * Draft and cancelled placements are left out: a report is about PKL that is
     * happening or has happened, not proposals.
     *
     * @return Collection<int, Placement>
     */
    private function scopedPlacements(ReportExport $export): Collection
    {
        $companyId = $this->filter($export, 'company_id');
        $departmentId = $this->filter($export, 'department_id');

        return Placement::query()
            ->visibleTo($export->requester)
            ->whereIn('status', [PlacementStatus::Active->value, PlacementStatus::Completed->value])
            ->when($companyId, fn ($query, string $id) => $query->where('company_id', $id))
            ->when($departmentId, fn ($query, string $id) => $query
                ->whereHas('student.schoolClass', fn ($q) => $q->where('department_id', $id)))
            ->with([
                'student:id,name,nis',
                'company:id,name',
                'teacher:id,name',
            ])
            ->get()
            ->sortBy(fn (Placement $placement): string => $placement->student->name)
            ->values();
    }

    /**
     * @param  Collection<int, Placement>  $placements
     * @return array<int, array<int, string|int|float>>
     */
    private function attendanceRows(Collection $placements, ReportExport $export): array
    {
        // placement_id => [status => count]
        $counts = $this->countByStatus(
            DB::table('attendances')
                ->where('verification_status', '!=', VerificationStatus::Rejected->value),
            $export,
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
                $placement->student->name,
                $placement->student->nis,
                $placement->company->name,
                $present,
                $permit,
                $sick,
                $absent,
                "{$rate}%",
            ];
        })->all();
    }

    /**
     * @param  Collection<int, Placement>  $placements
     * @return array<int, array<int, string|int|float>>
     */
    private function journalRows(Collection $placements, ReportExport $export): array
    {
        $counts = $this->countByStatus(DB::table('journals')->whereNull('deleted_at'), $export, 'date');

        return $placements->map(function (Placement $placement) use ($counts): array {
            $row = $counts[$placement->id] ?? [];

            $approved = $row[ReviewStatus::Approved->value] ?? 0;
            $pending = $row[ReviewStatus::Pending->value] ?? 0;
            $rejected = $row[ReviewStatus::Rejected->value] ?? 0;

            return [
                $placement->student->name,
                $placement->student->nis,
                $placement->company->name,
                $approved + $pending + $rejected,
                $approved,
                $pending,
                $rejected,
            ];
        })->all();
    }

    /**
     * @param  Collection<int, Placement>  $placements
     * @return array<int, array<int, string|int|float>>
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
                $placement->student->name,
                $placement->student->nis,
                $placement->company->name,
                $score === null ? '-' : number_format((float) $score->industry_score, 2),
                $score === null ? '-' : number_format((float) $score->teacher_score, 2),
                $score === null ? '-' : number_format((float) $score->final_score, 2),
                $score === null ? '-' : $score->grade,
                $status,
            ];
        })->all();
    }

    /**
     * @param  Collection<int, Placement>  $placements
     * @return array<int, array<int, string|int|float>>
     */
    private function monitoringRows(Collection $placements, ReportExport $export): array
    {
        $query = DB::table('monitorings')
            ->whereIn('placement_id', $placements->pluck('id'))
            ->selectRaw('placement_id, count(*) as total, max(visit_date) as last_visit')
            ->groupBy('placement_id');

        $this->applyDateRange($query, $export, 'visit_date');

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
                $placement->student->name,
                $placement->student->nis,
                $placement->company->name,
                $placement->teacher->name,
                $stat['total'],
                $stat['last'] === null ? '-' : CarbonImmutable::parse($stat['last'])->format('d/m/Y'),
            ];
        })->all();
    }

    /**
     * Group a table's rows into `[placement_id => [status => count]]`, filtered to
     * the placements on this report and its date range.
     *
     * @return array<string, array<string, int>>
     */
    private function countByStatus(Builder $query, ReportExport $export, string $dateColumn): array
    {
        $this->applyDateRange($query, $export, $dateColumn);

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

    private function applyDateRange(Builder $query, ReportExport $export, string $column): void
    {
        $from = $this->filter($export, 'date_from');
        $to = $this->filter($export, 'date_to');

        if ($from !== null) {
            $query->whereDate($column, '>=', $from);
        }

        if ($to !== null) {
            $query->whereDate($column, '<=', $to);
        }
    }

    private function filter(ReportExport $export, string $key): ?string
    {
        $value = $export->filters[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function describeScope(ReportExport $export): string
    {
        $from = $this->filter($export, 'date_from');
        $to = $this->filter($export, 'date_to');

        if ($from !== null && $to !== null) {
            return 'Periode '.CarbonImmutable::parse($from)->format('d/m/Y').' – '.CarbonImmutable::parse($to)->format('d/m/Y');
        }

        if ($from !== null) {
            return 'Sejak '.CarbonImmutable::parse($from)->format('d/m/Y');
        }

        if ($to !== null) {
            return 'Sampai '.CarbonImmutable::parse($to)->format('d/m/Y');
        }

        return 'Seluruh periode';
    }
}
