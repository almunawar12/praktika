<?php

declare(strict_types=1);

use App\Enums\ReportType;
use App\Enums\RoleName;
use Illuminate\Support\Facades\Gate;

test('an admin may reach the reports area and every type', function (): void {
    $admin = userWithRole(RoleName::AdminPkl);

    expect(Gate::forUser($admin)->allows('viewAny', ReportType::class))->toBeTrue();

    foreach (ReportType::cases() as $type) {
        expect(Gate::forUser($admin)->allows('view', $type))->toBeTrue();
    }
});

test('a student may not reach the reports area at all', function (): void {
    $student = userWithRole(RoleName::Siswa);

    expect(Gate::forUser($student)->allows('viewAny', ReportType::class))->toBeFalse()
        ->and(Gate::forUser($student)->allows('view', ReportType::Attendance))->toBeFalse();
});

test('a company supervisor reaches the area but only the types they hold', function (): void {
    $supervisor = userWithRole(RoleName::PembimbingIndustri);

    expect(Gate::forUser($supervisor)->allows('viewAny', ReportType::class))->toBeTrue()
        ->and(Gate::forUser($supervisor)->allows('view', ReportType::Attendance))->toBeTrue()
        ->and(Gate::forUser($supervisor)->allows('view', ReportType::Assessment))->toBeFalse();
});
