<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AssessmentType;
use App\Enums\AttendanceStatus;
use App\Enums\PlacementStatus;
use App\Enums\ReviewStatus;
use App\Enums\VerificationStatus;
use App\Models\Assessment;
use App\Models\Attendance;
use App\Models\Company;
use App\Models\Journal;
use App\Models\Monitoring;
use App\Models\Placement;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Builds the rich dashboard payload for Admin PKL and Super Admin.
 *
 * Separate from DashboardService so the per-role tile logic and the admin
 * overview never tangle: this class knows nothing about roles — the caller
 * decides who gets an overview at all.
 */
class DashboardOverviewService
{
    /**
     * Memoised: the same figure feeds both the attendance tile and the donut.
     */
    private ?int $activeCount = null;

    /**
     * @return array<string, mixed>
     */
    public function forAdmin(User $user): array
    {
        return [
            'stats' => $this->stats($user),
            'activityChart' => $this->activityChart($user),
            'attendanceToday' => $this->attendanceToday($user),
            'pendingJournals' => $this->pendingJournals($user),
            'recentAssessments' => $this->recentAssessments($user),
            'activeByDepartment' => $this->activeByDepartment($user),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function forStudent(User $user): ?array
    {
        $placement = $user->student?->activePlacement()->with(['company', 'teacher'])->first();

        if ($placement === null) {
            return null;
        }

        return [
            'placement' => [
                'company' => $placement->company->name,
                'teacher' => $placement->teacher->name,
                'startDate' => $placement->start_date->toDateString(),
                'endDate' => $placement->end_date->toDateString(),
            ],
            'todayStatus' => $this->todayStatus($placement),
            'progress' => $this->placementProgress($placement),
            'attendanceRecap' => $this->attendanceRecap($placement),
            'journalToday' => $this->journalToday($placement),
            'journalCompletion' => $this->journalCompletion($placement),
            'journalStatus' => $this->journalStatusCounts($placement),
            'rejectedJournals' => $this->rejectedJournals($placement),
            'finalScore' => $this->studentFinalScore($placement),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function todayStatus(Placement $placement): array
    {
        $today = $placement->attendances()
            ->whereDate('date', CarbonImmutable::now()->toDateString())
            ->where('verification_status', '!=', VerificationStatus::Rejected)
            ->first();

        return [
            'status' => $today?->status->value,
            'checkInAt' => $today?->check_in_at?->toIso8601String(),
            'checkOutAt' => $today?->check_out_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function placementProgress(Placement $placement): array
    {
        $today = CarbonImmutable::now()->startOfDay();
        $start = $placement->start_date;
        $end = $placement->end_date;

        $totalDays = $start->diffInDays($end) + 1;

        $elapsedDays = match (true) {
            $today->lt($start) => 0,
            $today->gt($end) => $totalDays,
            default => $start->diffInDays($today) + 1,
        };

        return [
            'daysElapsed' => $elapsedDays,
            'totalDays' => $totalDays,
            'percentage' => (float) round($elapsedDays / $totalDays * 100, 1),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function attendanceRecap(Placement $placement): array
    {
        $counts = $placement->attendances()
            ->where('verification_status', '!=', VerificationStatus::Rejected)
            ->groupBy('status')
            ->selectRaw('status, count(*) as total')
            ->pluck('total', 'status');

        return [
            'hadir' => (int) ($counts[AttendanceStatus::Present->value] ?? 0),
            'izin' => (int) ($counts[AttendanceStatus::Permit->value] ?? 0),
            'sakit' => (int) ($counts[AttendanceStatus::Sick->value] ?? 0),
            'alpha' => (int) ($counts[AttendanceStatus::Absent->value] ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function journalToday(Placement $placement): array
    {
        $filled = $placement->journals()
            ->whereDate('date', CarbonImmutable::now()->toDateString())
            ->exists();

        return ['filled' => $filled];
    }

    /**
     * Filled journals against working days elapsed since the placement began —
     * weekends never counted against the student, so they are excluded here too.
     *
     * @return array<string, mixed>
     */
    private function journalCompletion(Placement $placement): array
    {
        $filled = $placement->journals()->count();

        $workdaysElapsed = 0;
        $cursor = $placement->start_date;
        $end = CarbonImmutable::now()->startOfDay()->min($placement->end_date);

        while ($cursor->lte($end)) {
            if (! $cursor->isWeekend()) {
                $workdaysElapsed++;
            }

            $cursor = $cursor->addDay();
        }

        return [
            'filled' => $filled,
            'workdaysElapsed' => $workdaysElapsed,
            'percentage' => $workdaysElapsed > 0
                ? (float) round(min($filled, $workdaysElapsed) / $workdaysElapsed * 100, 1)
                : 0.0,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function journalStatusCounts(Placement $placement): array
    {
        $counts = $placement->journals()
            ->groupBy('status')
            ->selectRaw('status, count(*) as total')
            ->pluck('total', 'status');

        return [
            'pending' => (int) ($counts[ReviewStatus::Pending->value] ?? 0),
            'approved' => (int) ($counts[ReviewStatus::Approved->value] ?? 0),
            'rejected' => (int) ($counts[ReviewStatus::Rejected->value] ?? 0),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rejectedJournals(Placement $placement): array
    {
        return $placement->journals()
            ->where('status', ReviewStatus::Rejected)
            ->latest('date')
            ->limit(5)
            ->get()
            ->map(fn (Journal $journal): array => [
                'id' => $journal->id,
                'date' => $journal->date->toDateString(),
                'note' => $journal->teacher_note ?? $journal->industry_note,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function studentFinalScore(Placement $placement): ?array
    {
        $finalScore = $placement->finalScore()->published()->first();

        if ($finalScore === null) {
            return null;
        }

        return [
            'score' => (float) $finalScore->final_score,
            'grade' => $finalScore->grade,
            'publishedAt' => $finalScore->published_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forTeacher(User $user): array
    {
        $teacher = $user->teacher;

        if ($teacher === null) {
            return [
                'studentsNeedingAttention' => [],
                'upcomingVisits' => [],
            ];
        }

        return [
            'studentsNeedingAttention' => $this->studentsNeedingAttention($user),
            'upcomingVisits' => $this->upcomingVisits($teacher),
        ];
    }

    /**
     * Active placements with either a high alpha count or low journal completion —
     * the two signals a supervisor actually needs to act on.
     *
     * @return array<int, array<string, mixed>>
     */
    private function studentsNeedingAttention(User $user): array
    {
        $placements = Placement::query()
            ->visibleTo($user)
            ->active()
            ->with(['student:id,name', 'company:id,name'])
            ->get();

        $rows = [];

        foreach ($placements as $placement) {
            $alphaCount = $placement->attendances()
                ->where('status', AttendanceStatus::Absent)
                ->count();

            $completion = $this->journalCompletion($placement);

            if ($alphaCount < 3 && $completion['percentage'] >= 60.0) {
                continue;
            }

            $rows[] = [
                'placementId' => $placement->id,
                'student' => $placement->student->name,
                'company' => $placement->company->name,
                'alphaCount' => $alphaCount,
                'journalPercentage' => $completion['percentage'],
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function upcomingVisits(Teacher $teacher): array
    {
        return app(MonitoringService::class)->needingVisit($teacher)
            ->map(fn (Placement $placement): array => [
                'placementId' => $placement->id,
                'student' => $placement->student->name,
                'company' => $placement->company->name,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function stats(User $user): array
    {
        $now = CarbonImmutable::now();
        $monthStart = $now->startOfMonth();

        // One grouped scan covers every placement figure on the page.
        $placements = Placement::query()
            ->visibleTo($user)
            ->groupBy('status')
            ->selectRaw('status, count(*) as total')
            ->pluck('total', 'status');

        $totalPlacements = (int) $placements->sum();
        $active = (int) ($placements[PlacementStatus::Active->value] ?? 0);
        $completed = (int) ($placements[PlacementStatus::Completed->value] ?? 0);

        $students = Student::count();
        $newStudents = Student::query()->where('created_at', '>=', $monthStart)->count();
        $companies = Company::count();
        $newCompanies = Company::query()->where('created_at', '>=', $monthStart)->count();

        $pendingJournals = Journal::query()
            ->visibleTo($user)
            ->where('status', ReviewStatus::Pending)
            ->count();

        $presentToday = Attendance::query()
            ->visibleTo($user)
            ->whereDate('date', $now->toDateString())
            ->where('status', AttendanceStatus::Present)
            ->where('verification_status', '!=', VerificationStatus::Rejected)
            ->count();

        return [
            $this->stat('Total Siswa PKL', 'GraduationCap', $students, $this->delta($newStudents)),
            $this->stat('Total Perusahaan', 'Building2', $companies, $this->delta($newCompanies)),
            $this->stat('PKL Aktif', 'MapPin', $active, [$this->share($active, $totalPlacements).' dari total', 'muted']),
            $this->stat('PKL Selesai', 'Award', $completed, [$this->share($completed, $totalPlacements).' dari total', 'muted']),
            $this->stat('Jurnal Pending', 'NotebookPen', $pendingJournals, ['Perlu verifikasi', $pendingJournals > 0 ? 'warning' : 'muted']),
            $this->stat('Kehadiran Hari Ini', 'CalendarCheck', $presentToday, [$this->share($presentToday, $this->activeCount($user)).' hadir', 'positive']),
        ];
    }

    private function activeCount(User $user): int
    {
        return $this->activeCount ??= Placement::query()->visibleTo($user)->active()->count();
    }

    /**
     * Daily activity counts for the last 30 days, zero-filled so the chart never
     * has to guess at missing days. The frontend slices 7/14/30.
     *
     * @return array<int, array<string, mixed>>
     */
    private function activityChart(User $user): array
    {
        $start = CarbonImmutable::now()->subDays(29)->startOfDay();

        $absensi = $this->dailyCounts(Attendance::query()->visibleTo($user), 'date', $start);
        $jurnal = $this->dailyCounts(Journal::query()->visibleTo($user), 'date', $start);
        $monitoring = $this->dailyCounts(Monitoring::query()->visibleTo($user), 'visit_date', $start);
        $penilaian = $this->dailyCounts(
            Assessment::query()->visibleTo($user)->whereNotNull('submitted_at'),
            'submitted_at',
            $start,
        );

        $days = [];

        for ($i = 0; $i < 30; $i++) {
            $date = $start->addDays($i)->toDateString();

            $days[] = [
                'date' => $date,
                'absensi' => $absensi[$date] ?? 0,
                'jurnal' => $jurnal[$date] ?? 0,
                'monitoring' => $monitoring[$date] ?? 0,
                'penilaian' => $penilaian[$date] ?? 0,
            ];
        }

        return $days;
    }

    /**
     * Count per calendar day. `date(...)` truncates timestamps in PostgreSQL.
     *
     * The column is `literal-string` rather than `string` so it can never carry
     * anything but the fixed names the callers pass — the raw fragments below are
     * built from it.
     *
     * @param  Builder<covariant Model>  $query
     * @param  literal-string  $column
     * @return array<string, int>
     */
    private function dailyCounts(Builder $query, string $column, CarbonImmutable $start): array
    {
        return $query
            ->where($column, '>=', $start)
            ->groupByRaw("date({$column})")
            ->selectRaw("date({$column}) as day, count(*) as total")
            ->pluck('total', 'day')
            ->mapWithKeys(fn (int|string $total, string $day): array => [
                CarbonImmutable::parse($day)->toDateString() => (int) $total,
            ])
            ->all();
    }

    /**
     * Today's attendance split. Alpha is derived, not stored: every active
     * placement not accounted for by a non-rejected hadir/izin/sakit record —
     * which also absorbs the nightly `absent` rows.
     *
     * @return array<string, mixed>
     */
    private function attendanceToday(User $user): array
    {
        $total = $this->activeCount($user);

        $today = Attendance::query()
            ->visibleTo($user)
            ->whereDate('date', CarbonImmutable::now()->toDateString())
            ->where('verification_status', '!=', VerificationStatus::Rejected)
            ->groupBy('status')
            ->selectRaw('status, count(*) as total')
            ->pluck('total', 'status');

        $hadir = (int) ($today[AttendanceStatus::Present->value] ?? 0);
        $izin = (int) ($today[AttendanceStatus::Permit->value] ?? 0);
        $sakit = (int) ($today[AttendanceStatus::Sick->value] ?? 0);

        return [
            'hadir' => $hadir,
            'izin' => $izin,
            'sakit' => $sakit,
            'alpha' => max(0, $total - $hadir - $izin - $sakit),
            'total' => $total,
            'rate' => $total > 0 ? (float) round($hadir / $total * 100, 1) : 0.0,
        ];
    }

    /**
     * @return array{string, string}
     */
    private function delta(int $added): array
    {
        return ['+'.$added.' dari bulan lalu', $added > 0 ? 'positive' : 'muted'];
    }

    /**
     * "75%"-style share, with the zero denominator guarded.
     */
    private function share(int $part, int $whole): string
    {
        if ($whole === 0) {
            return '0%';
        }

        return (string) (float) round($part / $whole * 100, 1).'%';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pendingJournals(User $user): array
    {
        return Journal::query()
            ->visibleTo($user)
            ->where('status', ReviewStatus::Pending)
            ->with(['placement.student.schoolClass', 'placement.company'])
            ->latest('created_at')
            ->limit(4)
            ->get()
            ->map(fn (Journal $journal): array => [
                'id' => $journal->id,
                'student' => $journal->placement->student->name,
                'className' => $journal->placement->student->schoolClass->name,
                'company' => $journal->placement->company->name,
                'submittedAt' => $journal->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentAssessments(User $user): array
    {
        return Assessment::query()
            ->visibleTo($user)
            ->whereNotNull('submitted_at')
            ->with(['placement.student', 'placement.company'])
            ->latest('submitted_at')
            ->limit(4)
            ->get()
            ->map(fn (Assessment $assessment): array => [
                'id' => $assessment->id,
                'student' => $assessment->placement->student->name,
                'company' => $assessment->placement->company->name,
                'type' => $assessment->type === AssessmentType::Industry ? 'Industri' : 'Guru',
                'score' => $assessment->average_score !== null ? (float) $assessment->average_score : null,
            ])
            ->all();
    }

    /**
     * Active placements per jurusan. Joins walk placement → student → class →
     * department; soft-deleted rows on the joined tables are excluded manually
     * since joins bypass the models' global scopes.
     *
     * @return array<int, array<string, mixed>>
     */
    private function activeByDepartment(User $user): array
    {
        $rows = Placement::query()
            ->visibleTo($user)
            ->active()
            ->join('students', 'students.id', '=', 'placements.student_id')
            ->join('school_classes', 'school_classes.id', '=', 'students.class_id')
            ->join('departments', 'departments.id', '=', 'school_classes.department_id')
            ->whereNull('students.deleted_at')
            ->whereNull('school_classes.deleted_at')
            ->whereNull('departments.deleted_at')
            ->groupBy('departments.id', 'departments.name')
            ->selectRaw('departments.name, count(*) as total')
            ->orderByDesc('total')
            // Aggregate rows, not placements: hydrating them into models would
            // produce Placement instances carrying neither id nor status.
            ->toBase()
            ->get();

        $totalActive = (int) $rows->sum('total');

        return $rows
            ->map(fn (object $row): array => [
                'name' => (string) $row->name,
                'count' => (int) $row->total,
                'percentage' => $totalActive > 0 ? (float) round((int) $row->total / $totalActive * 100, 1) : 0.0,
            ])
            ->all();
    }

    /**
     * @param  array{string, string}  $sub
     * @return array<string, mixed>
     */
    private function stat(string $label, string $icon, int $value, array $sub): array
    {
        return [
            'label' => $label,
            'icon' => $icon,
            'value' => $value,
            'subLabel' => $sub[0],
            'subVariant' => $sub[1],
        ];
    }
}
