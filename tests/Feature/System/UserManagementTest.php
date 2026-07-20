<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('a super admin sees the user list; an admin pkl can not', function (): void {
    $this->actingAs(userWithRole(RoleName::SuperAdmin))
        ->get('/system/users')
        ->assertOk();

    $this->actingAs(userWithRole(RoleName::AdminPkl))
        ->get('/system/users')
        ->assertForbidden();
});

test('a super admin creates a staff account with a role', function (): void {
    $this->actingAs(userWithRole(RoleName::SuperAdmin))
        ->post('/system/users', [
            'name' => 'Budi',
            'email' => 'budi@sekolah.test',
            'phone' => '08123456789',
            'is_active' => true,
            'role' => RoleName::AdminPkl->value,
            'password' => 'rahasia-kuat-123',
            'password_confirmation' => 'rahasia-kuat-123',
        ])
        ->assertSessionHasNoErrors();

    $user = User::where('email', 'budi@sekolah.test')->sole();

    expect($user->hasRole(RoleName::AdminPkl->value))->toBeTrue()
        ->and($user->is_active)->toBeTrue();
});

test('edit returns json with user, hasProfile and abilities for the modal', function (): void {
    $admin = userWithRole(RoleName::SuperAdmin);
    $target = userWithRole(RoleName::AdminPkl);

    $this->actingAs($admin)
        ->getJson("/system/users/{$target->id}/edit")
        ->assertOk()
        ->assertJsonPath('data.user.id', $target->id)
        ->assertJsonStructure(['data' => ['user' => ['id', 'name', 'email', 'phone', 'role'], 'hasProfile', 'can' => ['delete', 'toggleStatus', 'resetPassword', 'impersonate']]]);
});

test('the user create page route is removed', function (): void {
    // With both `create` and `show` gone, no GET route matches this path — only the
    // PUT/DELETE {user} routes do, so the method is not allowed.
    $this->actingAs(userWithRole(RoleName::SuperAdmin))
        ->get('/system/users/create')
        ->assertStatus(405);
});

test('creating a user redirects to the index', function (): void {
    $this->actingAs(userWithRole(RoleName::SuperAdmin))
        ->post('/system/users', [
            'name' => 'Citra',
            'email' => 'citra@sekolah.test',
            'is_active' => true,
            'role' => RoleName::AdminPkl->value,
            'password' => 'rahasia-kuat-123',
            'password_confirmation' => 'rahasia-kuat-123',
        ])
        ->assertRedirect('/system/users');
});

test('this screen refuses to grant a profile-backed role', function (): void {
    // Siswa/Guru accounts are born from their own modules with their profile row;
    // minting one here would leave a user with no student record.
    $this->actingAs(userWithRole(RoleName::SuperAdmin))
        ->post('/system/users', [
            'name' => 'Siswa Palsu',
            'email' => 'palsu@sekolah.test',
            'is_active' => true,
            'role' => RoleName::Siswa->value,
            'password' => 'rahasia-kuat-123',
            'password_confirmation' => 'rahasia-kuat-123',
        ])
        ->assertSessionHasErrors('role');
});

test('nobody can deactivate or delete their own account', function (): void {
    $admin = userWithRole(RoleName::SuperAdmin);

    $this->actingAs($admin)->put("/system/users/{$admin->id}/status")->assertForbidden();
    $this->actingAs($admin)->delete("/system/users/{$admin->id}")->assertForbidden();
});

test('resetting a password changes the stored hash and reports the new one', function (): void {
    $admin = userWithRole(RoleName::SuperAdmin);
    $target = userWithRole(RoleName::AdminPkl);
    $before = $target->password;

    $this->actingAs($admin)
        ->put("/system/users/{$target->id}/reset-password")
        ->assertSessionHas('success', fn (string $message): bool => str_contains($message, 'Kata sandi baru'));

    expect($target->refresh()->password)->not->toBe($before)
        ->and(Hash::check('password', $target->password))->toBeFalse();
});

test('a super admin can toggle another account and update its fields', function (): void {
    $admin = userWithRole(RoleName::SuperAdmin);
    $target = userWithRole(RoleName::AdminPkl);

    $this->actingAs($admin)->put("/system/users/{$target->id}/status")->assertSessionHasNoErrors();
    expect($target->refresh()->is_active)->toBeFalse();

    $this->actingAs($admin)
        ->put("/system/users/{$target->id}", [
            'name' => 'Nama Baru',
            'email' => $target->email,
            'is_active' => true,
        ])
        ->assertSessionHasNoErrors();

    expect($target->refresh()->name)->toBe('Nama Baru')
        ->and($target->is_active)->toBeTrue();
});
