<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\FinalScore;
use App\Models\Placement;
use App\Models\User;

class FinalScorePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('final-scores.view');
    }

    public function view(User $user, FinalScore $score): bool
    {
        if (! $user->can('final-scores.view') || ! $this->relatedTo($user, $score->placement)) {
            return false;
        }

        // A student sees a result only once the school has released it (ADD § 9).
        // Everyone else may watch it take shape.
        if ($user->hasRole(RoleName::Siswa->value)) {
            return $score->isPublished();
        }

        return true;
    }

    /**
     * Recalculating a published score would rewrite a result already handed out;
     * ScoreCalculatorService refuses it too.
     */
    public function calculate(User $user, Placement $placement): bool
    {
        return $user->can('final-scores.calculate');
    }

    public function publish(User $user, FinalScore $score): bool
    {
        return $user->can('final-scores.publish') && ! $score->isPublished();
    }

    public function unpublish(User $user, FinalScore $score): bool
    {
        return $user->can('final-scores.unpublish') && $score->isPublished();
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
