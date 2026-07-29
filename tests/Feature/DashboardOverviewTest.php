<?php

declare(strict_types=1);

use App\Enums\AssessmentType;
use App\Enums\AttendanceStatus;
use App\Enums\ReviewStatus;
use App\Enums\RoleName;
use App\Models\Assessment;
use App\Models\Attendance;
use App\Models\Company;
use App\Models\Department;
use App\Models\Journal;
use App\Models\Monitoring;
use App\Models\Placement;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia;

test('admin roles receive the overview payload', function (RoleName $role): void {
    $this->actingAs(userWithRole($role))
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('overview.stats', 6)
            ->where('overview.stats.0.label', 'Total Siswa PKL')
            ->where('overview.stats.1.label', 'Total Perusahaan')
            ->where('overview.stats.2.label', 'PKL Aktif')
            ->where('overview.stats.3.label', 'PKL Selesai')
            ->where('overview.stats.4.label', 'Jurnal Pending')
            ->where('overview.stats.5.label', 'Kehadiran Hari Ini')
        );
})->with([
    'super admin' => RoleName::SuperAdmin,
    'admin pkl' => RoleName::AdminPkl,
]);

test('industri gets no overview', function (): void {
    $this->actingAs(userWithRole(RoleName::PembimbingIndustri))
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('overview', null));
});

test('a student with no active placement gets no overview', function (): void {
    $this->actingAs(userWithRole(RoleName::Siswa))
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('overview', null));
});

test('guru pembimbing gets a teacher overview', function (): void {
    $teacher = Teacher::factory()->create();

    $this->actingAs($teacher->user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('overview.studentsNeedingAttention')
            ->has('overview.upcomingVisits')
        );
});

test('a student with an active placement gets a student overview', function (): void {
    $placement = Placement::factory()->active()->create();

    $this->actingAs($placement->student->user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('overview.placement.company', $placement->company->name)
            ->where('overview.placement.teacher', $placement->teacher->name)
        );
});

test('a student sees their own placement progress and today status', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-07-15 10:00:00'));

    $placement = Placement::factory()->active()->create([
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-30',
    ]);

    Attendance::factory()->create([
        'placement_id' => $placement->id,
        'date' => '2026-07-15',
        'status' => AttendanceStatus::Present,
    ]);

    $this->actingAs($placement->student->user)
        ->get('/dashboard')
        ->assertInertia(fn (AssertableInertia $page) => $page
            // Day 15 of a 30-day term.
            ->where('overview.progress.daysElapsed', 15)
            ->where('overview.progress.totalDays', 30)
            ->where('overview.progress.percentage', 50)
            ->where('overview.todayStatus.status', 'present')
            ->where('overview.attendanceRecap.hadir', 1)
        );
});

test('a student sees their rejected journals as an alert', function (): void {
    $placement = Placement::factory()->active()->create();

    Journal::factory()->teacherRejected()->create([
        'placement_id' => $placement->id,
    ]);

    $this->actingAs($placement->student->user)
        ->get('/dashboard')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('overview.rejectedJournals', 1)
            ->where('overview.rejectedJournals.0.note', 'Hasil pekerjaan belum sesuai kompetensi.')
        );
});

test('a teacher sees supervised placements needing attention and unvisited placements', function (): void {
    $teacher = Teacher::factory()->create();

    $troubled = Placement::factory()->active()->create(['teacher_id' => $teacher->id]);
    Attendance::factory()->count(3)->sequence(
        ['date' => '2026-07-10'],
        ['date' => '2026-07-11'],
        ['date' => '2026-07-12'],
    )->create([
        'placement_id' => $troubled->id,
        'status' => AttendanceStatus::Absent,
    ]);

    // Not this teacher's student — must never show up.
    Placement::factory()->active()->create();

    $this->actingAs($teacher->user)
        ->get('/dashboard')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('overview.studentsNeedingAttention', 1)
            ->where('overview.studentsNeedingAttention.0.placementId', $troubled->id)
            ->where('overview.studentsNeedingAttention.0.alphaCount', 3)
            ->has('overview.upcomingVisits', 1)
            ->where('overview.upcomingVisits.0.placementId', $troubled->id)
        );
});

