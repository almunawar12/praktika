<?php

declare(strict_types=1);

namespace App\Services;

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
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
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
