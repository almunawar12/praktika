<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\Placement;
use App\Models\Student;
use App\Models\Teacher;
use Inertia\Testing\AssertableInertia;

test('show returns json with placement, quota and abilities for the modal', function (): void {
    $admin = userWithRole(RoleName::AdminPkl);
    $placement = Placement::factory()->create();

    $this->actingAs($admin)
        ->getJson("/pkl/placements/{$placement->id}")
        ->assertOk()
        ->assertJsonPath('data.placement.id', $placement->id)
        ->assertJsonStructure(['data' => ['placement', 'remainingQuota', 'can' => ['update', 'delete', 'activate', 'complete', 'cancel']]]);
});

test('edit returns json for the modal', function (): void {
    $admin = userWithRole(RoleName::AdminPkl);
    $placement = Placement::factory()->create();

    $this->actingAs($admin)
        ->getJson("/pkl/placements/{$placement->id}/edit")
        ->assertOk()
        ->assertJsonPath('data.id', $placement->id);
});

test('the placement create page route is removed', function (): void {
    $admin = userWithRole(RoleName::AdminPkl);

    $this->actingAs($admin)->get('/pkl/placements/create')->assertNotFound();
});

test('placement update redirects to the index', function (): void {
    $admin = userWithRole(RoleName::AdminPkl);
    $placement = Placement::factory()->create();

    $this->actingAs($admin)
        ->put("/pkl/placements/{$placement->id}", [
            'student_id' => $placement->student_id,
            'company_id' => $placement->company_id,
            'teacher_id' => $placement->teacher_id,
            'start_date' => $placement->start_date->toDateString(),
            'end_date' => $placement->end_date->toDateString(),
        ])
        ->assertRedirect('/pkl/placements');
});

test('a guru sees only the placements they supervise', function (): void {
    $teacher = Teacher::factory()->create();
    $mine = Placement::factory()->count(2)->create(['teacher_id' => $teacher->id]);
    Placement::factory()->count(3)->create();

    $this->actingAs($teacher->user)
        ->get('/pkl/placements')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('placements.data', 2));

    $this->actingAs($teacher->user)
        ->get("/pkl/placements/{$mine->first()->id}")
        ->assertOk();
});

test('a guru can not open a placement they do not supervise', function (): void {
    $teacher = Teacher::factory()->create();
    $other = Placement::factory()->create();

    // Holding placements.view is not enough: the row must be theirs.
    $this->actingAs($teacher->user)
        ->get("/pkl/placements/{$other->id}")
        ->assertForbidden();
});

test('a siswa sees only their own placement', function (): void {
    $student = Student::factory()->create();
    $mine = Placement::factory()->create(['student_id' => $student->id]);
    $other = Placement::factory()->create();

    $this->actingAs($student->user)
        ->get('/pkl/placements')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('placements.data', 1));

    $this->actingAs($student->user)->get("/pkl/placements/{$mine->id}")->assertOk();
    $this->actingAs($student->user)->get("/pkl/placements/{$other->id}")->assertForbidden();
});

test('a guru can not create, edit or transition placements', function (): void {
    $teacher = Teacher::factory()->create();
    $placement = Placement::factory()->create(['teacher_id' => $teacher->id]);

    $this->actingAs($teacher->user)->post('/pkl/placements')->assertForbidden();
    $this->actingAs($teacher->user)->get("/pkl/placements/{$placement->id}/edit")->assertForbidden();
    $this->actingAs($teacher->user)->put("/pkl/placements/{$placement->id}/activate")->assertForbidden();
    $this->actingAs($teacher->user)->delete("/pkl/placements/{$placement->id}")->assertForbidden();
});

test('a siswa can not transition their own placement', function (): void {
    $student = Student::factory()->create();
    $placement = Placement::factory()->create(['student_id' => $student->id]);

    $this->actingAs($student->user)->put("/pkl/placements/{$placement->id}/activate")->assertForbidden();
    $this->actingAs($student->user)->put("/pkl/placements/{$placement->id}/complete")->assertForbidden();
    $this->actingAs($student->user)
        ->put("/pkl/placements/{$placement->id}/cancel", ['cancel_reason' => 'saya mau batal'])
        ->assertForbidden();
});

test('a pembimbing industri sees nothing until the supervisor module exists', function (): void {
    $industri = userWithRole(RoleName::PembimbingIndustri);
    Placement::factory()->count(3)->create();

    // There is no company link to scope by yet. Denying is the safe default —
    // returning unscoped would show one company's supervisor every school's data.
    $this->actingAs($industri)
        ->get('/pkl/placements')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('placements.data', 0));
});

test('an admin sees every placement', function (): void {
    Placement::factory()->count(4)->create();

    $this->actingAs(userWithRole(RoleName::AdminPkl))
        ->get('/pkl/placements')
        ->assertInertia(fn (AssertableInertia $page) => $page->has('placements.data', 4));
});

test('the placement index does not N+1 across relations', function (): void {
    $admin = userWithRole(RoleName::AdminPkl);

    $countQueries = function (int $rows) use ($admin): int {
        Placement::query()->forceDelete();
        Placement::factory()->count($rows)->create();

        // Warm the request path first so permission-cache lookups are not counted as
        // if they were per-row work.
        $this->actingAs($admin)->get('/pkl/placements');

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($admin)->get('/pkl/placements')->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    // The property that matters is that query count does not grow with row count —
    // a fixed threshold would only measure unrelated request overhead.
    expect($countQueries(25))->toBe($countQueries(5));
});