test('student and company tiles report the monthly delta', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-07-15 10:00:00'));

    // One student from last month, two new this month.
    Student::factory()->create(['created_at' => '2026-06-20 08:00:00']);
    Student::factory()->count(2)->create();
    Company::factory()->create(['created_at' => '2026-05-01 08:00:00']);

    $this->actingAs(userWithRole(RoleName::AdminPkl))
        ->get('/dashboard')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('overview.stats.0.value', Student::count())
            ->where('overview.stats.0.subLabel', '+2 dari bulan lalu')
            ->where('overview.stats.0.subVariant', 'positive')
            ->where('overview.stats.1.subLabel', '+0 dari bulan lalu')
            ->where('overview.stats.1.subVariant', 'muted')
        );
});

test('placement tiles report their share of all placements', function (): void {
    Placement::factory()->active()->count(3)->create();
    Placement::factory()->completed()->create();

    $this->actingAs(userWithRole(RoleName::AdminPkl))
        ->get('/dashboard')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('overview.stats.2.value', 3)
            ->where('overview.stats.2.subLabel', '75% dari total')
            ->where('overview.stats.3.value', 1)
            ->where('overview.stats.3.subLabel', '25% dari total')
        );
});

test('the journal tile counts pending journals and flags them', function (): void {
    Journal::factory()->create(['status' => ReviewStatus::Pending]);
    Journal::factory()->approved()->create();

    $this->actingAs(userWithRole(RoleName::AdminPkl))
        ->get('/dashboard')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('overview.stats.4.value', 1)
            ->where('overview.stats.4.subLabel', 'Perlu verifikasi')
            ->where('overview.stats.4.subVariant', 'warning')
        );
});

test('the activity chart zero-fills thirty days of counts', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-07-15 10:00:00'));

    // Two placements: attendance is unique per placement per day, so two records
    // on the same date can only come from two students.
    $placements = Placement::factory()->active()->count(2)->create();

    Attendance::factory()->create([
        'placement_id' => $placements[0]->id,
        'date' => '2026-07-10',
    ]);
    Attendance::factory()->create([
        'placement_id' => $placements[1]->id,
        'date' => '2026-07-10',
    ]);
    Journal::factory()->create([
        'placement_id' => $placements[0]->id,
        'date' => '2026-07-10',
        'status' => ReviewStatus::Pending,
    ]);
    Monitoring::factory()->create([
        'placement_id' => $placements[0]->id,
        'visit_date' => '2026-07-10',
    ]);
    Assessment::factory()->create([
        'placement_id' => $placements[0]->id,
        'type' => AssessmentType::Industry,
        'submitted_at' => '2026-07-10 09:00:00',
    ]);

    $this->actingAs(userWithRole(RoleName::AdminPkl))
        ->get('/dashboard')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('overview.activityChart', 30)
            ->where('overview.activityChart.0.date', '2026-06-16')
            ->where('overview.activityChart.29.date', '2026-07-15')
            ->where('overview.activityChart.24.date', '2026-07-10')
            ->where('overview.activityChart.24.absensi', 2)
            ->where('overview.activityChart.24.jurnal', 1)
            ->where('overview.activityChart.24.monitoring', 1)
            ->where('overview.activityChart.24.penilaian', 1)
            ->where('overview.activityChart.0.absensi', 0)
        );
});

