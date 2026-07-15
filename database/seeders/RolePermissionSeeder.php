<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Permission;
use App\Models\Role;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Seed roles and permissions.
     *
     * Idempotent by design: it uses `firstOrCreate` + `syncPermissions`, so it is
     * safe to re-run on every deploy as the catalogue grows.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionCatalog::all() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // DatabaseSeeder runs with `WithoutModelEvents`, which suppresses the
        // `saved` hook Spatie normally uses to invalidate its permission cache.
        // Without this flush, the role sync below reads the empty collection
        // that was cached by the first lookup and fails with PermissionDoesNotExist.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (RoleName::cases() as $roleName) {
            $role = Role::findOrCreate($roleName->value, 'web');

            $role->syncPermissions(PermissionCatalog::forRole($roleName));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
