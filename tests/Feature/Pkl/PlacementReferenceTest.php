<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\Company;
use App\Models\Placement;
use App\Models\Student;
use App\Models\Teacher;

beforeEach(function (): void {
    $this->admin = userWithRole(RoleName::AdminPkl);
});

/*
| placements.{student,company,teacher}_id are all RESTRICT. Without these guards the
| delete would surface as a raw SQL error instead of something an admin can act on.
*/

test('a company with placement history can not be deleted', function (): void {
    $company = Company::factory()->create();
    Placement::factory()->create(['company_id' => $company->id]);

    $this->actingAs($this->admin)
        ->delete("/master/companies/{$company->id}")
        ->assertSessionHas('error');

    $this->assertNotSoftDeleted($company);
});

test('a company without placements can still be deleted', function (): void {
    $company = Company::factory()->create();

    $this->actingAs($this->admin)
        ->delete("/master/companies/{$company->id}")
        ->assertRedirect('/master/companies');

    $this->assertSoftDeleted($company);
});

test('a student with placement history can not be deleted', function (): void {
    $student = Student::factory()->create();
    Placement::factory()->create(['student_id' => $student->id]);

    $this->actingAs($this->admin)
        ->delete("/master/students/{$student->id}")
        ->assertSessionHas('error');

    $this->assertNotSoftDeleted($student);
    // The account must not be deactivated by a delete that did not happen.
    $this->assertDatabaseHas('users', ['id' => $student->user_id, 'is_active' => true]);
});

test('a teacher still supervising a placement can not be deleted', function (): void {
    $teacher = Teacher::factory()->create();
    Placement::factory()->create(['teacher_id' => $teacher->id]);

    $this->actingAs($this->admin)
        ->delete("/master/teachers/{$teacher->id}")
        ->assertSessionHas('error');

    $this->assertNotSoftDeleted($teacher);
    $this->assertDatabaseHas('users', ['id' => $teacher->user_id, 'is_active' => true]);
});

test('the student and company relations report their placements', function (): void {
    $student = Student::factory()->create();
    $company = Company::factory()->create(['quota' => 5]);

    Placement::factory()->active()->create([
        'student_id' => $student->id,
        'company_id' => $company->id,
    ]);
    Placement::factory()->completed()->create(['company_id' => $company->id]);

    expect($student->activePlacement()->exists())->toBeTrue()
        ->and($company->placements()->count())->toBe(2)
        // Only an active placement holds a quota slot.
        ->and($company->activePlacements()->count())->toBe(1);
});
