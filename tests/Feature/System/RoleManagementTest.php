<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\Role;

test('a super admin manages roles; an admin pkl can not reach them', function (): void {
    $this->actingAs(userWithRole(RoleName::SuperAdmin))->get('/system/roles')->assertOk();
    $this->actingAs(userWithRole(RoleName::AdminPkl))->get('/system/roles')->assertForbidden();
});

test('a custom role can be created, given permissions, and deleted', function (): void {
    $admin = userWithRole(RoleName::SuperAdmin);

    $this->actingAs($admin)->post('/system/roles', ['name' => 'Kepala Sekolah'])->assertSessionHasNoErrors();

    $role = Role::where('name', 'kepala-sekolah')->sole();

    $this->actingAs($admin)
        ->put("/system/roles/{$role->id}", ['permissions' => ['students.viewAny', 'reports.attendance']])
        ->assertSessionHasNoErrors();

    expect($role->fresh()->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['reports.attendance', 'students.viewAny']);

    $this->actingAs($admin)->delete("/system/roles/{$role->id}")->assertSessionHasNoErrors();
    expect(Role::where('name', 'kepala-sekolah')->exists())->toBeFalse();
});

test('a duplicate role name is rejected', function (): void {
    $admin = userWithRole(RoleName::SuperAdmin);
    Role::findOrCreate('kepala-sekolah', 'web');

    $this->actingAs($admin)
        ->post('/system/roles', ['name' => 'Kepala Sekolah'])
        ->assertSessionHasErrors('name');
});

test('a built-in role can not be edited or deleted', function (): void {
    $admin = userWithRole(RoleName::SuperAdmin);
    $builtIn = Role::where('name', RoleName::Siswa->value)->sole();

    $this->actingAs($admin)
        ->put("/system/roles/{$builtIn->id}", ['permissions' => []])
        ->assertForbidden();

    $this->actingAs($admin)->delete("/system/roles/{$builtIn->id}")->assertForbidden();
});

test('only real permissions from the catalogue may be granted', function (): void {
    $admin = userWithRole(RoleName::SuperAdmin);
    $role = Role::findOrCreate('kepala-sekolah', 'web');

    $this->actingAs($admin)
        ->put("/system/roles/{$role->id}", ['permissions' => ['students.viewAny', 'not.a.permission']])
        ->assertSessionHasErrors('permissions.1');
});
