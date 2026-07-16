<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('companies.viewAny');
    }

    public function view(User $user, Company $company): bool
    {
        if (! $user->can('companies.view')) {
            return false;
        }

        // Permission is not enough: quota and PIC contact details are commercial
        // information, so a student sees only their PKL company and a supervisor
        // only their own (ADD § 9).
        return Company::query()->visibleTo($user)->whereKey($company->getKey())->exists();
    }

    public function create(User $user): bool
    {
        return $user->can('companies.create');
    }

    public function update(User $user, Company $company): bool
    {
        return $user->can('companies.update');
    }

    public function delete(User $user, Company $company): bool
    {
        return $user->can('companies.delete');
    }
}
