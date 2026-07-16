<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\Placement;
use App\Models\Student;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('each role can reach its dashboard', function (RoleName $role): void {
    $this->actingAs(userWithRole($role))
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('dashboard')
            ->where('role', $role->value)
            ->where('roleLabel', $role->label())
        );
})->with([
    'super admin' => RoleName::SuperAdmin,
    'admin pkl' => RoleName::AdminPkl,
    'guru' => RoleName::GuruPembimbing,
    'industri' => RoleName::PembimbingIndustri,
    'siswa' => RoleName::Siswa,
]);

test('a user with no role is refused the dashboard', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/dashboard')->assertForbidden();
});

test('super admin sees real user counts rather than placeholders', function (): void {
    $user = userWithRole(RoleName::SuperAdmin);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('stats.0.label', 'Total Pengguna')
            ->where('stats.0.value', User::count())
            ->where('stats.0.pending', false)
        );
});

test('widgets without a data source are marked pending instead of showing a fake zero', function (): void {
    // Journals have no module yet, so this tile must not claim a number.
    $this->actingAs(userWithRole(RoleName::Siswa))
        ->get('/dashboard')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('stats.2.label', 'Kelengkapan Jurnal')
            ->where('stats.2.pending', true)
            ->where('stats.2.value', null)
        );
});

test('placement tiles report real counts now that the module exists', function (): void {
    $student = Student::factory()->create();
    Placement::factory()->active()->create(['student_id' => $student->id]);
    Placement::factory()->active()->count(2)->create();

    $this->actingAs(userWithRole(RoleName::AdminPkl))
        ->get('/dashboard')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('stats.0.label', 'PKL Aktif')
            ->where('stats.0.value', 3)
            ->where('stats.0.pending', false)
        );

    // The same tile is scoped: a student counts only their own.
    $this->actingAs($student->user)
        ->get('/dashboard')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('stats.0.label', 'PKL Aktif')
            ->where('stats.0.value', 1)
        );
});

test('only roles permitted to read the audit trail receive recent activities', function (): void {
    $this->actingAs(userWithRole(RoleName::SuperAdmin))
        ->get('/dashboard')
        ->assertInertia(fn (AssertableInertia $page) => $page->has('recentActivities'));

    // A siswa must never see other people's activity.
    $this->actingAs(userWithRole(RoleName::Siswa))
        ->get('/dashboard')
        ->assertInertia(fn (AssertableInertia $page) => $page->count('recentActivities', 0));
});

test('the shared auth payload exposes the role but never the password hash', function (): void {
    $user = userWithRole(RoleName::GuruPembimbing);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('auth.user.role', 'guru-pembimbing')
        ->where('auth.user.email', $user->email)
        ->missing('auth.user.password')
    );

    $response->assertDontSee($user->password, escape: false);
});
