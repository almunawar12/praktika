<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\User;
use App\Support\SidebarBuilder;

/**
 * @return array<int, string>
 */
function sidebarLabels(User $user): array
{
    $labels = [];

    foreach (app(SidebarBuilder::class)->build($user) as $group) {
        foreach ($group['items'] as $item) {
            $labels[] = $item['label'];
        }
    }

    return $labels;
}

test('super admin sees the system group', function (): void {
    $labels = sidebarLabels(userWithRole(RoleName::SuperAdmin));

    expect($labels)->toContain('Dashboard', 'Pengguna', 'Role & Permission', 'Pengaturan');
});

test('admin pkl sees pkl management but not user or role management', function (): void {
    $labels = sidebarLabels(userWithRole(RoleName::AdminPkl));

    expect($labels)->toContain('Dashboard', 'Siswa', 'Penempatan', 'Log Aktivitas')
        ->and($labels)->not->toContain('Pengguna')
        ->and($labels)->not->toContain('Role & Permission');
});

test('siswa sees only their own pkl items', function (): void {
    $labels = sidebarLabels(userWithRole(RoleName::Siswa));

    expect($labels)->toContain('Dashboard', 'Absensi Hari Ini', 'Riwayat Absensi', 'Jurnal')
        ->and($labels)->not->toContain('Pengguna')
        ->and($labels)->not->toContain('Siswa')
        ->and($labels)->not->toContain('Penilaian')
        ->and($labels)->not->toContain('Rekap Absensi');
});

test('pembimbing industri sees no monitoring or master data', function (): void {
    $labels = sidebarLabels(userWithRole(RoleName::PembimbingIndustri));

    expect($labels)->toContain('Dashboard', 'Jurnal', 'Penilaian')
        ->and($labels)->not->toContain('Monitoring')
        ->and($labels)->not->toContain('Jurusan')
        ->and($labels)->not->toContain('Pengguna');
});

test('the sidebar is filtered by permission, not by role name', function (): void {
    $user = userWithRole(RoleName::Siswa);

    expect(sidebarLabels($user))->not->toContain('Pengguna');

    // Granting the permission alone must surface the item, with no role change.
    $user->givePermissionTo('users.viewAny');
    $user->refresh();

    expect(sidebarLabels($user))->toContain('Pengguna');
});

test('unbuilt modules are disabled placeholders rather than dead links', function (): void {
    $groups = app(SidebarBuilder::class)->build(userWithRole(RoleName::SuperAdmin));

    $items = collect($groups)->flatMap(fn (array $group): array => $group['items']);

    $dashboard = $items->firstWhere('label', 'Dashboard');
    $students = $items->firstWhere('label', 'Siswa');
    // Still unbuilt as of the Master Data module.
    $academicYears = $items->firstWhere('label', 'Tahun Ajaran');

    expect($dashboard['enabled'])->toBeTrue()
        ->and($dashboard['href'])->toBe(route('dashboard'))
        ->and($students['enabled'])->toBeTrue()
        ->and($students['href'])->toBe(route('master.students.index'))
        ->and($academicYears['enabled'])->toBeFalse()
        ->and($academicYears['href'])->toBeNull();
});

test('every enabled sidebar link points at a route that exists', function (): void {
    foreach (RoleName::cases() as $role) {
        $groups = app(SidebarBuilder::class)->build(userWithRole($role));

        foreach ($groups as $group) {
            foreach ($group['items'] as $item) {
                if ($item['enabled']) {
                    $this->actingAs(userWithRole($role))
                        ->get($item['href'])
                        ->assertSuccessful();
                }
            }
        }
    }
});
