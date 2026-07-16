<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\Department;
use App\Models\SchoolClass;
use App\Models\Student;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->admin = userWithRole(RoleName::AdminPkl);
    $this->department = Department::factory()->create();
});

test('admin can create a class', function (): void {
    $this->actingAs($this->admin)
        ->post('/master/classes', ['department_id' => $this->department->id, 'name' => 'XII A'])
        ->assertRedirect();

    $this->assertDatabaseHas('school_classes', ['name' => 'XII A']);
});

test('class names are unique within a department but reusable across departments', function (): void {
    SchoolClass::factory()->create(['department_id' => $this->department->id, 'name' => 'XII A']);

    $this->actingAs($this->admin)
        ->post('/master/classes', ['department_id' => $this->department->id, 'name' => 'XII A'])
        ->assertSessionHasErrors('name');

    // RPL "XII A" and TKJ "XII A" are genuinely different classes.
    $other = Department::factory()->create();

    $this->actingAs($this->admin)
        ->post('/master/classes', ['department_id' => $other->id, 'name' => 'XII A'])
        ->assertSessionHasNoErrors();
});

test('a class with students can not be deleted', function (): void {
    $class = SchoolClass::factory()->create(['department_id' => $this->department->id]);
    Student::factory()->create(['class_id' => $class->id]);

    $this->actingAs($this->admin)
        ->delete("/master/classes/{$class->id}")
        ->assertSessionHas('error');

    $this->assertNotSoftDeleted($class);
});

test('classes can be filtered by department', function (): void {
    $other = Department::factory()->create();
    SchoolClass::factory()->count(2)->create(['department_id' => $this->department->id]);
    SchoolClass::factory()->count(3)->create(['department_id' => $other->id]);

    $this->actingAs($this->admin)
        ->get("/master/classes?department_id={$other->id}")
        ->assertInertia(fn (AssertableInertia $page) => $page->has('classes.data', 3));
});

test('a siswa can not reach the classes module', function (): void {
    $this->actingAs(userWithRole(RoleName::Siswa))
        ->get('/master/classes')
        ->assertForbidden();
});
