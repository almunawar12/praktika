<?php

declare(strict_types=1);

use App\Enums\AssessmentType;
use App\Http\Controllers\Assessment\AssessmentController;
use App\Http\Controllers\Assessment\FinalScoreController;
use App\Http\Controllers\Attendance\AttendanceController;
use App\Http\Controllers\Attendance\AttendancePhotoController;
use App\Http\Controllers\Attendance\AttendanceVerificationController;
use App\Http\Controllers\Attendance\CheckInController;
use App\Http\Controllers\Journal\AttachmentController;
use App\Http\Controllers\Journal\JournalController;
use App\Http\Controllers\Journal\JournalReviewController;
use App\Http\Controllers\Monitoring\MonitoringController;
use App\Http\Controllers\Pkl\PlacementController;
use App\Http\Controllers\Pkl\PlacementStatusController;
use Illuminate\Support\Facades\Route;

/*
| PKL transactions. `can:` middleware gates reaching the module; the policy decides
| each row, since every role but Admin sees only its own slice (ADD § 3.2).
*/

Route::middleware('auth')->prefix('pkl')->name('pkl.')->group(function (): void {
    // Distinct path so it is not swallowed by the resource's placements/{placement}.
    Route::get('placement-options', [PlacementController::class, 'options'])
        ->middleware('can:placements.viewAny')
        ->name('placements.options');

    Route::resource('placements', PlacementController::class)
        ->except('create')
        ->middleware('can:placements.viewAny');

    Route::middleware('can:placements.viewAny')->group(function (): void {
        Route::put('placements/{placement}/activate', [PlacementStatusController::class, 'activate'])
            ->name('placements.activate');

        Route::put('placements/{placement}/complete', [PlacementStatusController::class, 'complete'])
            ->name('placements.complete');

        Route::put('placements/{placement}/cancel', [PlacementStatusController::class, 'cancel'])
            ->name('placements.cancel');
    });
});

Route::middleware('auth')->group(function (): void {
    // The student's own actions. Declared before the `{attendance}` routes so
    // "check-in" is never mistaken for an id.
    Route::middleware('can:attendances.checkIn')->group(function (): void {
        Route::get('attendances/check-in', [CheckInController::class, 'create'])
            ->name('attendances.check-in.create');

        Route::post('attendances/check-in', [CheckInController::class, 'store'])
            ->name('attendances.check-in.store');

        Route::post('attendances/check-out', [CheckInController::class, 'checkOut'])
            ->name('attendances.check-out.store');

        Route::post('attendances/absence', [CheckInController::class, 'storeAbsence'])
            ->name('attendances.absence.store');
    });

    Route::middleware('can:attendances.viewAny')->group(function (): void {
        Route::get('attendances', [AttendanceController::class, 'index'])
            ->name('attendances.index');

        // Photos live on the private disk and are authorized per attendance.
        Route::get('attendances/photo', AttendancePhotoController::class)
            ->name('attendances.photo');

        Route::get('attendances/{attendance}', [AttendanceController::class, 'show'])
            ->name('attendances.show');

        Route::put('attendances/{attendance}/verify', AttendanceVerificationController::class)
            ->name('attendances.verify');
    });

    Route::middleware('can:journals.viewAny')->group(function (): void {
        Route::resource('journals', JournalController::class);

        // Separate endpoints per stage: each carries its own permission and produces
        // its own audit entry (PRD § 5 order).
        Route::put('journals/{journal}/review/industry', [JournalReviewController::class, 'industry'])
            ->name('journals.review.industry');

        Route::put('journals/{journal}/review/teacher', [JournalReviewController::class, 'teacher'])
            ->name('journals.review.teacher');

        // Authorized through the parent record, not by path.
        Route::get('attachments/{attachment}', [AttachmentController::class, 'show'])
            ->name('attachments.show');

        Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy'])
            ->name('attachments.destroy');
    });

    Route::middleware('can:monitorings.viewAny')->group(function (): void {
        Route::resource('monitorings', MonitoringController::class);
    });

    Route::middleware('can:assessments.viewAny')->group(function (): void {
        Route::get('assessments', [AssessmentController::class, 'index'])
            ->name('assessments.index');

        // One form, two sides: which one is the route's business, and the policy
        // decides whether this user owns that side.
        Route::get('assessments/{placement}/{type}', [AssessmentController::class, 'edit'])
            ->whereIn('type', AssessmentType::values())
            ->name('assessments.edit');

        Route::put('assessments/{placement}/{type}', [AssessmentController::class, 'store'])
            ->whereIn('type', AssessmentType::values())
            ->name('assessments.store');

        Route::put('assessments/{assessment}/unlock', [AssessmentController::class, 'unlock'])
            ->name('assessments.unlock');
    });

    Route::middleware('can:final-scores.view')->group(function (): void {
        Route::get('final-scores', [FinalScoreController::class, 'index'])
            ->name('final-scores.index');

        Route::get('final-scores/{finalScore}', [FinalScoreController::class, 'show'])
            ->name('final-scores.show');

        Route::post('final-scores/{placement}/calculate', [FinalScoreController::class, 'calculate'])
            ->name('final-scores.calculate');

        Route::post('final-scores/{finalScore}/publish', [FinalScoreController::class, 'publish'])
            ->name('final-scores.publish');

        Route::delete('final-scores/{finalScore}/publish', [FinalScoreController::class, 'unpublish'])
            ->name('final-scores.unpublish');
    });
});
