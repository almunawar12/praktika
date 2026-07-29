<?php

declare(strict_types=1);

use App\Jobs\CompleteExpiredPlacementsJob;
use App\Jobs\MarkAbsentStudentsJob;
use Illuminate\Support\Facades\Schedule;

/*
| Requires a scheduler to be running in each environment:
|   * * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1
| Without it, students who never open the app are never marked absent, and
| expired placements never move to `completed`.
*/

// After working hours, so a late check-in is not overwritten by an absence.
Schedule::job(new MarkAbsentStudentsJob)
    ->dailyAt('18:00')
    ->weekdays()
    ->name('mark-absent-students')
    ->withoutOverlapping();

// Before the day starts, so anything relying on `completed` status (reports,
// final scoring) sees it as soon as the term is actually over.
Schedule::job(new CompleteExpiredPlacementsJob)
    ->dailyAt('00:30')
    ->name('complete-expired-placements')
    ->withoutOverlapping();
