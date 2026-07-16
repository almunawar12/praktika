<?php

declare(strict_types=1);

use App\Enums\AttendanceStatus;
use App\Enums\ReportFormat;
use App\Enums\ReportStatus;
use App\Enums\ReportType;
use App\Enums\RoleName;
use App\Enums\VerificationStatus;
use App\Jobs\GenerateReportJob;
use App\Models\Attendance;
use App\Models\Company;
use App\Models\CompanySupervisor;
use App\Models\Placement;
use App\Models\ReportExport;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\ReportService;
use Illuminate\Support\Facades\Queue;
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

    Attendance::factory()->create([
        'placement_id' => $this->placement->id,
        'status' => AttendanceStatus::Present,
        'verification_status' => VerificationStatus::Verified,
    ]);
});

test('generating a report queues a job and records a pending export', function (): void {
    Queue::fake();

    $admin = userWithRole(RoleName::AdminPkl);

    $this->actingAs($admin)
        ->post('/reports', ['type' => 'attendance', 'format' => 'pdf'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $export = ReportExport::query()->where('requested_by', $admin->id)->sole();

    expect($export->status)->toBe(ReportStatus::Pending)
        ->and($export->type)->toBe(ReportType::Attendance);

    Queue::assertPushed(GenerateReportJob::class);
});

test('the queue job renders a file and completes the export', function (ReportFormat $format): void {
    Storage::fake('local');

    $admin = userWithRole(RoleName::AdminPkl);
    $export = ReportExport::factory()->create([
        'requested_by' => $admin->id,
        'type' => ReportType::Attendance,
        'format' => $format,
    ]);

    GenerateReportJob::dispatchSync($export);

    $export->refresh();

    expect($export->status)->toBe(ReportStatus::Completed)
        ->and($export->file_path)->not->toBeNull()
        // One row per placement in scope.
        ->and($export->row_count)->toBe(1);

    Storage::disk('local')->assertExists($export->file_path);
})->with([
    'pdf' => ReportFormat::Pdf,
    'xlsx' => ReportFormat::Xlsx,
]);

test('a completed report is downloaded only by its owner', function (): void {
    Storage::fake('local');

    $owner = userWithRole(RoleName::AdminPkl);
    $other = userWithRole(RoleName::AdminPkl);

    $export = ReportExport::factory()->create([
        'requested_by' => $owner->id,
        'type' => ReportType::Attendance,
        'format' => ReportFormat::Pdf,
    ]);
    GenerateReportJob::dispatchSync($export);

    $this->actingAs($owner)->get("/reports/{$export->id}/download")->assertOk();

    // Another admin holds every report permission, yet the file is not theirs.
    $this->actingAs($other)->get("/reports/{$export->id}/download")->assertForbidden();
});

test('a report that is not finished can not be downloaded', function (): void {
    $owner = userWithRole(RoleName::AdminPkl);
    $export = ReportExport::factory()->create(['requested_by' => $owner->id]);

    $this->actingAs($owner)->get("/reports/{$export->id}/download")->assertNotFound();
});

test('a student can not reach the reports area at all', function (): void {
    $this->actingAs($this->student->user)->get('/reports')->assertForbidden();

    $this->actingAs($this->student->user)
        ->post('/reports', ['type' => 'attendance', 'format' => 'pdf'])
        ->assertForbidden();
});

test('a supervisor can not generate a report type they lack', function (): void {
    $supervisor = CompanySupervisor::factory()->create(['company_id' => $this->company->id]);

    // Pembimbing Industri may pull attendance and journal recaps, never assessment.
    $this->actingAs($supervisor->user)
        ->post('/reports', ['type' => 'assessment', 'format' => 'pdf'])
        ->assertForbidden();

    $this->actingAs($supervisor->user)
        ->post('/reports', ['type' => 'attendance', 'format' => 'pdf'])
        ->assertSessionHasNoErrors();
});

test('a report covers only the placements its requester may see', function (): void {
    // A second placement under a different teacher and company.
    Placement::factory()->active()->create();

    $guru = $this->teacher->user;
    $export = ReportExport::factory()->create([
        'requested_by' => $guru->id,
        'type' => ReportType::Attendance,
        'format' => ReportFormat::Pdf,
    ]);

    $dataset = app(ReportService::class)->build($export);

    // The guru's recap contains their one student, not the other teacher's.
    expect($dataset->rows)->toHaveCount(1)
        ->and($dataset->rows[0][0])->toBe($this->student->name);
});

test('the owner can delete a report and its file', function (): void {
    Storage::fake('local');

    $owner = userWithRole(RoleName::AdminPkl);
    $export = ReportExport::factory()->create([
        'requested_by' => $owner->id,
        'type' => ReportType::Attendance,
        'format' => ReportFormat::Pdf,
    ]);
    GenerateReportJob::dispatchSync($export);
    $path = $export->refresh()->file_path;

    $this->actingAs($owner)->delete("/reports/{$export->id}")->assertSessionHasNoErrors();

    Storage::disk('local')->assertMissing($path);
    expect(ReportExport::query()->whereKey($export->id)->exists())->toBeFalse();
});

test('the queue job records why it failed instead of hanging', function (): void {
    $admin = userWithRole(RoleName::AdminPkl);
    $export = ReportExport::factory()->create(['requested_by' => $admin->id]);

    (new GenerateReportJob($export))->failed(new RuntimeException('disk penuh'));

    $export->refresh();

    expect($export->status)->toBe(ReportStatus::Failed)
        ->and($export->error)->toContain('disk penuh')
        ->and($export->file_path)->toBeNull();
});
