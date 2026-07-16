<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\Attendance;
use App\Models\Company;
use App\Models\CompanySupervisor;
use App\Models\Placement;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia;

/*
| Until this module existed, Pembimbing Industri was denied everything rather than
| risk leaking. These tests pin down what the role can now see — and, more
| importantly, what it still cannot.
*/

beforeEach(function (): void {
    $this->company = Company::factory()->create(['quota' => 10]);
    $this->supervisor = CompanySupervisor::factory()->create(['company_id' => $this->company->id]);
});

test('a supervisor sees placements at their own company only', function (): void {
    $mine = Placement::factory()->count(2)->create(['company_id' => $this->company->id]);
    Placement::factory()->count(3)->create();

    $this->actingAs($this->supervisor->user)
        ->get('/pkl/placements')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('placements.data', 2));

    $this->actingAs($this->supervisor->user)
        ->get("/pkl/placements/{$mine->first()->id}")
        ->assertOk();
});

test('a supervisor can not open a placement at another company', function (): void {
    $other = Placement::factory()->create();

    $this->actingAs($this->supervisor->user)
        ->get("/pkl/placements/{$other->id}")
        ->assertForbidden();
});

test('a supervisor sees attendance from their own company only', function (): void {
    $placement = Placement::factory()->active()->create(['company_id' => $this->company->id]);

    // Distinct dates: attendance is unique per placement per day, and the factory's
    // default date is random over a 30-day window — two draws on one placement
    // collide often enough to fail the suite roughly one run in thirty.
    Attendance::factory()->count(2)->sequence(
        ['date' => CarbonImmutable::now()->subDay()->toDateString()],
        ['date' => CarbonImmutable::now()->subDays(2)->toDateString()],
    )->create(['placement_id' => $placement->id]);

    // These each get their own placement, so their random dates cannot collide.
    Attendance::factory()->count(3)->create();

    $this->actingAs($this->supervisor->user)
        ->get('/attendances')
        ->assertInertia(fn (AssertableInertia $page) => $page->has('attendances.data', 2));
});

test('a supervisor verifies attendance at their own company', function (): void {
    $placement = Placement::factory()->active()->create(['company_id' => $this->company->id]);
    $attendance = Attendance::factory()->create(['placement_id' => $placement->id]);

    $this->actingAs($this->supervisor->user)
        ->put("/attendances/{$attendance->id}/verify", ['decision' => 'verified'])
        ->assertSessionHasNoErrors();

    expect($attendance->refresh()->verified_by)->toBe($this->supervisor->user->id);
});

test('a supervisor can not verify attendance at another company', function (): void {
    $other = Attendance::factory()->create();

    $this->actingAs($this->supervisor->user)
        ->put("/attendances/{$other->id}/verify", ['decision' => 'verified'])
        ->assertForbidden();
});

test('a supervisor still can not create or transition placements', function (): void {
    $placement = Placement::factory()->create(['company_id' => $this->company->id]);

    // Placement is the school's decision, not the company's.
    $this->actingAs($this->supervisor->user)->get('/pkl/placements/create')->assertForbidden();
    $this->actingAs($this->supervisor->user)
        ->put("/pkl/placements/{$placement->id}/activate")
        ->assertForbidden();
});

test('a supervisor without a company link sees nothing', function (): void {
    // Role assigned but no profile row: the deny-by-default path must still hold.
    $orphan = userWithRole(RoleName::PembimbingIndustri);
    Placement::factory()->count(3)->create();

    $this->actingAs($orphan)
        ->get('/pkl/placements')
        ->assertInertia(fn (AssertableInertia $page) => $page->has('placements.data', 0));
});

test('a supervisor can not reach teachers or the supervisor registry', function (): void {
    foreach (['/master/teachers', '/master/supervisors', '/master/departments'] as $url) {
        $this->actingAs($this->supervisor->user)->get($url)->assertForbidden();
    }
});

test('a supervisor sees only students placed at their own company', function (): void {
    // The student directory holds names, NIS, NISN, email and phone numbers of
    // minors. A supervisor is external to the school and must not browse it.
    $mine = Student::factory()->create();
    Placement::factory()->create([
        'student_id' => $mine->id,
        'company_id' => $this->company->id,
    ]);

    $stranger = Student::factory()->create();
    Placement::factory()->create(['student_id' => $stranger->id]);
    Student::factory()->count(3)->create();

    $this->actingAs($this->supervisor->user)
        ->get('/master/students')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('students.data', 1));

    $this->actingAs($this->supervisor->user)->get("/master/students/{$mine->id}")->assertOk();
    $this->actingAs($this->supervisor->user)
        ->get("/master/students/{$stranger->id}")
        ->assertForbidden();
});

test('a supervisor sees only their own company', function (): void {
    Company::factory()->count(4)->create();

    $this->actingAs($this->supervisor->user)
        ->get('/master/companies')
        ->assertInertia(fn (AssertableInertia $page) => $page->has('companies.data', 1));

    $rival = Company::factory()->create();

    // Quota and PIC contact are commercial detail; a rival's supervisor has no
    // business reading them.
    $this->actingAs($this->supervisor->user)
        ->get("/master/companies/{$rival->id}")
        ->assertForbidden();
});

test('a siswa sees only the company they are placed at', function (): void {
    $student = Student::factory()->create();
    Placement::factory()->create([
        'student_id' => $student->id,
        'company_id' => $this->company->id,
    ]);
    $others = Company::factory()->count(3)->create();

    $this->actingAs($student->user)
        ->get('/master/companies')
        ->assertInertia(fn (AssertableInertia $page) => $page->has('companies.data', 1));

    $this->actingAs($student->user)
        ->get("/master/companies/{$others->first()->id}")
        ->assertForbidden();
});

test('school staff still see the whole directory', function (): void {
    Student::factory()->count(4)->create();
    Company::factory()->count(2)->create();

    foreach ([RoleName::AdminPkl, RoleName::GuruPembimbing] as $role) {
        $this->actingAs(userWithRole($role))
            ->get('/master/students')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('students.data', 4));
    }
});
