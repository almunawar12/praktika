<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Activity;
use App\Models\User;

/**
 * The audit trail is append-only. There is deliberately no `create`, `update` or
 * `delete` ability: entries are written by the activity logger, never by a request,
 * and a log that can be edited is not an audit trail.
 */
class ActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('activity-log.viewAny');
    }

    public function view(User $user, Activity $activity): bool
    {
        return $user->can('activity-log.view');
    }
}
