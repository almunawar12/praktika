<?php

declare(strict_types=1);

use App\DataTransferObjects\ReportFilters;
use App\Enums\ReportType;
use App\Models\AcademicYear;
use Illuminate\Http\Request;

function filterRequest(array $query = []): Request
{
    return Request::create('/reports/attendance', 'GET', $query);
}

test('it reads every filter from the query string', function (): void {
    $filters = ReportFilters::fromRequest(filterRequest([
        'date_from' => '2026-01-01',
        'date_to' => '2026-03-31',
        'department_id' => '11111111-1111-1111-1111-111111111111',
        'company_id' => '22222222-2222-2222-2222-222222222222',
        'school_class_id' => '33333333-3333-3333-3333-333333333333',
        'teacher_id' => '44444444-4444-4444-4444-444444444444',
        'search' => 'budi',
        'per_page' => '25',
    ]), ReportType::Attendance);

    expect($filters->dateFrom)->toBe('2026-01-01')
        ->and($filters->dateTo)->toBe('2026-03-31')
        ->and($filters->departmentId)->toBe('11111111-1111-1111-1111-111111111111')
        ->and($filters->companyId)->toBe('22222222-2222-2222-2222-222222222222')
        ->and($filters->schoolClassId)->toBe('33333333-3333-3333-3333-333333333333')
        ->and($filters->teacherId)->toBe('44444444-4444-4444-4444-444444444444')
        ->and($filters->search)->toBe('budi')
        ->and($filters->perPage)->toBe(25);
});

test('it treats empty strings and the "all" sentinel as no filter', function (): void {
    $filters = ReportFilters::fromRequest(filterRequest([
        'date_from' => '2026-01-01',
        'company_id' => 'all',
        'department_id' => '',
        'search' => '   ',
    ]), ReportType::Attendance);

    expect($filters->companyId)->toBeNull()
        ->and($filters->departmentId)->toBeNull()
        ->and($filters->search)->toBeNull();
});

test('it falls back to the active academic year when no period is given', function (): void {
    AcademicYear::factory()->create([
        'start_date' => '2026-07-01',
        'end_date' => '2027-06-30',
        'is_active' => true,
    ]);

    $filters = ReportFilters::fromRequest(filterRequest(), ReportType::Attendance);

    expect($filters->dateFrom)->toBe('2026-07-01')
        ->and($filters->dateTo)->toBe('2027-06-30');
});

test('an explicit period wins over the active academic year', function (): void {
    AcademicYear::factory()->create([
        'start_date' => '2026-07-01',
        'end_date' => '2027-06-30',
        'is_active' => true,
    ]);

    $filters = ReportFilters::fromRequest(
        filterRequest(['date_from' => '2026-08-01']),
        ReportType::Attendance,
    );

    expect($filters->dateFrom)->toBe('2026-08-01')
        ->and($filters->dateTo)->toBeNull();
});

test('without an active academic year the period stays open', function (): void {
    $filters = ReportFilters::fromRequest(filterRequest(), ReportType::Attendance);

    expect($filters->dateFrom)->toBeNull()
        ->and($filters->dateTo)->toBeNull()
        ->and($filters->describeScope())->toBe('Seluruh periode');
});

test('student_id is only read for the student summary report', function (): void {
    $query = ['student_id' => '55555555-5555-5555-5555-555555555555'];

    expect(ReportFilters::fromRequest(filterRequest($query), ReportType::Attendance)->studentId)->toBeNull()
        ->and(ReportFilters::fromRequest(filterRequest($query), ReportType::StudentSummary)->studentId)
        ->toBe('55555555-5555-5555-5555-555555555555');
});

test('per page is clamped and defaults to 15', function (): void {
    expect(ReportFilters::fromRequest(filterRequest(), ReportType::Attendance)->perPage)->toBe(15)
        ->and(ReportFilters::fromRequest(filterRequest(['per_page' => '1']), ReportType::Attendance)->perPage)->toBe(5)
        ->and(ReportFilters::fromRequest(filterRequest(['per_page' => '5000']), ReportType::Attendance)->perPage)->toBe(100);
});

test('describeScope reads back the period', function (): void {
    $both = ReportFilters::fromRequest(
        filterRequest(['date_from' => '2026-01-01', 'date_to' => '2026-03-31']),
        ReportType::Attendance,
    );

    expect($both->describeScope())->toBe('Periode 01/01/2026 – 31/03/2026');
});
