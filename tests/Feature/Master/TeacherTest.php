<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\Teacher;
use App\Models\User;

beforeEach(function (): void {
    $this->admin = userWithRole(RoleName::AdminPkl);
});

function teacherPayload(array $overrides = []): array
{
    return array_merge([
        'nip' => '198501012010011001',
        'name' => 'Ahmad Fauzi',
        'email' => 'ahmad@praktika.test',
        'phone' => '081234567890',
    ], $overrides);
}

test('creating a teacher also creates a guru login account', function (): void {
    $this->actingAs($this->admin)
        ->post('/master/teachers', teacherPayload())
        ->assertRedirect();

    $user = Teacher::firstOrFail()->user;

    expect($user->email)->toBe('ahmad@praktika.test')
        ->and($user->hasRole(RoleName::GuruPembimbing->value))->toBeTrue()
        ->and($user->is_active)->toBeTrue();
});

test('show and edit return json for the modal', function (): void {
    $teacher = Teacher::factory()->create(['nip' => '123']);

    $this->actingAs($this->admin)
        ->getJson("/master/teachers/{$teacher->id}")
        ->assertOk()
        ->assertJsonPath('data.nip', '123')
        ->assertJsonPath('data.user.email', $teacher->user->email);

    $this->actingAs($this->admin)
        ->getJson("/master/teachers/{$teacher->id}/edit")
        ->assertOk()
        ->assertJsonPath('data.id', $teacher->id);
});

test('the teacher create page route is removed', function (): void {
    $this->actingAs($this->admin)->get('/master/teachers/create')->assertNotFound();
});

test('teacher create and update redirect to the index', function (): void {
    $this->actingAs($this->admin)
        ->post('/master/teachers', teacherPayload())
        ->assertRedirect('/master/teachers');

    $teacher = Teacher::firstOrFail();

    $this->actingAs($this->admin)
        ->put("/master/teachers/{$teacher->id}", teacherPayload(['name' => 'Baru']))
        ->assertRedirect('/master/teachers');
});

test('a failed teacher create leaves no orphan user behind', function (): void {
    User::factory()->create(['email' => 'taken@praktika.test']);
    $usersBefore = User::count();

    $this->actingAs($this->admin)
        ->post('/master/teachers', teacherPayload(['email' => 'taken@praktika.test']))
        ->assertSessionHasErrors('email');

    expect(User::count())->toBe($usersBefore)
        ->and(Teacher::count())->toBe(0);
});

test('nip must be unique', function (): void {
    Teacher::factory()->create(['nip' => '111']);

    $this->actingAs($this->admin)
        ->post('/master/teachers', teacherPayload(['nip' => '111']))
        ->assertSessionHasErrors('nip');
});

test('editing a teacher keeps its user account in step', function (): void {
    $teacher = Teacher::factory()->create();

    $this->actingAs($this->admin)
        ->put("/master/teachers/{$teacher->id}", teacherPayload([
            'name' => 'Nama Diubah',
            'email' => 'diubah@praktika.test',
        ]))
        ->assertRedirect();

    $teacher->refresh();

    expect($teacher->name)->toBe('Nama Diubah')
        ->and($teacher->user->name)->toBe('Nama Diubah')
        ->and($teacher->user->email)->toBe('diubah@praktika.test');
});

test('deleting a teacher deactivates the account rather than removing it', function (): void {
    $teacher = Teacher::factory()->create();
    $userId = $teacher->user_id;

    $this->actingAs($this->admin)
        ->delete("/master/teachers/{$teacher->id}")
        ->assertRedirect('/master/teachers');

    $this->assertSoftDeleted($teacher);
    $this->assertDatabaseHas('users', ['id' => $userId, 'is_active' => false]);
});

test('a guru can view their own profile but not delete it', function (): void {
    $teacher = Teacher::factory()->create();

    $this->actingAs($teacher->user)
        ->get("/master/teachers/{$teacher->id}")
        ->assertOk();

    // Deleting your own profile would deactivate the account you are acting from.
    expect($teacher->user->can('delete', $teacher))->toBeFalse();
});

test('creating a teacher is written to the audit trail', function (): void {
    $this->actingAs($this->admin)->post('/master/teachers', teacherPayload());

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'teacher',
        'event' => 'created',
    ]);
});
