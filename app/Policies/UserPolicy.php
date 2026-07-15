<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\User;

/**
 * Every role — Super Admin included — is evaluated here; there is no `Gate::before()`
 * bypass (see AppServiceProvider). That is what makes the `delete` and `toggleStatus`
 * guards below binding on Super Admins too, which is the whole point of having them.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.viewAny');
    }

    public function view(User $user, User $model): bool
    {
        return $user->can('users.view') || $user->is($model);
    }

    public function create(User $user): bool
    {
        return $user->can('users.create');
    }

    public function update(User $user, User $model): bool
    {
        return $user->can('users.update');
    }

    public function delete(User $user, User $model): bool
    {
        // Deleting yourself locks you out of the account you are acting from.
        if ($user->is($model)) {
            return false;
        }

        // Removing the last Super Admin would leave the system unadministrable.
        if ($model->hasRole(RoleName::SuperAdmin->value) && $this->isLastSuperAdmin($model)) {
            return false;
        }

        return $user->can('users.delete');
    }

    public function restore(User $user, User $model): bool
    {
        return $user->can('users.delete');
    }

    public function resetPassword(User $user, User $model): bool
    {
        return $user->can('users.resetPassword');
    }

    public function toggleStatus(User $user, User $model): bool
    {
        if ($user->is($model)) {
            return false;
        }

        if ($model->hasRole(RoleName::SuperAdmin->value) && $this->isLastSuperAdmin($model)) {
            return false;
        }

        return $user->can('users.toggleStatus');
    }

    public function impersonate(User $user, User $model): bool
    {
        if ($user->is($model)) {
            return false;
        }

        return $user->can('users.impersonate');
    }

    private function isLastSuperAdmin(User $model): bool
    {
        return User::role(RoleName::SuperAdmin->value)
            ->whereKeyNot($model->getKey())
            ->doesntExist();
    }
}
