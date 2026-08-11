<?php

declare(strict_types=1);

use App\DataTransferObjects\ReportFilters;
use App\Enums\AttendanceStatus;
use App\Enums\ReportFormat;
use App\Enums\ReportType;
use App\Enums\RoleName;
use App\Enums\VerificationStatus;
use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Company;
use App\Models\CompanySupervisor;
use App\Models\Placement;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\ReportService;
use Illuminate\Http\Request;

beforeEach(function (): void {
    $this->company = Company::factory()->create(['quota' => 10]);
    $this->teacher = Teacher::factory()->create();
    $this->student = Student::factory()->create(['name' => 'Andi Pratama', 'nis' => '2001']);

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

test('the reports index redirects to the first type the user may open', function (): void {
    $this->actingAs(userWithRole(RoleName::AdminPkl))
        ->get('/reports')
        ->assertRedirect('/reports/attendance');
});

test('a student may not reach the reports area', function (): void {
    $student = userWithRole(RoleName::Siswa);

    $this->actingAs($student)->get('/reports')->assertForbidden();
    $this->actingAs($student)->get('/reports/attendance')->assertForbidden();
});

test('a report the user has no permission for is forbidden', function (): void {
    $this->actingAs(userWithRole(RoleName::PembimbingIndustri))
        ->get('/reports/assessment')
        ->assertForbidden();
});

test('an unknown report type is a 404', function (): void {
    $this->actingAs(userWithRole(RoleName::AdminPkl))
        ->get('/reports/tidak-ada')
        ->assertNotFound();
});

test('the report screen renders rows straight from the filters', function (): void {
    $this->actingAs(userWithRole(RoleName::AdminPkl))
        ->get('/reports/attendance')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/show')
            ->where('type.value', 'attendance')
            ->has('columns', 8)
            ->has('rows.data', 1)
            ->where('rows.data.0.cells.0', 'Andi Pratama'));
});

test('search narrows the rendered rows', function (): void {
    $this->actingAs(userWithRole(RoleName::AdminPkl))
        ->get('/reports/attendance?search=zzz')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('rows.data', 0));
});

test('invalid filters are rejected with field errors', function (): void {
    $this->actingAs(userWithRole(RoleName::AdminPkl))
        ->get('/reports/attendance?date_from=2026-03-01&date_to=2026-01-01')
        ->assertSessionHasErrors('date_to');
});

test('a supervisor only sees their own company on screen', function (): void {
    $otherCompany = Company::factory()->create(['quota' => 10]);
    Placement::factory()->active()->create([
        'student_id' => Student::factory()->create(['name' => 'Budi'])->id,
        'company_id' => $otherCompany->id,
        'teacher_id' => $this->teacher->id,
    ]);

    $user = userWithRole(RoleName::PembimbingIndustri);
    CompanySupervisor::factory()->create([
        'user_id' => $user->id,
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($user->fresh())
        ->get('/reports/attendance')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('rows.data', 1)
            ->where('rows.data.0.cells.0', 'Andi Pratama'));
});

test('downloading streams the file without storing it and records the activity', function (ReportFormat $format): void {
    $admin = userWithRole(RoleName::AdminPkl);

    $response = $this->actingAs($admin)
        ->get("/reports/attendance/download?format={$format->value}");

    $response->assertOk()
        ->assertHeader('content-type', $format->contentType());

    expect($response->headers->get('content-disposition'))->toContain('rekap-absensi-');

    $activity = Activity::query()->where('description', 'report.download')->sole();

    expect($activity->causer_id)->toBe($admin->id)
        ->and($activity->properties['type'])->toBe('attendance')
        ->and($activity->properties['format'])->toBe($format->value);
})->with([
    'pdf' => ReportFormat::Pdf,
    'xlsx' => ReportFormat::Xlsx,
]);

test('a download covers every row, not just the visible page', function (): void {
    foreach (range(1, 20) as $index) {
        Placement::factory()->active()->create([
            'student_id' => Student::factory()->create(['nis' => "30{$index}"])->id,
            'company_id' => $this->company->id,
            'teacher_id' => $this->teacher->id,
        ]);
    }

    $admin = userWithRole(RoleName::AdminPkl);

    $this->actingAs($admin)
        ->get('/reports/attendance')
        ->assertInertia(fn ($page) => $page->has('rows.data', 15)->where('rows.total', 21));

    $this->actingAs($admin)
        ->get('/reports/attendance/download?format=xlsx')
        ->assertOk();

    // The spreadsheet is built from dataset(), which is never paginated.
    $dataset = app(ReportService::class)->dataset(
        ReportType::Attendance,
        ReportFilters::fromRequest(Request::create('/reports/attendance'), ReportType::Attendance),
        $admin,
    );

    expect($dataset->rows)->toHaveCount(21);
});

test('downloading a report the user may not open is forbidden', function (): void {
    $this->actingAs(userWithRole(RoleName::PembimbingIndustri))
        ->get('/reports/assessment/download?format=pdf')
        ->assertForbidden();
});

test('the old generate route is gone', function (): void {
    $this->actingAs(userWithRole(RoleName::AdminPkl))
        ->post('/reports', ['type' => 'attendance', 'format' => 'pdf'])
        ->assertStatus(405);
});
