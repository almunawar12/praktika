<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ReviewStatus;
use App\Enums\RoleName;
use App\Models\Journal;
use App\Models\User;

class JournalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('journals.viewAny');
    }

    /**
     * Permission answers "may you use this feature"; this answers "may you see this
     * row". Without the second, any guru could read every school's journals.
     */
    public function view(User $user, Journal $journal): bool
    {
        return $user->can('journals.view') && $this->relatedTo($user, $journal);
    }

    public function create(User $user): bool
    {
        return $user->can('journals.create');
    }

    public function update(User $user, Journal $journal): bool
    {
        return $user->can('journals.update')
            && $this->isOwner($user, $journal)
            && $journal->isEditable();
    }

    public function delete(User $user, Journal $journal): bool
    {
        return $user->can('journals.delete')
            && $this->isOwner($user, $journal)
            && $journal->isEditable();
    }

    public function reviewAsIndustry(User $user, Journal $journal): bool
    {
        return $user->can('journals.reviewAsIndustry')
            && $this->relatedTo($user, $journal)
            && $journal->industry_status === ReviewStatus::Pending;
    }

    /**
     * The teacher's turn comes only after industry approval — the PRD § 5 order,
     * enforced here as well as by a CHECK constraint.
     */
    public function reviewAsTeacher(User $user, Journal $journal): bool
    {
        return $user->can('journals.reviewAsTeacher')
            && $this->relatedTo($user, $journal)
            && $journal->awaitingTeacher();
    }

    private function isOwner(User $user, Journal $journal): bool
    {
        return $user->student !== null
            && $journal->placement->student_id === $user->student->id;
    }

    /**
     * Mirrors Placement::scopeVisibleTo (ADD § 3.2).
     */
    private function relatedTo(User $user, Journal $journal): bool
    {
        if ($user->hasAnyRole([RoleName::SuperAdmin->value, RoleName::AdminPkl->value])) {
            return true;
        }

        $placement = $journal->placement;

        if ($user->hasRole(RoleName::GuruPembimbing->value)) {
            return $user->teacher !== null && $placement->teacher_id === $user->teacher->id;
        }

        if ($user->hasRole(RoleName::Siswa->value)) {
            return $user->student !== null && $placement->student_id === $user->student->id;
        }

        if ($user->hasRole(RoleName::PembimbingIndustri->value)) {
            return $user->companySupervisor !== null
                && $placement->company_id === $user->companySupervisor->company_id;
        }

        return false;
    }
}
