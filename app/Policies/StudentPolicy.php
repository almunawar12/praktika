<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('students.viewAny');
    }

    public function view(User $user, Student $student): bool
    {
        // A student may always read their own profile, even without students.view.
        if ($user->student?->is($student)) {
            return true;
        }

        return $user->can('students.view');
    }

    public function create(User $user): bool
    {
        return $user->can('students.create');
    }

    public function update(User $user, Student $student): bool
    {
        return $user->can('students.update');
    }

    public function delete(User $user, Student $student): bool
    {
        if ($user->student?->is($student)) {
            return false;
        }

        return $user->can('students.delete');
    }
}
