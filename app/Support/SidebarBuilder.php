<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

/**
 * Builds the sidebar for the authenticated user.
 *
 * Items are filtered by *permission*, never by role name, so changing a role's
 * permissions in PermissionCatalog reshapes the sidebar without touching the frontend.
 *
 * The sidebar is cosmetic. Hiding an item is not access control — every route it
 * points at is independently protected by `can:` middleware and a policy.
 *
 * Modules that are not built yet are emitted with `enabled => false` so the shape of
 * the app is visible per role without producing links that 404.
 */
final class SidebarBuilder
{
    /**
     * @return array<int, array{title: string|null, items: array<int, array<string, mixed>>}>
     */
    public function build(User $user): array
    {
        $groups = [
            $this->group(null, [
                $this->item('Dashboard', 'LayoutDashboard', 'dashboard.view', route: 'dashboard'),
            ]),

            $this->group('Master Data', [
                $this->item('Tahun Ajaran', 'CalendarRange', 'academic-years.viewAny'),
                $this->item('Jurusan', 'Network', 'departments.viewAny', route: 'master.departments.index'),
                $this->item('Kelas', 'School', 'school-classes.viewAny', route: 'master.classes.index'),
                $this->item('Siswa', 'GraduationCap', 'students.viewAny', route: 'master.students.index'),
                $this->item('Guru', 'Users', 'teachers.viewAny', route: 'master.teachers.index'),
                $this->item('Perusahaan', 'Building2', 'companies.viewAny', route: 'master.companies.index'),
                $this->item('Pembimbing Industri', 'UserCog', 'company-supervisors.viewAny', route: 'master.supervisors.index'),
            ]),

            $this->group('PKL', [
                // Only a student holds attendances.checkIn, so this surfaces for them
                // alone — no role check needed here.
                $this->item('Absensi Hari Ini', 'Fingerprint', 'attendances.checkIn', route: 'attendances.check-in.create'),
                $this->item('Penempatan', 'MapPin', 'placements.viewAny', route: 'pkl.placements.index'),
                $this->item('Riwayat Absensi', 'CalendarCheck', 'attendances.viewAny', route: 'attendances.index'),
                $this->item('Jurnal', 'NotebookPen', 'journals.viewAny', route: 'journals.index'),
                $this->item('Monitoring', 'ClipboardCheck', 'monitorings.viewAny', route: 'monitorings.index'),
                $this->item('Penilaian', 'Star', 'assessments.viewAny', route: 'assessments.index'),
                $this->item('Nilai Akhir', 'Award', 'final-scores.view', route: 'final-scores.index'),
            ]),

            $this->group('Laporan', [
                $this->item('Rekap Absensi', 'FileSpreadsheet', 'reports.attendance'),
                $this->item('Rekap Jurnal', 'FileText', 'reports.journal'),
                $this->item('Nilai PKL', 'FileBadge', 'reports.assessment'),
                $this->item('Monitoring', 'FileSearch', 'reports.monitoring'),
            ]),

            $this->group('Sistem', [
                $this->item('Pengguna', 'UserRound', 'users.viewAny'),
                $this->item('Role & Permission', 'ShieldCheck', 'roles.viewAny'),
                $this->item('Pengaturan', 'Settings', 'settings.view'),
                $this->item('Log Aktivitas', 'ScrollText', 'activity-log.viewAny'),
            ]),
        ];

        return $this->filter($groups, $user);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{title: string|null, items: array<int, array<string, mixed>>}
     */
    private function group(?string $title, array $items): array
    {
        return ['title' => $title, 'items' => $items];
    }

    /**
     * @return array<string, mixed>
     */
    private function item(string $label, string $icon, string $permission, ?string $route = null): array
    {
        return [
            'label' => $label,
            'icon' => $icon,
            'permission' => $permission,
            'href' => $route === null ? null : route($route),
            'enabled' => $route !== null,
        ];
    }

    /**
     * Drop items the user cannot access, then drop groups left empty.
     *
     * @param  array<int, array{title: string|null, items: array<int, array<string, mixed>>}>  $groups
     * @return array<int, array{title: string|null, items: array<int, array<string, mixed>>}>
     */
    private function filter(array $groups, User $user): array
    {
        $visible = [];

        foreach ($groups as $group) {
            $items = array_values(array_filter(
                $group['items'],
                fn (array $item): bool => $user->can($item['permission']),
            ));

            if ($items !== []) {
                $visible[] = ['title' => $group['title'], 'items' => $items];
            }
        }

        return $visible;
    }
}
