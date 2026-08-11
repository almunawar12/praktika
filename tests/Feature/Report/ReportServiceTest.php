<?php

declare(strict_types=1);

use App\DataTransferObjects\ReportFilters;
use App\Enums\AttendanceStatus;
use App\Enums\ReportType;
use App\Enums\RoleName;
use App\Enums\VerificationStatus;
use App\Models\Attendance;
use App\Models\Company;
use App\Models\CompanySupervisor;
use App\Models\Placement;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\ReportService;

function reportFilters(array $overrides = []): ReportFilters
{
    return new ReportFilters(
        dateFrom: $overrides['dateFrom'] ?? null,
        dateTo: $overrides['dateTo'] ?? null,
        departmentId: $overrides['departmentId'] ?? null,
        companyId: $overrides['companyId'] ?? null,
        schoolClassId: $overrides['schoolClassId'] ?? null,
        teacherId: $overrides['teacherId'] ?? null,
        search: $overrides['search'] ?? null,
        studentId: $overrides['studentId'] ?? null,
        perPage: $overrides['perPage'] ?? 15,
    );
}

function placementFor(string $name, string $nis, Company $company, Teacher $teacher, ?SchoolClass $class = null): Placement
{
    $student = Student::factory()->create(array_filter([
        'name' => $name,
        'nis' => $nis,
        'class_id' => $class?->id,
    ]));

    return Placement::factory()->active()->create([
        'student_id' => $student->id,
        'company_id' => $company->id,
        'teacher_id' => $teacher->id,
    ]);
}

beforeEach(function (): void {
    $this->service = app(ReportService::class);
    $this->admin = userWithRole(RoleName::AdminPkl);
    $this->company = Company::factory()->create(['quota' => 10]);
    $this->teacher = Teacher::factory()->create();
});

test('paginate returns rows ordered by student name with a key per placement', function (): void {
    $second = placementFor('Zulfa', '2002', $this->company, $this->teacher);
    $first = placementFor('Andi', '2001', $this->company, $this->teacher);

    $page = $this->service->paginate(ReportType::Attendance, reportFilters(), $this->admin);

    expect($page->total())->toBe(2)
        ->and($page->items()[0]['key'])->toBe($first->id)
        ->and($page->items()[0]['cells'][0])->toBe('Andi')
        ->and($page->items()[1]['key'])->toBe($second->id)
        ->and($page->items()[1]['cells'][0])->toBe('Zulfa');
});

test('each page carries its own aggregates', function (): void {
    $andi = placementFor('Andi', '2001', $this->company, $this->teacher);
    $budi = placementFor('Budi', '2002', $this->company, $this->teacher);

    Attendance::factory()->count(3)->create([
        'placement_id' => $andi->id,
        'status' => AttendanceStatus::Present,
        'verification_status' => VerificationStatus::Verified,
    ]);
    Attendance::factory()->create([
        'placement_id' => $budi->id,
        'status' => AttendanceStatus::Present,
        'verification_status' => VerificationStatus::Verified,
    ]);

    // Column index 3 is "Hadir".
    $first = $this->service->paginate(ReportType::Attendance, reportFilters(['perPage' => 1]), $this->admin, page: 1);

    expect($first->total())->toBe(2)
        ->and($first->items())->toHaveCount(1)
        ->and($first->items()[0]['cells'][0])->toBe('Andi')
        ->and($first->items()[0]['cells'][3])->toBe(3);

    $second = $this->service->paginate(ReportType::Attendance, reportFilters(['perPage' => 1]), $this->admin, page: 2);

    expect($second->items())->toHaveCount(1)
        ->and($second->items()[0]['cells'][0])->toBe('Budi')
        ->and($second->items()[0]['cells'][3])->toBe(1);
});

test('the class filter narrows rows', function (): void {
    $class = SchoolClass::factory()->create();
    placementFor('Andi', '2001', $this->company, $this->teacher, $class);
    placementFor('Budi', '2002', $this->company, $this->teacher);

    $page = $this->service->paginate(
        ReportType::Attendance,
        reportFilters(['schoolClassId' => $class->id]),
        $this->admin,
    );

    expect($page->total())->toBe(1)
        ->and($page->items()[0]['cells'][0])->toBe('Andi');
});

test('the teacher filter narrows rows', function (): void {
    $other = Teacher::factory()->create();
    placementFor('Andi', '2001', $this->company, $this->teacher);
    placementFor('Budi', '2002', $this->company, $other);

    $page = $this->service->paginate(
        ReportType::Attendance,
        reportFilters(['teacherId' => $other->id]),
        $this->admin,
    );

    expect($page->total())->toBe(1)
        ->and($page->items()[0]['cells'][0])->toBe('Budi');
});

test('search matches student name or nis', function (): void {
    placementFor('Andi Pratama', '2001', $this->company, $this->teacher);
    placementFor('Budi Santoso', '9999', $this->company, $this->teacher);

    expect($this->service->paginate(ReportType::Attendance, reportFilters(['search' => 'pratama']), $this->admin)->total())->toBe(1)
        ->and($this->service->paginate(ReportType::Attendance, reportFilters(['search' => '9999']), $this->admin)->total())->toBe(1)
        ->and($this->service->paginate(ReportType::Attendance, reportFilters(['search' => 'zzz']), $this->admin)->total())->toBe(0);
});

test('a company supervisor only sees their own company', function (): void {
    $mine = placementFor('Andi', '2001', $this->company, $this->teacher);
    $otherCompany = Company::factory()->create(['quota' => 10]);
    placementFor('Budi', '2002', $otherCompany, $this->teacher);

    $supervisorUser = userWithRole(RoleName::PembimbingIndustri);
    CompanySupervisor::factory()->create([
        'user_id' => $supervisorUser->id,
        'company_id' => $this->company->id,
    ]);

    $page = $this->service->paginate(ReportType::Attendance, reportFilters(), $supervisorUser->fresh());

    expect($page->total())->toBe(1)
        ->and($page->items()[0]['key'])->toBe($mine->id);
});

test('dataset returns flat rows for every placement in scope', function (): void {
    placementFor('Andi', '2001', $this->company, $this->teacher);
    placementFor('Budi', '2002', $this->company, $this->teacher);

    $dataset = $this->service->dataset(ReportType::Attendance, reportFilters(['perPage' => 1]), $this->admin);

    expect($dataset->rows)->toHaveCount(2)
        ->and($dataset->rows[0][0])->toBe('Andi')
        ->and($dataset->columns)->toBe(ReportType::Attendance->columns())
        ->and($dataset->requestedBy)->toBe($this->admin->name);
});

test('studentSummary returns null when no student is chosen or the student is out of scope', function (): void {
    $placement = placementFor('Andi', '2001', $this->company, $this->teacher);

    expect($this->service->studentSummary(reportFilters(), $this->admin))->toBeNull();

    $outsider = Student::factory()->create();

    expect($this->service->studentSummary(reportFilters(['studentId' => $outsider->id]), $this->admin))->toBeNull()
        ->and($this->service->studentSummary(reportFilters(['studentId' => $placement->student_id]), $this->admin))
        ->not->toBeNull();
});
