<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RoleName;
use App\Models\Activity;
use App\Models\User;

/**
 * Assembles the dashboard payload for the authenticated user.
 *
 * The PKL modules do not exist yet, so their widgets are returned with
 * `pending => true` and no value. They are placeholders on purpose: inventing
 * numbers here would make the dashboard look finished while reporting nothing.
 * Widgets that can be answered from data that *does* exist (users, roles, the
 * audit trail) carry real values.
 */
class DashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user): array
    {
        $role = $user->roleName();

        return [
            'role' => $role?->value,
            'roleLabel' => $role?->label(),
            'stats' => $this->statsFor($role),
            'recentActivities' => $this->recentActivities($user),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function statsFor(?RoleName $role): array
    {
        return match ($role) {
            RoleName::SuperAdmin => [
                $this->stat('Total Pengguna', 'Users', User::count()),
                $this->stat('Pengguna Aktif', 'UserCheck', User::where('is_active', true)->count()),
                $this->pending('Total Siswa PKL', 'GraduationCap'),
                $this->pending('Total Perusahaan', 'Building2'),
            ],
            RoleName::AdminPkl => [
                $this->pending('Total Siswa PKL', 'GraduationCap'),
                $this->pending('PKL Aktif', 'MapPin'),
                $this->pending('Jurnal Pending', 'NotebookPen'),
                $this->pending('Kehadiran Hari Ini', 'CalendarCheck'),
            ],
            RoleName::GuruPembimbing => [
                $this->pending('Siswa Bimbingan', 'GraduationCap'),
                $this->pending('Jurnal Menunggu Review', 'NotebookPen'),
                $this->pending('Absensi Perlu Verifikasi', 'CalendarCheck'),
                $this->pending('Penilaian Belum Diisi', 'Star'),
            ],
            RoleName::PembimbingIndustri => [
                $this->pending('Siswa PKL', 'GraduationCap'),
                $this->pending('Kehadiran Hari Ini', 'CalendarCheck'),
                $this->pending('Jurnal Menunggu Review', 'NotebookPen'),
                $this->pending('Penilaian Belum Diisi', 'Star'),
            ],
            RoleName::Siswa => [
                $this->pending('Status Absensi Hari Ini', 'CalendarCheck'),
                $this->pending('Progres PKL', 'MapPin'),
                $this->pending('Kelengkapan Jurnal', 'NotebookPen'),
                $this->pending('Nilai Akhir', 'Award'),
            ],
            null => [],
        };
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
     * @return array<string, mixed>
     */
    private function stat(string $label, string $icon, int $value): array
    {
        return ['label' => $label, 'icon' => $icon, 'value' => $value, 'pending' => false];
    }

    /**
     * @return array<string, mixed>
     */
    private function pending(string $label, string $icon): array
    {
        return ['label' => $label, 'icon' => $icon, 'value' => null, 'pending' => true];
    }
}
