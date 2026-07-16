<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\Department;
use App\Models\SchoolClass;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->admin = userWithRole(RoleName::AdminPkl);
});

test('admin can list departments', function (): void {
    Department::factory()->count(3)->create();

    $this->actingAs($this->admin)
        ->get('/master/departments')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('master/departments/index')
            ->has('departments.data', 3)
        );
});

test('admin can create a department', function (): void {
    $this->actingAs($this->admin)
        ->post('/master/departments', ['code' => 'RPL', 'name' => 'Rekayasa Perangkat Lunak'])
        ->assertRedirect();

    $this->assertDatabaseHas('departments', ['code' => 'RPL']);
});

test('department code must be unique', function (): void {
    Department::factory()->create(['code' => 'RPL']);

    $this->actingAs($this->admin)
        ->post('/master/departments', ['code' => 'RPL', 'name' => 'Duplikat'])
        ->assertSessionHasErrors('code');
});

test('a soft-deleted code can be reused', function (): void {
    $department = Department::factory()->create(['code' => 'RPL']);
    $department->delete();

    // The partial unique index only covers live rows, so the validator must agree.
    $this->actingAs($this->admin)
        ->post('/master/departments', ['code' => 'RPL', 'name' => 'Jurusan Baru'])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseCount('departments', 2);
});

test('a department with classes can not be deleted', function (): void {
    $department = Department::factory()->create();
    SchoolClass::factory()->create(['department_id' => $department->id]);

    $this->actingAs($this->admin)
        ->delete("/master/departments/{$department->id}")
        ->assertSessionHas('error');

    $this->assertNotSoftDeleted($department);
});

test('an empty department can be deleted', function (): void {
    $department = Department::factory()->create();

    $this->actingAs($this->admin)
        ->delete("/master/departments/{$department->id}")
        ->assertRedirect('/master/departments');

    $this->assertSoftDeleted($department);
});

test('a siswa can not reach the departments module', function (): void {
    $siswa = userWithRole(RoleName::Siswa);

    $this->actingAs($siswa)->get('/master/departments')->assertForbidden();
    $this->actingAs($siswa)->post('/master/departments', ['code' => 'X', 'name' => 'Y'])
        ->assertForbidden();
});

test('a guru may read departments but not change them', function (): void {
    $guru = userWithRole(RoleName::GuruPembimbing);
    $department = Department::factory()->create();

    $this->actingAs($guru)->get('/master/departments')->assertOk();
    $this->actingAs($guru)->get("/master/departments/{$department->id}")->assertOk();

    $this->actingAs($guru)->get('/master/departments/create')->assertForbidden();
    $this->actingAs($guru)
        ->put("/master/departments/{$department->id}", ['code' => 'Z', 'name' => 'Z'])
        ->assertForbidden();
    $this->actingAs($guru)->delete("/master/departments/{$department->id}")->assertForbidden();
});
