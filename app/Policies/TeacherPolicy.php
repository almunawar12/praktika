<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Teacher;
use App\Models\User;

class TeacherPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('teachers.viewAny');
    }

    public function view(User $user, Teacher $teacher): bool
    {
        // A teacher may always read their own profile, even without teachers.view.
        if ($user->teacher?->is($teacher)) {
            return true;
        }

        return $user->can('teachers.view');
    }

    public function create(User $user): bool
    {
        return $user->can('teachers.create');
    }

    public function update(User $user, Teacher $teacher): bool
    {
        return $user->can('teachers.update');
    }

    public function delete(User $user, Teacher $teacher): bool
    {
        // Deleting your own teacher profile would deactivate the account you are
        // acting from — the same trap UserPolicy::delete guards against.
        if ($user->teacher?->is($teacher)) {
            return false;
        }

        return $user->can('teachers.delete');
    }
}
