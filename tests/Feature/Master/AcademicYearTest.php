<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\AcademicYear;
use App\Models\Placement;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    // Only Super Admin has create/update/delete/activate on academic-years —
    // Admin PKL is view-only (PermissionCatalog::adminPkl()).
    $this->superAdmin = userWithRole(RoleName::SuperAdmin);
});

test('super admin can list academic years with overview stats', function (): void {
    AcademicYear::factory()->count(2)->create();
    AcademicYear::factory()->active()->create();

    $this->actingAs($this->superAdmin)
        ->get('/master/academic-years')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('master/academic-years/index')
            ->has('academicYears.data', 3)
            ->where('stats.total', 3)
            ->where('stats.active', 1)
        );
});

test('a new academic year starts inactive regardless of what is submitted', function (): void {
    $this->actingAs($this->superAdmin)
        ->post('/master/academic-years', [
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ])
        ->assertRedirect('/master/academic-years');

    $this->assertDatabaseHas('academic_years', ['name' => '2026/2027', 'is_active' => false]);
});

test('end date before start date is rejected', function (): void {
    $this->actingAs($this->superAdmin)
        ->post('/master/academic-years', [
            'name' => '2026/2027',
            'start_date' => '2027-06-30',
            'end_date' => '2026-07-01',
        ])
        ->assertSessionHasErrors('end_date');
});

test('academic year name must be unique among live rows', function (): void {
    AcademicYear::factory()->create(['name' => '2026/2027']);

    $this->actingAs($this->superAdmin)
        ->post('/master/academic-years', [
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
        ])
        ->assertSessionHasErrors('name');
});

test('a soft-deleted name can be reused', function (): void {
    $year = AcademicYear::factory()->create(['name' => '2026/2027']);
    $year->delete();

    $this->actingAs($this->superAdmin)
        ->post('/master/academic-years', [
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
        ])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseCount('academic_years', 2);
});

test('activating a year deactivates whichever year was active before', function (): void {
    $current = AcademicYear::factory()->active()->create();
    $next = AcademicYear::factory()->create();

    $this->actingAs($this->superAdmin)
        ->put("/master/academic-years/{$next->id}/activate")
        ->assertRedirect();

    expect($current->fresh()->is_active)->toBeFalse()
        ->and($next->fresh()->is_active)->toBeTrue();
});

test('a year with placements can not be deleted', function (): void {
    $year = AcademicYear::factory()->create();
    Placement::factory()->create(['academic_year_id' => $year->id]);

    $this->actingAs($this->superAdmin)
        ->delete("/master/academic-years/{$year->id}")
        ->assertSessionHas('error');

    $this->assertNotSoftDeleted($year);
});

test('an unused academic year can be deleted', function (): void {
    $year = AcademicYear::factory()->create();

    $this->actingAs($this->superAdmin)
        ->delete("/master/academic-years/{$year->id}")
        ->assertRedirect('/master/academic-years');

    $this->assertSoftDeleted($year);
});

test('the create page route is removed', function (): void {
    $this->actingAs($this->superAdmin)
        ->get('/master/academic-years/create')
        ->assertNotFound();
});

test('admin pkl may view academic years but not create, update, delete, or activate', function (): void {
    $adminPkl = userWithRole(RoleName::AdminPkl);
    $year = AcademicYear::factory()->create();

    $this->actingAs($adminPkl)->get('/master/academic-years')->assertOk();
    $this->actingAs($adminPkl)->getJson("/master/academic-years/{$year->id}")->assertOk();

    $this->actingAs($adminPkl)
        ->post('/master/academic-years', ['name' => 'X', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30'])
        ->assertForbidden();
    $this->actingAs($adminPkl)
        ->put("/master/academic-years/{$year->id}", ['name' => 'Y', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30'])
        ->assertForbidden();
    $this->actingAs($adminPkl)->delete("/master/academic-years/{$year->id}")->assertForbidden();
    $this->actingAs($adminPkl)->put("/master/academic-years/{$year->id}/activate")->assertForbidden();
});

test('a siswa can not reach the academic years module', function (): void {
    $siswa = userWithRole(RoleName::Siswa);

    $this->actingAs($siswa)->get('/master/academic-years')->assertForbidden();
});
