<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Enums\VerificationStatus;
use App\Models\Attendance;
use App\Models\User;

class AttendancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('attendances.viewAny');
    }

    public function view(User $user, Attendance $attendance): bool
    {
        return $user->can('attendances.view') && $this->relatedTo($user, $attendance);
    }

    /**
     * Only a student checks in, and only for themselves — the placement is resolved
     * from the authenticated user, never from the request.
     */
    public function checkIn(User $user): bool
    {
        return $user->can('attendances.checkIn');
    }

    public function checkOut(User $user): bool
    {
        return $user->can('attendances.checkOut');
    }

    public function verify(User $user, Attendance $attendance): bool
    {
        if (! $user->can('attendances.verify') || ! $this->relatedTo($user, $attendance)) {
            return false;
        }

        // Verifying your own attendance would make the check meaningless.
        if ($user->student !== null && $attendance->placement->student_id === $user->student->id) {
            return false;
        }

        // Already decided: re-deciding would quietly rewrite the record a teacher's
        // assessment rests on. Admin correction is a separate ability.
        return $attendance->verification_status === VerificationStatus::Pending;
    }

    public function update(User $user, Attendance $attendance): bool
    {
        return $user->can('attendances.update');
    }

    public function delete(User $user, Attendance $attendance): bool
    {
        return $user->can('attendances.delete');
    }

    /**
     * Mirrors Placement::scopeVisibleTo (ADD § 3.2).
     */
    private function relatedTo(User $user, Attendance $attendance): bool
    {
        if ($user->hasAnyRole([RoleName::SuperAdmin->value, RoleName::AdminPkl->value])) {
            return true;
        }

        $placement = $attendance->placement;

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
