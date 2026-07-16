<?php

declare(strict_types=1);

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
