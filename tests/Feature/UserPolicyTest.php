<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\Role;
use App\Models\User;

test('super admin can manage other users', function (): void {
    $superAdmin = userWithRole(RoleName::SuperAdmin);
    $other = userWithRole(RoleName::Siswa);

    expect($superAdmin->can('viewAny', User::class))->toBeTrue()
        ->and($superAdmin->can('create', User::class))->toBeTrue()
        ->and($superAdmin->can('update', $other))->toBeTrue()
        ->and($superAdmin->can('delete', $other))->toBeTrue();
});

test('nobody can delete their own account, not even a super admin', function (): void {
    $superAdmin = userWithRole(RoleName::SuperAdmin);

    // This is the case a `Gate::before()` super admin bypass would silently break:
    // it would answer `true` before UserPolicy::delete ever runs.
    expect($superAdmin->can('delete', $superAdmin))->toBeFalse()
        ->and($superAdmin->can('toggleStatus', $superAdmin))->toBeFalse()
        ->and($superAdmin->can('impersonate', $superAdmin))->toBeFalse();
});

test('the last super admin can not be deleted or deactivated', function (): void {
    $lastSuperAdmin = userWithRole(RoleName::SuperAdmin);

    $actor = userWithRole(RoleName::AdminPkl);
    $actor->givePermissionTo(['users.delete', 'users.toggleStatus']);
    $actor->refresh();

    // Sole super admin: removing them would leave the system unadministrable.
    expect($actor->can('delete', $lastSuperAdmin))->toBeFalse()
        ->and($actor->can('toggleStatus', $lastSuperAdmin))->toBeFalse();

    // A second super admin exists, so the first is no longer load-bearing.
    userWithRole(RoleName::SuperAdmin);

    expect($actor->can('delete', $lastSuperAdmin))->toBeTrue()
        ->and($actor->can('toggleStatus', $lastSuperAdmin))->toBeTrue();
});

test('admin pkl can reset passwords but can not create or delete users', function (): void {
    $admin = userWithRole(RoleName::AdminPkl);
    $target = userWithRole(RoleName::Siswa);

    expect($admin->can('resetPassword', $target))->toBeTrue()
        ->and($admin->can('create', User::class))->toBeFalse()
        ->and($admin->can('delete', $target))->toBeFalse()
        ->and($admin->can('viewAny', User::class))->toBeFalse();
});

test('a siswa can view their own profile but not other users', function (): void {
    $siswa = userWithRole(RoleName::Siswa);
    $other = userWithRole(RoleName::GuruPembimbing);

    expect($siswa->can('view', $siswa))->toBeTrue()
        ->and($siswa->can('view', $other))->toBeFalse()
        ->and($siswa->can('update', $other))->toBeFalse();
});

test('built-in roles can not be renamed or deleted', function (): void {
    $superAdmin = userWithRole(RoleName::SuperAdmin);
    $builtIn = Role::where('name', RoleName::Siswa->value)->firstOrFail();

    expect($superAdmin->can('update', $builtIn))->toBeFalse()
        ->and($superAdmin->can('delete', $builtIn))->toBeFalse();
});

test('a custom role remains editable', function (): void {
    $superAdmin = userWithRole(RoleName::SuperAdmin);
    $custom = Role::findOrCreate('kepala-sekolah', 'web');

    expect($superAdmin->can('update', $custom))->toBeTrue()
        ->and($superAdmin->can('delete', $custom))->toBeTrue();
});
