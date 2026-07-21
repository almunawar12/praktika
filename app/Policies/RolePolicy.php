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
        // The Super Admin role is the last line of control — editing its permissions
        // could strip away the very rights that keep an admin in charge, so it stays
        // locked. Other built-ins are only ever re-permissioned here (never renamed —
        // the update action syncs permissions only), so their access is safe to edit.
        if ($role->name === RoleName::SuperAdmin->value) {
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