test('attendance today buckets statuses and derives alpha from active placements', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-07-15 10:00:00'));

    $placements = Placement::factory()->active()->count(4)->create();

    Attendance::factory()->create([
        'placement_id' => $placements[0]->id,
        'date' => '2026-07-15',
        'status' => AttendanceStatus::Present,
    ]);
    Attendance::factory()->permit()->create([
        'placement_id' => $placements[1]->id,
        'date' => '2026-07-15',
    ]);
    Attendance::factory()->sick()->create([
        'placement_id' => $placements[2]->id,
        'date' => '2026-07-15',
    ]);
    // placements[3] has no record today: counted as alpha.

    $this->actingAs(userWithRole(RoleName::AdminPkl))
        ->get('/dashboard')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('overview.attendanceToday.hadir', 1)
            ->where('overview.attendanceToday.izin', 1)
            ->where('overview.attendanceToday.sakit', 1)
            ->where('overview.attendanceToday.alpha', 1)
            ->where('overview.attendanceToday.total', 4)
            ->where('overview.attendanceToday.rate', 25)
        );
});

test('pending journals list the four newest with student, class, and company', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-07-15 10:00:00'));

    $placement = Placement::factory()->active()->create();

    // Distinct dates: journals are unique per placement per day.
    Journal::factory()->count(5)->sequence(
        ['date' => '2026-07-10', 'created_at' => '2026-07-10 08:00:00'],
        ['date' => '2026-07-11', 'created_at' => '2026-07-11 08:00:00'],
        ['date' => '2026-07-12', 'created_at' => '2026-07-12 08:00:00'],
        ['date' => '2026-07-13', 'created_at' => '2026-07-13 08:00:00'],
        ['date' => '2026-07-14', 'created_at' => '2026-07-14 08:00:00'],
    )->create([
        'placement_id' => $placement->id,
        'status' => ReviewStatus::Pending,
    ]);

    $this->actingAs(userWithRole(RoleName::AdminPkl))
        ->get('/dashboard')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('overview.pendingJournals', 4)
            ->where('overview.pendingJournals.0.student', $placement->student->name)
            ->where('overview.pendingJournals.0.className', $placement->student->schoolClass->name)
            ->where('overview.pendingJournals.0.company', $placement->company->name)
            ->where('overview.pendingJournals.0.submittedAt', CarbonImmutable::parse('2026-07-14 08:00:00')->toIso8601String())
        );
});

test('recent assessments list submitted scores with the short type label', function (): void {
    $placement = Placement::factory()->active()->create();

    Assessment::factory()->create([
        'placement_id' => $placement->id,
        'type' => AssessmentType::Industry,
        'average_score' => '85.50',
        'submitted_at' => '2026-07-14 09:00:00',
    ]);
    // Draft assessments never appear.
    Assessment::factory()->create([
        'placement_id' => $placement->id,
        'type' => AssessmentType::Teacher,
        'submitted_at' => null,
    ]);

    $this->actingAs(userWithRole(RoleName::AdminPkl))
        ->get('/dashboard')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('overview.recentAssessments', 1)
            ->where('overview.recentAssessments.0.student', $placement->student->name)
            ->where('overview.recentAssessments.0.type', 'Industri')
            ->where('overview.recentAssessments.0.score', 85.5)
        );
});

test('active placements are grouped by department with shares', function (): void {
    $rpl = Department::factory()->create(['name' => 'Rekayasa Perangkat Lunak']);
    $akl = Department::factory()->create(['name' => 'Akuntansi']);
    $rplClass = SchoolClass::factory()->create(['department_id' => $rpl->id]);
    $aklClass = SchoolClass::factory()->create(['department_id' => $akl->id]);

    // A fresh student per placement: only one active placement per student exists.
    Placement::factory()->active()->count(3)->create([
        'student_id' => Student::factory()->state(['class_id' => $rplClass->id]),
    ]);
    Placement::factory()->active()->create([
        'student_id' => Student::factory()->state(['class_id' => $aklClass->id]),
    ]);

    $this->actingAs(userWithRole(RoleName::AdminPkl))
        ->get('/dashboard')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('overview.activeByDepartment', 2)
            ->where('overview.activeByDepartment.0.name', 'Rekayasa Perangkat Lunak')
            ->where('overview.activeByDepartment.0.count', 3)
            ->where('overview.activeByDepartment.0.percentage', 75)
            ->where('overview.activeByDepartment.1.count', 1)
            ->where('overview.activeByDepartment.1.percentage', 25)
        );
});
