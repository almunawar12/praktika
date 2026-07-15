<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('roles.viewAny');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->can('roles.view');
    }

    public function create(User $user): bool
    {
        return $user->can('roles.create');
    }

    public function update(User $user, Role $role): bool
    {
        // The five built-in roles are referenced by name throughout the codebase
        // (RoleName enum, PermissionCatalog, sidebar). Renaming one would silently
        // break every one of those lookups.
        if ($this->isBuiltIn($role)) {
            return false;
        }

        return $user->can('roles.update');
    }

    public function delete(User $user, Role $role): bool
    {
        if ($this->isBuiltIn($role)) {
            return false;
        }

        return $user->can('roles.delete');
    }

    public function assign(User $user, Role $role): bool
    {
        return $user->can('roles.assign');
    }

    private function isBuiltIn(Role $role): bool
    {
        return RoleName::tryFrom($role->name) !== null;
    }
}
