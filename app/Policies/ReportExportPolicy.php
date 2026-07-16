<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ReportType;
use App\Models\ReportExport;
use App\Models\User;

class ReportExportPolicy
{
    /**
     * The reports area is reachable by anyone who may generate at least one kind
     * of report. A student, who may generate none, never sees it.
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

    /**
     * An export — and its file — belongs to whoever asked for it. Ownership is the
     * whole rule: even an admin cannot open someone else's extract (ADD § 9).
     */
    public function view(User $user, ReportExport $export): bool
    {
        return $export->requested_by === $user->id;
    }

    public function download(User $user, ReportExport $export): bool
    {
        return $export->requested_by === $user->id;
    }

    public function delete(User $user, ReportExport $export): bool
    {
        return $export->requested_by === $user->id;
    }

    /**
     * Whether the user may generate this particular kind of report.
     */
    public function generate(User $user, ReportType $type): bool
    {
        return $user->can($type->permission());
    }
}
