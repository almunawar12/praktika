<?php

declare(strict_types=1);

use App\Http\Controllers\System\ActivityController;
use App\Http\Controllers\System\ImpersonationController;
use App\Http\Controllers\System\RoleController;
use App\Http\Controllers\System\SettingController;
use App\Http\Controllers\System\UserController;
use App\Http\Controllers\System\UserModerationController;
use Illuminate\Support\Facades\Route;

/*
| System administration. Almost all of it is Super Admin territory; Admin PKL is
| let into the audit log and the (read-only) settings. Every route carries the
| coarse `can:` gate for its area, and each controller action re-checks the row.
*/

Route::middleware('auth')->prefix('system')->name('system.')->group(function (): void {
    // Leaving an impersonation is open to whoever is currently impersonated —
    // it is the only account they hold — so it sits outside the permission gates.
    Route::delete('impersonation', [ImpersonationController::class, 'destroy'])
        ->name('impersonation.destroy');

    Route::middleware('can:users.viewAny')->group(function (): void {
        Route::resource('users', UserController::class)->except(['show', 'create']);

        Route::put('users/{user}/status', [UserModerationController::class, 'toggleStatus'])
            ->name('users.status');

        Route::put('users/{user}/reset-password', [UserModerationController::class, 'resetPassword'])
            ->name('users.reset-password');

        Route::post('users/{user}/impersonate', [ImpersonationController::class, 'store'])
            ->middleware('can:users.impersonate')
            ->name('users.impersonate');
    });

    Route::middleware('can:roles.viewAny')->group(function (): void {
        Route::resource('roles', RoleController::class)->only(['index', 'store', 'edit', 'update', 'destroy']);
    });

    Route::middleware('can:settings.view')->group(function (): void {
        Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
    });

    Route::middleware('can:activity-log.viewAny')->group(function (): void {
        Route::get('activity', [ActivityController::class, 'index'])->name('activity.index');
    });
});
