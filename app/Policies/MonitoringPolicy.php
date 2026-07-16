<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Monitoring;
use App\Models\Placement;
use App\Models\User;

class MonitoringPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('monitorings.viewAny');
    }

    public function view(User $user, Monitoring $monitoring): bool
    {
        return $user->can('monitorings.view') && $this->relatedTo($user, $monitoring->placement);
    }

    public function create(User $user): bool
    {
        return $user->can('monitorings.create');
    }

    /**
     * Whether this teacher may file a visit against this particular placement —
     * checked in the controller, since `create` has no model to inspect.
     */
    public function recordFor(User $user, Placement $placement): bool
    {
        return $user->can('monitorings.create')
            && $user->teacher !== null
            && $placement->teacher_id === $user->teacher->id;
    }

    /**
     * Only the teacher who filed it. A visit report is a first-hand account: another
     * teacher editing it would be putting words in someone else's mouth.
     */
    public function update(User $user, Monitoring $monitoring): bool
    {
        return $user->can('monitorings.update') && $this->isAuthor($user, $monitoring);
    }

    public function delete(User $user, Monitoring $monitoring): bool
    {
        return $user->can('monitorings.delete') && $this->isAuthor($user, $monitoring);
    }

    private function isAuthor(User $user, Monitoring $monitoring): bool
    {
        return $user->teacher !== null && $monitoring->teacher_id === $user->teacher->id;
    }

    /**
     * Mirrors Placement::scopeVisibleTo (ADD § 3.2).
     *
     * Note Pembimbing Industri is absent by design: PermissionCatalog gives the role
     * no monitoring permission at all. A visit report is the school's internal
     * assessment of how the company is hosting the student — not the company's to read.
     */
    private function relatedTo(User $user, Placement $placement): bool
    {
        if ($user->hasAnyRole([RoleName::SuperAdmin->value, RoleName::AdminPkl->value])) {
            return true;
        }

        if ($user->hasRole(RoleName::GuruPembimbing->value)) {
            return $user->teacher !== null && $placement->teacher_id === $user->teacher->id;
        }

        if ($user->hasRole(RoleName::Siswa->value)) {
            return $user->student !== null && $placement->student_id === $user->student->id;
        }

        return false;
    }
}
