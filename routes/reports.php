<?php

declare(strict_types=1);

use App\Http\Controllers\Report\ReportController;
use App\Models\ReportExport;
use Illuminate\Support\Facades\Route;

/*
| Reports. Reaching the area needs at least one report permission; the policy
| then decides each row, because an export — and its file — is private to the
| person who asked for it (ADD § 9).
*/

Route::middleware('auth')->name('reports.')->prefix('reports')->group(function (): void {
    Route::middleware('can:viewAny,'.ReportExport::class)->group(function (): void {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::post('/', [ReportController::class, 'store'])->name('store');

        // Authorized per row (owner only), not by a blanket permission.
        Route::get('{report}/download', [ReportController::class, 'download'])->name('download');
        Route::delete('{report}', [ReportController::class, 'destroy'])->name('destroy');
    });
});
