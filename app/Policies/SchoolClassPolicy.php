<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SchoolClass;
use App\Models\User;

class SchoolClassPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('school-classes.viewAny');
    }

    public function view(User $user, SchoolClass $class): bool
    {
        return $user->can('school-classes.view');
    }

    public function create(User $user): bool
    {
        return $user->can('school-classes.create');
    }

    public function update(User $user, SchoolClass $class): bool
    {
        return $user->can('school-classes.update');
    }

    public function delete(User $user, SchoolClass $class): bool
    {
        return $user->can('school-classes.delete');
    }
}
