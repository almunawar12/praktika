<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AssessmentType;
use App\Enums\AttendanceStatus;
use App\Enums\PlacementStatus;
use App\Enums\ReviewStatus;
use App\Enums\RoleName;
use App\Enums\VerificationStatus;
use App\Models\Activity;
use App\Models\Assessment;
use App\Models\Attendance;
use App\Models\Company;
use App\Models\FinalScore;
use App\Models\Journal;
use App\Models\Monitoring;
use App\Models\Placement;
use App\Models\Student;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Assembles the dashboard payload for the authenticated user.
 *
 * Every tile now reports a real, role-scoped number — the placeholders are gone
 * along with the last module they were waiting on. Tiles are chosen for what
 * prompts action ("Perlu Dikunjungi", "Penilaian Belum Dikirim") rather than for
 * what is easy to count.
 */
class DashboardService
{
    public function __construct(private readonly DashboardOverviewService $overview) {}

    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user): array
    {
        $role = $user->roleName();

        return [
            'role' => $role?->value,
            'roleLabel' => $role?->label(),
            'stats' => $this->statsFor($user, $role),
            'recentActivities' => $this->recentActivities($user),
            'overview' => $this->overviewFor($user, $role),
        ];
    }

    /**
     * The rich overview exists only for the two admin roles; everyone else
     * renders the plain tile dashboard and gets an explicit null.
     *
     * @return array<string, mixed>|null
     */
    private function overviewFor(User $user, ?RoleName $role): ?array
    {
        if (! in_array($role, [RoleName::SuperAdmin, RoleName::AdminPkl], true)) {
            return null;
        }

        return $this->overview->forAdmin($user);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function statsFor(User $user, ?RoleName $role): array
    {
        // One grouped query for every placement number on the page, already scoped to
        // what this user may see. Counting each status separately would issue a query
        // per tile for a value the same scan already has.
        $placements = $this->placementCountsByStatus($user);

        return match ($role) {
            RoleName::SuperAdmin => [
                $this->stat('Total Pengguna', 'Users', User::count()),
                $this->stat('PKL Aktif', 'MapPin', $placements[PlacementStatus::Active->value]),
                $this->stat('Total Siswa', 'GraduationCap', Student::count()),
                $this->stat('Total Perusahaan', 'Building2', Company::count()),
            ],
            RoleName::AdminPkl => [
                $this->stat('PKL Aktif', 'MapPin', $placements[PlacementStatus::Active->value]),
                $this->stat('Hadir Hari Ini', 'CalendarCheck', $this->presentToday($user)),
                $this->stat('Absensi Perlu Verifikasi', 'Fingerprint', $this->awaitingVerification($user)),
                $this->stat('Jurnal Pending', 'NotebookPen', $this->journalsPending($user)),
                $this->stat('PKL Selesai', 'Award', $placements[PlacementStatus::Completed->value]),
            ],
            RoleName::GuruPembimbing => [
                $this->stat('Siswa Bimbingan Aktif', 'GraduationCap', $placements[PlacementStatus::Active->value]),
                $this->stat('Absensi Perlu Verifikasi', 'Fingerprint', $this->awaitingVerification($user)),
                // Only journals industry has already signed off are actually the
                // teacher's to review (PRD § 5).
                $this->stat('Jurnal Menunggu Review', 'NotebookPen', $this->journalsForTeacher($user)),
                $this->stat('Kunjungan Bulan Ini', 'ClipboardCheck', $this->visitsThisMonth($user)),
                // The number that actually prompts action.
                $this->stat('Perlu Dikunjungi', 'MapPin', $this->placementsNeedingVisit($user)),
                $this->stat('Penilaian Belum Dikirim', 'Star', $this->assessmentsPending($user, AssessmentType::Teacher)),
            ],
            RoleName::PembimbingIndustri => [
                $this->stat('Siswa PKL Aktif', 'GraduationCap', $placements[PlacementStatus::Active->value]),
                $this->stat('Hadir Hari Ini', 'CalendarCheck', $this->presentToday($user)),
                $this->stat('Absensi Perlu Verifikasi', 'Fingerprint', $this->awaitingVerification($user)),
                $this->stat('Jurnal Menunggu Review', 'NotebookPen', $this->journalsForIndustry($user)),
                $this->stat('Penilaian Belum Dikirim', 'Star', $this->assessmentsPending($user, AssessmentType::Industry)),
            ],
            RoleName::Siswa => [
                $this->stat('PKL Aktif', 'MapPin', $placements[PlacementStatus::Active->value]),
                $this->stat('Total Hadir', 'CalendarCheck', $this->presentCount($user)),
                $this->stat('Jurnal Ditolak', 'NotebookPen', $this->journalsRejected($user)),
                // Only a published score exists as far as a student is concerned.
                $this->stat('Nilai Dipublikasikan', 'Award', $this->publishedScores($user)),
            ],
            null => [],
        };
    }

    /**
     * Students recorded present today, within what this user may see.
     *
     * Rejected records are excluded: a claim that was refused is not attendance.
     */
    private function presentToday(User $user): int
    {
        return Attendance::query()
            ->visibleTo($user)
            ->whereDate('date', CarbonImmutable::now()->toDateString())
            ->where('status', AttendanceStatus::Present)
            ->where('verification_status', '!=', VerificationStatus::Rejected)
            ->count();
    }

    private function awaitingVerification(User $user): int
    {
        return Attendance::query()
            ->visibleTo($user)
            ->awaitingVerification()
            ->count();
    }

    /**
     * Assessable placements this user is responsible for that have no submitted
     * assessment of their side yet — the work still outstanding.
     */
    private function assessmentsPending(User $user, AssessmentType $type): int
    {
        return Placement::query()
            ->visibleTo($user)
            ->whereIn('status', array_column(AssessmentService::ASSESSABLE, 'value'))
            // Expressed as a subquery rather than whereDoesntHave(), which hands the
            // closure an untyped builder where the `submitted` scope will not resolve.
            ->whereNotIn(
                'id',
                Assessment::query()
                    ->where('type', $type)
                    ->submitted()
                    ->select('placement_id'),
            )
            ->count();
    }

    private function publishedScores(User $user): int
    {
        return FinalScore::query()->visibleTo($user)->published()->count();
    }

    private function visitsThisMonth(User $user): int
    {
        return Monitoring::query()
            ->visibleTo($user)
            ->where('visit_date', '>=', CarbonImmutable::now()->startOfMonth())
            ->count();
    }

    /**
     * Supervised placements with no visit in the last 30 days.
     */
    private function placementsNeedingVisit(User $user): int
    {
        if ($user->teacher === null) {
            return 0;
        }

        return app(MonitoringService::class)->needingVisit($user->teacher)->count();
    }

    /**
     * Journals not yet through both stages.
     */
    private function journalsPending(User $user): int
    {
        return Journal::query()
            ->visibleTo($user)
            ->where('status', ReviewStatus::Pending)
            ->count();
    }

    private function journalsForIndustry(User $user): int
    {
        return Journal::query()->visibleTo($user)->pendingForIndustry()->count();
    }

    private function journalsForTeacher(User $user): int
    {
        return Journal::query()->visibleTo($user)->pendingForTeacher()->count();
    }

    /**
     * The number that actually needs the student's attention.
     */
    private function journalsRejected(User $user): int
    {
        return Journal::query()
            ->visibleTo($user)
            ->where('status', ReviewStatus::Rejected)
            ->count();
    }

    private function presentCount(User $user): int
    {
        return Attendance::query()
            ->visibleTo($user)
            ->where('status', AttendanceStatus::Present)
            ->where('verification_status', '!=', VerificationStatus::Rejected)
            ->count();
    }

    /**
     * Placement counts per status, scoped to what this user may see.
     *
     * @return array<string, int>
     */
    private function placementCountsByStatus(User $user): array
    {
        $counts = Placement::query()
            ->visibleTo($user)
            ->groupBy('status')
            ->selectRaw('status, count(*) as total')
            ->pluck('total', 'status')
            ->all();

        $result = [];

        foreach (PlacementStatus::cases() as $status) {
            $result[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentActivities(User $user): array
    {
        if ($user->cannot('activity-log.viewAny')) {
            return [];
        }

        $activities = Activity::query()
            ->with('causer')
            ->latest()
            ->limit(10)
            ->get();

        $recent = [];

        foreach ($activities as $activity) {
            // `causer` is a morphTo and can point at any model; only a User has a
            // name worth showing, and system-generated entries have no causer at all.
            $causer = $activity->causer;

            $recent[] = [
                'id' => $activity->id,
                'description' => $activity->description,
                'event' => $activity->event,
                'causer' => $causer instanceof User ? $causer->name : null,
                'createdAt' => $activity->created_at?->toIso8601String(),
            ];
        }

        return $recent;
    }

    /**
     * The `pending` flag is kept in the payload even though every tile now reports a
     * real number: the frontend still honours it, and the next module to land will
     * need it again before its data exists.
     *
     * @return array<string, mixed>
     */
    private function stat(string $label, string $icon, int $value): array
    {
        return ['label' => $label, 'icon' => $icon, 'value' => $value, 'pending' => false];
    }
}
