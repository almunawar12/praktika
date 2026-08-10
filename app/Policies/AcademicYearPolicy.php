<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AcademicYear;
use App\Models\User;

class AcademicYearPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('academic-years.viewAny');
    }

    public function view(User $user, AcademicYear $academicYear): bool
    {
        return $user->can('academic-years.view');
    }

    public function create(User $user): bool
    {
        return $user->can('academic-years.create');
    }

    public function update(User $user, AcademicYear $academicYear): bool
    {
        return $user->can('academic-years.update');
    }

    public function delete(User $user, AcademicYear $academicYear): bool
    {
        return $user->can('academic-years.delete');
    }

    public function activate(User $user, AcademicYear $academicYear): bool
    {
        return $user->can('academic-years.activate');
    }
}
