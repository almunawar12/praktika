<?php

declare(strict_types=1);

use App\Enums\AttendanceStatus;
use App\Enums\ReportType;
use App\Enums\ReviewStatus;
use App\Enums\RoleName;
use App\Enums\VerificationStatus;
use App\Models\Attendance;
use App\Models\Company;
use App\Models\FinalScore;
use App\Models\Journal;
use App\Models\Monitoring;
use App\Models\Placement;
use App\Models\ReportExport;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\ReportService;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->company = Company::factory()->create(['quota' => 10]);
    $this->teacher = Teacher::factory()->create();
    $this->student = Student::factory()->create();

    $this->placement = Placement::factory()->active()->create([
        'student_id' => $this->student->id,
        'company_id' => $this->company->id,
        'teacher_id' => $this->teacher->id,
    ]);
});

test('the service assembles attendance, journal, score, and monitoring for one placement', function (): void {
    Attendance::factory()->create([
        'placement_id' => $this->placement->id,
        'status' => AttendanceStatus::Present,
        'verification_status' => VerificationStatus::Verified,
    ]);
    Attendance::factory()->create([
        'placement_id' => $this->placement->id,
        'status' => AttendanceStatus::Sick,
        'verification_status' => VerificationStatus::Verified,
    ]);

    Journal::factory()->approved()->onDate('2026-01-10')->create(['placement_id' => $this->placement->id]);
    Journal::factory()->onDate('2026-01-11')->create(['placement_id' => $this->placement->id]); // stays pending

    FinalScore::factory()->create(['placement_id' => $this->placement->id]);

    Monitoring::factory()->create([
        'placement_id' => $this->placement->id,
        'teacher_id' => $this->teacher->id,
        'note' => 'Siswa aktif dan disiplin.',
    ]);

    $guru = $this->teacher->user;
    $export = ReportExport::factory()->create([
        'requested_by' => $guru->id,
        'type' => ReportType::StudentSummary,
        'filters' => ['student_id' => $this->student->id],
    ]);

    $dataset = app(ReportService::class)->buildStudentSummary($export);

    expect($dataset->studentName)->toBe($this->student->name)
        ->and($dataset->nis)->toBe($this->student->nis)
        ->and($dataset->attendancePresent)->toBe(1)
        ->and($dataset->attendanceSick)->toBe(1)
        ->and($dataset->attendanceRate)->toBe('50%')
        ->and($dataset->journalTotal)->toBe(2)
        ->and($dataset->journalApproved)->toBe(1)
        ->and($dataset->journalPending)->toBe(1)
        ->and($dataset->finalScore)->not->toBeNull()
        ->and($dataset->monitorings)->toHaveCount(1)
        ->and($dataset->monitorings[0]['note'])->toBe('Siswa aktif dan disiplin.');
});

test('the service reports an unscored student without a final score', function (): void {
    $guru = $this->teacher->user;
    $export = ReportExport::factory()->create([
        'requested_by' => $guru->id,
        'type' => ReportType::StudentSummary,
        'filters' => ['student_id' => $this->student->id],
    ]);

    $dataset = app(ReportService::class)->buildStudentSummary($export);

    expect($dataset->finalScore)->toBeNull()
        ->and($dataset->assessmentStatus)->toBe('Belum dinilai');
});

test('the service refuses a student outside the requester visibility scope', function (): void {
    $otherTeacher = Teacher::factory()->create();
    $guru = $otherTeacher->user;

    $export = ReportExport::factory()->create([
        'requested_by' => $guru->id,
        'type' => ReportType::StudentSummary,
        'filters' => ['student_id' => $this->student->id],
    ]);

    app(ReportService::class)->buildStudentSummary($export);
})->throws(RuntimeException::class);

test('the service refuses a student with no active or completed placement', function (): void {
    $admin = userWithRole(RoleName::AdminPkl);
    $studentWithNoPlacement = Student::factory()->create();

    $export = ReportExport::factory()->create([
        'requested_by' => $admin->id,
        'type' => ReportType::StudentSummary,
        'filters' => ['student_id' => $studentWithNoPlacement->id],
    ]);

    app(ReportService::class)->buildStudentSummary($export);
})->throws(RuntimeException::class);
