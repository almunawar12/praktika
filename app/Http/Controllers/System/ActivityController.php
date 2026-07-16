<?php

declare(strict_types=1);

namespace App\Http\Controllers\System;

use App\DataTransferObjects\TableRequest;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only window on the audit trail. There is no create/update/delete: entries
 * are written by the activity logger, and a log a request can edit is not one.
 */
class ActivityController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Activity::class);

        $table = TableRequest::fromRequest($request, ['log_name', 'event', 'date_from', 'date_to']);

        $activities = Activity::query()
            ->with('causer')
            ->when($table->filter('log_name'), fn (Builder $q, string $log) => $q->where('log_name', $log))
            ->when($table->filter('event'), fn (Builder $q, string $event) => $q->where('event', $event))
            ->when($table->filter('date_from'), fn (Builder $q, string $d) => $q->whereDate('created_at', '>=', $d))
            ->when($table->filter('date_to'), fn (Builder $q, string $d) => $q->whereDate('created_at', '<=', $d))
            ->when($table->search, fn (Builder $q, string $search) => $q->where('description', 'ilike', "%{$search}%"))
            ->latest()
            ->paginate($table->perPage)
            ->withQueryString()
            ->through(fn (Activity $activity): array => [
                'id' => $activity->id,
                'log_name' => $activity->log_name,
                'description' => $activity->description,
                'event' => $activity->event,
                // causer is a morphTo over any model; only a User has a name to show,
                // and system entries have no causer at all.
                'causer' => $activity->causer instanceof User ? $activity->causer->name : null,
                'subject_type' => $activity->subject_type === null
                    ? null
                    : class_basename($activity->subject_type),
                'created_at' => $activity->created_at?->toIso8601String(),
            ]);

        return Inertia::render('system/activity/index', [
            'activities' => $activities,
            'table' => $table->toArray(),
            'filterOptions' => [
                'logNames' => Activity::query()->distinct()->orderBy('log_name')->pluck('log_name')->filter()->values(),
                'events' => Activity::query()->whereNotNull('event')->distinct()->orderBy('event')->pluck('event')->values(),
            ],
        ]);
    }
}
