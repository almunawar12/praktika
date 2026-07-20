<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->admin = userWithRole(RoleName::AdminPkl);
    $this->class = SchoolClass::factory()->create();
});

function studentPayload(SchoolClass $class, array $overrides = []): array
{
    return array_merge([
        'nis' => '1234567890',
        'nisn' => '0987654321',
        'name' => 'Budi Santoso',
        'email' => 'budi@praktika.test',
        'phone' => '081234567890',
        'class_id' => $class->id,
    ], $overrides);
}

test('creating a student also creates a siswa login account', function (): void {
    $this->actingAs($this->admin)
        ->post('/master/students', studentPayload($this->class))
        ->assertRedirect();

    $student = Student::firstOrFail();
    $user = $student->user;

    // Without this account the student could never check in or file a journal —
    // Praktika has no other way to hand out a login.
    expect($user)->not->toBeNull()
        ->and($user->email)->toBe('budi@praktika.test')
        ->and($user->name)->toBe('Budi Santoso')
        ->and($user->hasRole(RoleName::Siswa->value))->toBeTrue()
        ->and($user->is_active)->toBeTrue();
});

test('show and edit return json for the modal', function (): void {
    $student = Student::factory()->create(['class_id' => $this->class->id, 'nis' => '555']);

    $this->actingAs($this->admin)
        ->getJson("/master/students/{$student->id}")
        ->assertOk()
        ->assertJsonPath('data.nis', '555')
        ->assertJsonPath('data.school_class.id', $this->class->id)
        ->assertJsonPath('data.user.email', $student->user->email);

    $this->actingAs($this->admin)
        ->getJson("/master/students/{$student->id}/edit")
        ->assertOk()
        ->assertJsonPath('data.school_class.id', $this->class->id);
});

test('the student create page route is removed', function (): void {
    $this->actingAs($this->admin)->get('/master/students/create')->assertNotFound();
});

test('student create and update redirect to the index', function (): void {
    $this->actingAs($this->admin)
        ->post('/master/students', studentPayload($this->class))
        ->assertRedirect('/master/students');

    $student = Student::firstOrFail();

    $this->actingAs($this->admin)
        ->put("/master/students/{$student->id}", studentPayload($this->class, [
            'email' => $student->user->email,
            'name' => 'Baru',
        ]))
        ->assertRedirect('/master/students');
});

test('the generated password is random, not a shared default', function (): void {
    $this->actingAs($this->admin)
        ->post('/master/students', studentPayload($this->class));

    $user = Student::firstOrFail()->user;

    foreach (['password', 'praktika', '12345678', $user->email] as $guess) {
        expect(Hash::check($guess, $user->password))->toBeFalse();
    }
});

test('a failed student create leaves no orphan user behind', function (): void {
    $existing = User::factory()->create(['email' => 'taken@praktika.test']);
    $usersBefore = User::count();

    $this->actingAs($this->admin)
        ->post('/master/students', studentPayload($this->class, ['email' => $existing->email]))
        ->assertSessionHasErrors('email');

    expect(User::count())->toBe($usersBefore)
        ->and(Student::count())->toBe(0);
});

test('editing a student keeps its user account in step', function (): void {
    $student = Student::factory()->create(['class_id' => $this->class->id]);

    $this->actingAs($this->admin)
        ->put("/master/students/{$student->id}", studentPayload($this->class, [
            'name' => 'Nama Diubah',
            'email' => 'diubah@praktika.test',
        ]))
        ->assertRedirect();

    $student->refresh();

    // The profile and the account must not drift: users.name is what the sidebar
    // and the audit trail display.
    expect($student->name)->toBe('Nama Diubah')
        ->and($student->user->name)->toBe('Nama Diubah')
        ->and($student->user->email)->toBe('diubah@praktika.test');
});

test('a student email must not collide with any existing account', function (): void {
    $guru = userWithRole(RoleName::GuruPembimbing);

    $this->actingAs($this->admin)
        ->post('/master/students', studentPayload($this->class, ['email' => $guru->email]))
        ->assertSessionHasErrors('email');
});

test('saving a student without changing its email does not fail against itself', function (): void {
    $student = Student::factory()->create(['class_id' => $this->class->id]);

    $this->actingAs($this->admin)
        ->put("/master/students/{$student->id}", studentPayload($this->class, [
            'email' => $student->user->email,
            'name' => 'Masih Sama',
        ]))
        ->assertSessionHasNoErrors();
});

test('deleting a student deactivates the account rather than removing it', function (): void {
    $student = Student::factory()->create(['class_id' => $this->class->id]);
    $userId = $student->user_id;

    $this->actingAs($this->admin)
        ->delete("/master/students/{$student->id}")
        ->assertRedirect('/master/students');

    $this->assertSoftDeleted($student);

    // The user row stays: it is the causer on audit-log entries.
    $this->assertDatabaseHas('users', ['id' => $userId, 'is_active' => false]);
});

test('nis and nisn must be unique', function (): void {
    Student::factory()->create(['class_id' => $this->class->id, 'nis' => '111', 'nisn' => '222']);

    $this->actingAs($this->admin)
        ->post('/master/students', studentPayload($this->class, ['nis' => '111']))
        ->assertSessionHasErrors('nis');

    $this->actingAs($this->admin)
        ->post('/master/students', studentPayload($this->class, ['nisn' => '222']))
        ->assertSessionHasErrors('nisn');
});

test('the students module is admin-facing and closed to siswa entirely', function (): void {
    $student = Student::factory()->create(['class_id' => $this->class->id]);

    // Gated at the route by `can:students.viewAny`, which siswa does not hold — so a
    // siswa never reaches this module, not even for their own row.
    $this->actingAs($student->user)->get('/master/students')->assertForbidden();
    $this->actingAs($student->user)->get("/master/students/{$student->id}")->assertForbidden();
});

test('the policy still lets a student read their own profile, for student-facing pages', function (): void {
    $student = Student::factory()->create(['class_id' => $this->class->id]);
    $other = Student::factory()->create(['class_id' => $this->class->id]);

    // Not reachable through /master/students above, but this is the check a future
    // "Profil Saya" page will make.
    expect($student->user->can('view', $student))->toBeTrue()
        ->and($student->user->can('view', $other))->toBeFalse()
        ->and($student->user->can('update', $student))->toBeFalse();
});

test('students can be filtered by class', function (): void {
    $other = SchoolClass::factory()->create();
    Student::factory()->count(2)->create(['class_id' => $this->class->id]);
    Student::factory()->count(3)->create(['class_id' => $other->id]);

    $this->actingAs($this->admin)
        ->get("/master/students?class_id={$other->id}")
        ->assertInertia(fn (AssertableInertia $page) => $page->has('students.data', 3));
});

test('students can be searched by nis and name', function (): void {
    Student::factory()->create(['class_id' => $this->class->id, 'name' => 'Siti Aminah', 'nis' => '9999']);
    Student::factory()->count(4)->create(['class_id' => $this->class->id]);

    $this->actingAs($this->admin)
        ->get('/master/students?search=Siti')
        ->assertInertia(fn (AssertableInertia $page) => $page->has('students.data', 1));

    $this->actingAs($this->admin)
        ->get('/master/students?search=9999')
        ->assertInertia(fn (AssertableInertia $page) => $page->has('students.data', 1));
});
