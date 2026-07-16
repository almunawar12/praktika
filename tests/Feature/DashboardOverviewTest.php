<?php

declare(strict_types=1);

use App\Enums\ReviewStatus;
use App\Enums\RoleName;
use App\Models\Company;
use App\Models\Journal;
use App\Models\Placement;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia;

test('admin roles receive the overview payload', function (RoleName $role): void {
    $this->actingAs(userWithRole($role))
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('overview.stats', 6)
            ->where('overview.stats.0.label', 'Total Siswa PKL')
            ->where('overview.stats.1.label', 'Total Perusahaan')
            ->where('overview.stats.2.label', 'PKL Aktif')
            ->where('overview.stats.3.label', 'PKL Selesai')
            ->where('overview.stats.4.label', 'Jurnal Pending')
            ->where('overview.stats.5.label', 'Kehadiran Hari Ini')
        );
})->with([
    'super admin' => RoleName::SuperAdmin,
    'admin pkl' => RoleName::AdminPkl,
]);

test('non-admin roles get no overview', function (RoleName $role): void {
    $this->actingAs(userWithRole($role))
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('overview', null));
})->with([
    'guru' => RoleName::GuruPembimbing,
    'industri' => RoleName::PembimbingIndustri,
    'siswa' => RoleName::Siswa,
]);

test('student and company tiles report the monthly delta', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-07-15 10:00:00'));

    // One student from last month, two new this month.
    Student::factory()->create(['created_at' => '2026-06-20 08:00:00']);
    Student::factory()->count(2)->create();
    Company::factory()->create(['created_at' => '2026-05-01 08:00:00']);

    $this->actingAs(userWithRole(RoleName::AdminPkl))
        ->get('/dashboard')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('overview.stats.0.value', Student::count())
            ->where('overview.stats.0.subLabel', '+2 dari bulan lalu')
            ->where('overview.stats.0.subVariant', 'positive')
            ->where('overview.stats.1.subLabel', '+0 dari bulan lalu')
            ->where('overview.stats.1.subVariant', 'muted')
        );
});

test('placement tiles report their share of all placements', function (): void {
    Placement::factory()->active()->count(3)->create();
    Placement::factory()->completed()->create();

    $this->actingAs(userWithRole(RoleName::AdminPkl))
        ->get('/dashboard')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('overview.stats.2.value', 3)
            ->where('overview.stats.2.subLabel', '75% dari total')
            ->where('overview.stats.3.value', 1)
            ->where('overview.stats.3.subLabel', '25% dari total')
        );
});

test('the journal tile counts pending journals and flags them', function (): void {
    Journal::factory()->create(['status' => ReviewStatus::Pending]);
    Journal::factory()->approved()->create();

    $this->actingAs(userWithRole(RoleName::AdminPkl))
        ->get('/dashboard')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('overview.stats.4.value', 1)
            ->where('overview.stats.4.subLabel', 'Perlu verifikasi')
            ->where('overview.stats.4.subVariant', 'warning')
        );
});
