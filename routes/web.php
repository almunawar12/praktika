<?php

declare(strict_types=1);

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', DashboardController::class)
        ->middleware('can:dashboard.view')
        ->name('dashboard');
});

require __DIR__.'/auth.php';
require __DIR__.'/master.php';
require __DIR__.'/pkl.php';
require __DIR__.'/reports.php';
