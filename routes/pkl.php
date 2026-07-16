<?php

declare(strict_types=1);

use App\Http\Controllers\Attendance\AttendanceController;
use App\Http\Controllers\Attendance\AttendancePhotoController;
use App\Http\Controllers\Attendance\AttendanceVerificationController;
use App\Http\Controllers\Attendance\CheckInController;
use App\Http\Controllers\Pkl\PlacementController;
use App\Http\Controllers\Pkl\PlacementStatusController;
use Illuminate\Support\Facades\Route;

/*
| PKL transactions. `can:` middleware gates reaching the module; the policy decides
| each row, since every role but Admin sees only its own slice (ADD § 3.2).
*/

Route::middleware('auth')->prefix('pkl')->name('pkl.')->group(function (): void {
    Route::resource('placements', PlacementController::class)
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
});
