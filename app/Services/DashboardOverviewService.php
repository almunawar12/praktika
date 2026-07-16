<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\PlacementStatus;
use App\Enums\ReviewStatus;
use App\Enums\VerificationStatus;
use App\Models\Attendance;
use App\Models\Company;
use App\Models\Journal;
use App\Models\Placement;
use App\Models\Student;
use App\Models\User;
use Carbon\CarbonImmutable;

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
