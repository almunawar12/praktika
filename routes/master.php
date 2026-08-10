<?php

declare(strict_types=1);

use App\Http\Controllers\Master\AcademicYearController;
use App\Http\Controllers\Master\CompanyController;
use App\Http\Controllers\Master\CompanySupervisorController;
use App\Http\Controllers\Master\DepartmentController;
use App\Http\Controllers\Master\SchoolClassController;
use App\Http\Controllers\Master\StudentController;
use App\Http\Controllers\Master\TeacherController;
use Illuminate\Support\Facades\Route;

/*
| Master data. Every action is additionally authorized by its policy inside the
| controller — the `can:` middleware here only gates reaching the module at all.
*/

Route::middleware('auth')->prefix('master')->name('master.')->group(function (): void {
    Route::resource('academic-years', AcademicYearController::class)
        ->parameters(['academic-years' => 'academic_year'])
        ->except('create')
        ->middleware('can:academic-years.viewAny');

    Route::put('academic-years/{academic_year}/activate', [AcademicYearController::class, 'activate'])
        ->middleware('can:academic-years.viewAny')
        ->name('academic-years.activate');

    Route::resource('departments', DepartmentController::class)
        ->except('create')
        ->middleware('can:departments.viewAny');

    Route::resource('classes', SchoolClassController::class)
        ->parameters(['classes' => 'class'])
        ->except('create')
        ->middleware('can:school-classes.viewAny');

    Route::resource('teachers', TeacherController::class)
        ->except('create')
        ->middleware('can:teachers.viewAny');

    Route::resource('companies', CompanyController::class)
        ->except('create')
        ->middleware('can:companies.viewAny');

    Route::resource('students', StudentController::class)
        ->except('create')
        ->middleware('can:students.viewAny');

    Route::resource('supervisors', CompanySupervisorController::class)
        ->parameters(['supervisors' => 'supervisor'])
        ->except('create')
        ->middleware('can:company-supervisors.viewAny');
});
