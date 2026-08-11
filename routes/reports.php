<?php

declare(strict_types=1);

use App\Http\Controllers\Report\ReportController;
use Illuminate\Support\Facades\Route;

/*
| Reports. Read-only: a report is a query over data the user may already see,
| so each type is one page whose filters live in the URL. Authorization is per
| type (ReportPolicy); what appears inside is scoped by Placement::visibleTo().
*/

Route::middleware('auth')->name('reports.')->prefix('reports')->group(function (): void {
    Route::get('/', [ReportController::class, 'index'])->name('index');
    Route::get('{type}', [ReportController::class, 'show'])->name('show');
    Route::get('{type}/download', [ReportController::class, 'download'])->name('download');
});
