<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RoleName;
use App\Models\Role;
use Illuminate\Support\Facades\DB;

/**
 * Custom roles beyond the five built-ins. The built-ins are defined by the
 * RoleName enum and PermissionCatalog and are read-only here (RolePolicy blocks
 * renaming or deleting them); this service manages the extras a school might add.
 */
class RoleService
{
    /**
     * Every role with the counts the index needs, built-ins first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function overview(): array
    {
        return Role::query()
            ->withCount(['permissions', 'users'])
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'label' => $this->label($role),
                'is_built_in' => RoleName::tryFrom($role->name) !== null,
                'permissions_count' => $role->permissions_count,
                'users_count' => $role->users_count,
            ])
            ->sortByDesc('is_built_in')
            ->values()
            ->all();
    }

    public function create(string $name): Role
    {
        return Role::query()->create(['name' => $name, 'guard_name' => 'web']);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    public function syncPermissions(Role $role, array $permissions): void
    {
        DB::transaction(fn () => $role->syncPermissions($permissions));
    }

    public function delete(Role $role): void
    {
        $role->delete();
    }

    public function label(Role $role): string
    {
        return RoleName::tryFrom($role->name)?->label() ?? $role->name;
    }
}
