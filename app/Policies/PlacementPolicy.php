<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PlacementStatus;
use App\Enums\RoleName;
use App\Models\Placement;
use App\Models\User;

class PlacementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('placements.viewAny');
    }

    /**
     * Permission answers "may you use this feature"; this answers "may you see this
     * row". Both are required — without the second, any guru could read every
     * school's placements.
     */
    public function view(User $user, Placement $placement): bool
    {
        return $user->can('placements.view') && $this->relatedTo($user, $placement);
    }

    public function create(User $user): bool
    {
        return $user->can('placements.create');
    }

    public function update(User $user, Placement $placement): bool
    {
        return $user->can('placements.update') && $placement->status->isEditable();
    }

    public function delete(User $user, Placement $placement): bool
    {
        // Only a draft is disposable; anything further has history behind it.
        return $user->can('placements.delete') && $placement->status === PlacementStatus::Draft;
    }

    public function activate(User $user, Placement $placement): bool
    {
        return $user->can('placements.activate')
            && $placement->status->canTransitionTo(PlacementStatus::Active);
    }

    public function complete(User $user, Placement $placement): bool
    {
        return $user->can('placements.complete')
            && $placement->status->canTransitionTo(PlacementStatus::Completed);
    }

    public function cancel(User $user, Placement $placement): bool
    {
        return $user->can('placements.cancel')
            && $placement->status->canTransitionTo(PlacementStatus::Cancelled);
    }

    /**
     * The authorization path from ADD § 3.2, mirroring Placement::scopeVisibleTo.
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

        // Pembimbing Industri has no company link until the company_supervisors
        // module exists; deny rather than leak.
        return false;
    }
}
