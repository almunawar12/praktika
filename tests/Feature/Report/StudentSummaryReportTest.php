<?php

declare(strict_types=1);

use App\DataTransferObjects\ReportFilters;
use App\Enums\AttendanceStatus;
use App\Enums\ReviewStatus;
use App\Enums\RoleName;
use App\Enums\VerificationStatus;
use App\Models\Attendance;
use App\Models\Company;
use App\Models\FinalScore;
use App\Models\Journal;
use App\Models\Monitoring;
use App\Models\Placement;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\ReportService;

function summaryFilters(?string $studentId, ?string $from = null, ?string $to = null): ReportFilters
{
    return new ReportFilters(
        dateFrom: $from,
        dateTo: $to,
        departmentId: null,
        companyId: null,
        schoolClassId: null,
        teacherId: null,
        search: null,
        studentId: $studentId,
        perPage: 15,
    );
}

beforeEach(function (): void {
    $this->company = Company::factory()->create(['quota' => 10]);
    $this->teacher = Teacher::factory()->create();
    $this->student = Student::factory()->create();

    $this->placement = Placement::factory()->active()->create([
        'student_id' => $this->student->id,
        'company_id' => $this->company->id,
        'teacher_id' => $this->teacher->id,
    ]);

    $this->admin = userWithRole(RoleName::AdminPkl);
});

test('the service assembles attendance, journal, score, and monitoring for one placement', function (): void {
    Attendance::factory()->onDate('2026-01-05')->create([
        'placement_id' => $this->placement->id,
        'status' => AttendanceStatus::Present,
        'verification_status' => VerificationStatus::Verified,
    ]);
    Attendance::factory()->onDate('2026-01-06')->create([
        'placement_id' => $this->placement->id,
        'status' => AttendanceStatus::Sick,
        'verification_status' => VerificationStatus::Verified,
    ]);
    Journal::factory()->create([
        'placement_id' => $this->placement->id,
        'date' => '2026-01-05',
        'status' => ReviewStatus::Approved,
    ]);
    Monitoring::factory()->create([
        'placement_id' => $this->placement->id,
        'visit_date' => '2026-01-07',
    ]);
    FinalScore::factory()->create(['placement_id' => $this->placement->id]);

    $summary = app(ReportService::class)
        ->studentSummary(summaryFilters($this->student->id), $this->admin);

    expect($summary)->not->toBeNull()
        ->and($summary->studentName)->toBe($this->student->name)
        ->and($summary->attendancePresent)->toBe(1)
        ->and($summary->attendanceSick)->toBe(1)
        ->and($summary->journalApproved)->toBe(1)
        ->and($summary->monitorings)->toHaveCount(1)
        ->and($summary->finalScore)->not->toBeNull();
});

test('an unscored student is reported without a final score', function (): void {
    $summary = app(ReportService::class)
        ->studentSummary(summaryFilters($this->student->id), $this->admin);

    expect($summary->finalScore)->toBeNull()
        ->and($summary->grade)->toBeNull()
        ->and($summary->assessmentStatus)->toBe('Belum dinilai');
});

test('the date range narrows the summary', function (): void {
    Attendance::factory()->onDate('2026-01-05')->create([
        'placement_id' => $this->placement->id,
        'status' => AttendanceStatus::Present,
        'verification_status' => VerificationStatus::Verified,
    ]);
    Attendance::factory()->onDate('2026-02-05')->create([
        'placement_id' => $this->placement->id,
        'status' => AttendanceStatus::Present,
        'verification_status' => VerificationStatus::Verified,
    ]);

    $summary = app(ReportService::class)->studentSummary(
        summaryFilters($this->student->id, '2026-01-01', '2026-01-31'),
        $this->admin,
    );

    expect($summary->attendancePresent)->toBe(1);
});

test('the screen shows an empty state until a student is chosen', function (): void {
    $this->actingAs($this->admin)
        ->get('/reports/student_summary')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/show')
            ->where('summary', null)
            ->has('filterOptions.students', 1));
});

test('choosing a student renders the card', function (): void {
    $this->actingAs($this->admin)
        ->get("/reports/student_summary?student_id={$this->student->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('summary.studentName', $this->student->name));
});

test('downloading the summary streams a pdf', function (): void {
    $this->actingAs($this->admin)
        ->get("/reports/student_summary/download?student_id={$this->student->id}")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('a summary for a student outside the users scope is a 404', function (): void {
    $outsider = Student::factory()->create();

    Placement::factory()->active()->create([
        'student_id' => $outsider->id,
        'company_id' => Company::factory()->create(['quota' => 10])->id,
        'teacher_id' => Teacher::factory()->create()->id,
    ]);

    $guru = userWithRole(RoleName::GuruPembimbing);

    $this->actingAs($guru)
        ->get("/reports/student_summary/download?student_id={$outsider->id}")
        ->assertNotFound();
});

test('a supervisor may not open the student summary at all', function (): void {
    $this->actingAs(userWithRole(RoleName::PembimbingIndustri))
        ->get('/reports/student_summary')
        ->assertForbidden();
});
