<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\Permission;
use App\Models\Role;
use App\Support\PermissionCatalog;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Str;

test('every role in the enum is seeded', function (): void {
    foreach (RoleName::cases() as $role) {
        expect(Role::where('name', $role->value)->exists())->toBeTrue();
    }

    expect(Role::count())->toBe(count(RoleName::cases()));
});

test('the whole permission catalogue is seeded', function (): void {
    expect(Permission::count())->toBe(count(PermissionCatalog::all()));
});

test('roles and permissions use uuid primary keys', function (): void {
    expect(Str::isUuid(Role::first()->id))->toBeTrue()
        ->and(Str::isUuid(Permission::first()->id))->toBeTrue();
});

test('super admin holds every permission', function (): void {
    $user = userWithRole(RoleName::SuperAdmin);

    foreach (PermissionCatalog::all() as $permission) {
        expect($user->can($permission))->toBeTrue("super admin should hold {$permission}");
    }
});

test('the seeder is idempotent and can be re-run safely', function (): void {
    $roles = Role::count();
    $permissions = Permission::count();

    $this->seed(RolePermissionSeeder::class);

    expect(Role::count())->toBe($roles)
        ->and(Permission::count())->toBe($permissions);
});

test('siswa can manage journals but never other people accounts', function (): void {
    $user = userWithRole(RoleName::Siswa);

    expect($user->can('journals.create'))->toBeTrue()
        ->and($user->can('attendances.checkIn'))->toBeTrue()
        ->and($user->can('users.delete'))->toBeFalse()
        ->and($user->can('users.viewAny'))->toBeFalse()
        ->and($user->can('journals.reviewAsTeacher'))->toBeFalse()
        ->and($user->can('final-scores.publish'))->toBeFalse();
});

test('guru reviews as teacher and never as industry', function (): void {
    $user = userWithRole(RoleName::GuruPembimbing);

    expect($user->can('journals.reviewAsTeacher'))->toBeTrue()
        ->and($user->can('assessments.submitAsTeacher'))->toBeTrue()
        ->and($user->can('journals.reviewAsIndustry'))->toBeFalse()
        ->and($user->can('assessments.submitAsIndustry'))->toBeFalse()
        ->and($user->can('journals.create'))->toBeFalse();
});

test('pembimbing industri reviews as industry and never as teacher', function (): void {
    $user = userWithRole(RoleName::PembimbingIndustri);

    expect($user->can('journals.reviewAsIndustry'))->toBeTrue()
        ->and($user->can('assessments.submitAsIndustry'))->toBeTrue()
        ->and($user->can('journals.reviewAsTeacher'))->toBeFalse()
        ->and($user->can('monitorings.create'))->toBeFalse();
});

test('admin pkl manages pkl data but not users or roles', function (): void {
    $user = userWithRole(RoleName::AdminPkl);

    expect($user->can('placements.create'))->toBeTrue()
        ->and($user->can('students.create'))->toBeTrue()
        ->and($user->can('final-scores.publish'))->toBeTrue()
        ->and($user->can('users.create'))->toBeFalse()
        ->and($user->can('users.delete'))->toBeFalse()
        ->and($user->can('roles.viewAny'))->toBeFalse()
        ->and($user->can('settings.update'))->toBeFalse();
});

test('role helpers report the assigned role', function (): void {
    expect(userWithRole(RoleName::SuperAdmin)->isSuperAdmin())->toBeTrue()
        ->and(userWithRole(RoleName::AdminPkl)->isAdminPkl())->toBeTrue()
        ->and(userWithRole(RoleName::GuruPembimbing)->isGuruPembimbing())->toBeTrue()
        ->and(userWithRole(RoleName::PembimbingIndustri)->isPembimbingIndustri())->toBeTrue()
        ->and(userWithRole(RoleName::Siswa)->isSiswa())->toBeTrue();

    $siswa = userWithRole(RoleName::Siswa);

    expect($siswa->isSuperAdmin())->toBeFalse()
        ->and($siswa->roleName())->toBe(RoleName::Siswa);
});
