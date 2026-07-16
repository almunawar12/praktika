<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\AssessmentType;
use App\Enums\RoleName;
use App\Models\Assessment;
use App\Models\Placement;
use App\Models\User;
use App\Services\AssessmentService;

class AssessmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('assessments.viewAny');
    }

    /**
     * A student never sees raw assessments — only the final score, and only once it
     * is published (ADD § 9). PermissionCatalog gives siswa no assessment permission
     * at all, so this holds without a role check here.
     */
    public function view(User $user, Assessment $assessment): bool
    {
        return $user->can('assessments.view') && $this->relatedTo($user, $assessment->placement);
    }

    /**
     * Whether this user may fill in this side for this placement — checked in the
     * controller, since there is no assessment yet to inspect.
     */
    public function submitFor(User $user, Placement $placement, AssessmentType $type): bool
    {
        if (! $user->can($type->permission())) {
            return false;
        }

        if (! in_array($placement->status, AssessmentService::ASSESSABLE, true)) {
            return false;
        }

        return $this->relatedTo($user, $placement);
    }

    /**
     * Reopening a submitted assessment changes the evidence a final score rests on,
     * so it is an admin decision, not the assessor's second thought.
     */
    public function unlock(User $user, Assessment $assessment): bool
    {
        return $user->hasAnyRole([RoleName::SuperAdmin->value, RoleName::AdminPkl->value])
            && $assessment->isSubmitted();
    }

    /**
     * Mirrors Placement::scopeVisibleTo (ADD § 3.2).
     */
    private function relatedTo(User $user, Placement $placement): bool
    {
        if ($user->hasAnyRole([RoleName::SuperAdmin->value, RoleName::AdminPkl->value])) {
            return true;
        }

        if ($user->hasRole(RoleName::GuruPembimbing->value)) {
            return $user->teacher !== null && $placement->teacher_id === $user->teacher->id;
        }

        if ($user->hasRole(RoleName::PembimbingIndustri->value)) {
            return $user->companySupervisor !== null
                && $placement->company_id === $user->companySupervisor->company_id;
        }

        return false;
    }
}
