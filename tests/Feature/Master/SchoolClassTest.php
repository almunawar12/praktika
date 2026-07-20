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
        ->assertRedirect('/master/classes');

    $this->assertDatabaseHas('school_classes', ['name' => 'XII A']);
});

test('show and edit return json for the modal', function (): void {
    $class = SchoolClass::factory()->create(['department_id' => $this->department->id, 'name' => 'XII A']);

    $this->actingAs($this->admin)
        ->getJson("/master/classes/{$class->id}")
        ->assertOk()
        ->assertJsonPath('data.name', 'XII A')
        ->assertJsonPath('data.department.id', $this->department->id);

    $this->actingAs($this->admin)
        ->getJson("/master/classes/{$class->id}/edit")
        ->assertOk()
        ->assertJsonPath('data.id', $class->id);
});

test('the class create page route is removed', function (): void {
    $this->actingAs($this->admin)->get('/master/classes/create')->assertNotFound();
});

test('class update redirects to the index', function (): void {
    $class = SchoolClass::factory()->create(['department_id' => $this->department->id]);

    $this->actingAs($this->admin)
        ->put("/master/classes/{$class->id}", ['department_id' => $this->department->id, 'name' => 'XII B'])
        ->assertRedirect('/master/classes');
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
