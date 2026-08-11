<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ReportType;
use App\Models\User;

/**
 * Reports are read, not owned.
 *
 * There is no stored artefact to guard any more, so authorization is only about
 * which kinds of recap a user may open. What data appears inside one is decided
 * separately by Placement::visibleTo().
 */
class ReportPolicy
{
    /**
     * The reports area is reachable by anyone holding at least one report
     * permission. A student, who holds none, never sees it.
     */
    public function viewAny(User $user): bool
    {
        foreach (ReportType::cases() as $type) {
            if ($user->can($type->permission())) {
                return true;
            }
        }

        return false;
    }

    public function view(User $user, ReportType $type): bool
    {
        return $user->can($type->permission());
    }
}
