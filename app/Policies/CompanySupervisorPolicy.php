<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CompanySupervisor;
use App\Models\User;

class CompanySupervisorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('company-supervisors.viewAny');
    }

    public function view(User $user, CompanySupervisor $supervisor): bool
    {
        // A supervisor may always read their own profile, even without the
        // viewAny permission (used by a future "Profil Saya" page).
        if ($user->companySupervisor?->is($supervisor)) {
            return true;
        }

        return $user->can('company-supervisors.view');
    }

    public function create(User $user): bool
    {
        return $user->can('company-supervisors.create');
    }

    public function update(User $user, CompanySupervisor $supervisor): bool
    {
        return $user->can('company-supervisors.update');
    }

    public function delete(User $user, CompanySupervisor $supervisor): bool
    {
        // Deleting your own profile would deactivate the account you are acting from.
        if ($user->companySupervisor?->is($supervisor)) {
            return false;
        }

        return $user->can('company-supervisors.delete');
    }
}
