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
