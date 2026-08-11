# Rekap Siswa (Per-Student Combined Report) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a fifth report type, "Rekap Siswa", that produces one PDF combining a single student's attendance summary, journal summary, final score, and monitoring visits — generated through the same `/reports` form and sync-generation flow as the existing four report types.

**Architecture:** A new `ReportType::StudentSummary` case drives a separate data path: `ReportService::buildStudentSummary()` resolves the student's most recent Active/Completed placement (through the existing `Placement::visibleTo` scope) and assembles a new `StudentSummaryDataset` DTO, which a new Blade view renders to PDF. `ReportController::store()` branches on type to call this path instead of the generic tabular `ReportService::build()` path added by the prior sync-report-generation work. The frontend form swaps its format picker for a searchable student picker when this type is selected.

**Tech Stack:** Laravel 13, PHP 8.3, Pest, barryvdh/laravel-dompdf, Inertia.js + React 19 + TypeScript, existing `Combobox` component (`resources/js/components/shared/combobox.tsx`).

## Global Constraints

- Strict typing: `declare(strict_types=1);` at the top of every PHP file touched or created.
- Follow PSR-12 / Laravel Pint formatting.
- `reports.student_summary` is granted to `AdminPkl` and `GuruPembimbing` only — never `PembimbingIndustri` (they lack `reports.assessment`, and this report always includes the score section).
- The student resolved for a summary must come from a `Placement` visible to the requester (`Placement::visibleTo`) — never a raw, unscoped `Student::find()`.
- Generation failures land as a `failed` `ReportExport` row with a truncated `error`, never a bare 500 (established by the prior sync-report-generation change).

---

### Task 1: Data layer — DTO and `ReportService::buildStudentSummary()`

**Files:**
- Create: `app/DataTransferObjects/StudentSummaryDataset.php`
- Modify: `app/Enums/ReportType.php`
- Modify: `app/Support/PermissionCatalog.php`
- Modify: `app/Services/ReportService.php`
- Create: `tests/Feature/Report/StudentSummaryReportTest.php`

**Interfaces:**
- Consumes: `App\Models\Placement::scopeVisibleTo` (existing, unchanged), `App\Models\ReportExport` (existing — `filters` array gains a `student_id` key, no schema change needed since `filters` is already a JSON column), `App\Enums\PlacementStatus::Active`/`Completed` (existing).
- Produces: `App\Services\ReportService::buildStudentSummary(ReportExport $export): StudentSummaryDataset` — the method `ReportController::store()` will call in Task 2. Throws `RuntimeException` (message safe to surface to the report owner) when `student_id` is missing from filters or resolves to no visible Active/Completed placement.

- [ ] **Step 1: Write the failing test for the service method**

Create `tests/Feature/Report/StudentSummaryReportTest.php`:

```php
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

    Journal::factory()->approved()->create(['placement_id' => $this->placement->id]);
    Journal::factory()->create(['placement_id' => $this->placement->id]); // stays pending

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
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/Report/StudentSummaryReportTest.php`
Expected: Fatal error / failure — `ReportType::StudentSummary` doesn't exist yet and `ReportService::buildStudentSummary()` doesn't exist yet.

- [ ] **Step 3: Add the `StudentSummary` case to `ReportType`**

In `app/Enums/ReportType.php`, add the case and its `label()`/`columns()` arms:

```php
enum ReportType: string
{
    case Attendance = 'attendance';
    case Journal = 'journal';
    case Assessment = 'assessment';
    case Monitoring = 'monitoring';
    case StudentSummary = 'student_summary';

    public function label(): string
    {
        return match ($this) {
            self::Attendance => 'Rekap Absensi',
            self::Journal => 'Rekap Jurnal',
            self::Assessment => 'Rekap Nilai PKL',
            self::Monitoring => 'Rekap Monitoring',
            self::StudentSummary => 'Rekap Siswa',
        };
    }
```

And in `columns()`:

```php
    public function columns(): array
    {
        return match ($this) {
            self::Attendance => ['Siswa', 'NIS', 'Perusahaan', 'Hadir', 'Izin', 'Sakit', 'Alpa', '% Kehadiran'],
            self::Journal => ['Siswa', 'NIS', 'Perusahaan', 'Total', 'Disetujui', 'Pending', 'Ditolak'],
            self::Assessment => ['Siswa', 'NIS', 'Perusahaan', 'Nilai Industri', 'Nilai Guru', 'Nilai Akhir', 'Grade', 'Status'],
            self::Monitoring => ['Siswa', 'NIS', 'Perusahaan', 'Guru', 'Jumlah Kunjungan', 'Kunjungan Terakhir'],
            // Not a flat table — rendered by its own Blade view via ReportService::buildStudentSummary().
            self::StudentSummary => [],
        };
    }
```

`permission()` needs no change — it already derives `"reports.{$this->value}"`, giving `reports.student_summary` for free.

- [ ] **Step 4: Grant the new permission to Admin PKL and Guru Pembimbing**

In `app/Support/PermissionCatalog.php`, change line 47:

```php
            'reports' => ['attendance', 'journal', 'assessment', 'monitoring', 'student_summary'],
```

In `adminPkl()` (around line 128), change:

```php
            'reports.attendance', 'reports.journal', 'reports.assessment', 'reports.monitoring',
            'reports.student_summary',
```

In `guruPembimbing()` (around line 164), change:

```php
            'reports.attendance', 'reports.journal', 'reports.assessment', 'reports.monitoring',
            'reports.student_summary',
```

Leave `pembimbingIndustri()` untouched — it must not gain `reports.student_summary`.

- [ ] **Step 5: Create the `StudentSummaryDataset` DTO**

Create `app/DataTransferObjects/StudentSummaryDataset.php`:

```php
<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

/**
 * The rendered content of a "Rekap Siswa" report — one student's whole PKL,
 * combined. Unlike ReportDataset this isn't a flat table: it's sectioned
 * (identity, attendance, journal, score, monitoring), so it has its own Blade
 * view instead of sharing reports/pdf.blade.php.
 */
final readonly class StudentSummaryDataset
{
    /**
     * @param  array<int, array{visit_date: string, note: string, recommendation: ?string}>  $monitorings
     */
    public function __construct(
        public string $studentName,
        public string $nis,
        public string $className,
        public string $companyName,
        public string $teacherName,
        public string $period,
        public string $scope,
        public int $attendancePresent,
        public int $attendancePermit,
        public int $attendanceSick,
        public int $attendanceAbsent,
        public string $attendanceRate,
        public int $journalTotal,
        public int $journalApproved,
        public int $journalPending,
        public int $journalRejected,
        public ?string $industryScore,
        public ?string $teacherScore,
        public ?string $finalScore,
        public ?string $grade,
        public string $assessmentStatus,
        public array $monitorings,
        public string $requestedBy,
        public string $generatedAt,
    ) {}
}
```

- [ ] **Step 6: Implement `ReportService::buildStudentSummary()` and its private helpers**

In `app/Services/ReportService.php`, add these imports alongside the existing ones:

```php
use App\DataTransferObjects\StudentSummaryDataset;
```

(All other needed imports — `AttendanceStatus`, `PlacementStatus`, `ReviewStatus`, `VerificationStatus`, `Placement`, `ReportExport`, `CarbonImmutable`, `DB` — are already imported in this file. `RuntimeException` is a global class and needs no `use`.)

Add the public method (place it after `build()`):

```php
    public function buildStudentSummary(ReportExport $export): StudentSummaryDataset
    {
        $export->loadMissing('requester');

        $studentId = $this->filter($export, 'student_id');

        if ($studentId === null) {
            throw new \RuntimeException('Siswa belum dipilih.');
        }

        $placement = Placement::query()
            ->visibleTo($export->requester)
            ->whereIn('status', [PlacementStatus::Active->value, PlacementStatus::Completed->value])
            ->where('student_id', $studentId)
            ->with(['student.schoolClass', 'company', 'teacher', 'finalScore'])
            ->latest('start_date')
            ->first();

        if ($placement === null) {
            throw new \RuntimeException('Siswa tidak memiliki penempatan aktif atau selesai yang dapat dilaporkan.');
        }

        $attendance = $this->studentAttendanceStats($placement, $export);
        $journal = $this->studentJournalStats($placement, $export);
        $score = $placement->finalScore;

        return new StudentSummaryDataset(
            studentName: $placement->student->name,
            nis: $placement->student->nis,
            className: $placement->student->schoolClass->name,
            companyName: $placement->company->name,
            teacherName: $placement->teacher->name,
            period: $placement->start_date->format('d/m/Y').' – '.$placement->end_date->format('d/m/Y'),
            scope: $this->describeScope($export),
            attendancePresent: $attendance['present'],
            attendancePermit: $attendance['permit'],
            attendanceSick: $attendance['sick'],
            attendanceAbsent: $attendance['absent'],
            attendanceRate: $attendance['rate'],
            journalTotal: $journal['total'],
            journalApproved: $journal['approved'],
            journalPending: $journal['pending'],
            journalRejected: $journal['rejected'],
            industryScore: $score === null ? null : number_format((float) $score->industry_score, 2),
            teacherScore: $score === null ? null : number_format((float) $score->teacher_score, 2),
            finalScore: $score === null ? null : number_format((float) $score->final_score, 2),
            grade: $score?->grade,
            assessmentStatus: match (true) {
                $score === null => 'Belum dinilai',
                $score->isPublished() => 'Dipublikasikan',
                default => 'Draft',
            },
            monitorings: $this->studentMonitoringVisits($placement, $export),
            requestedBy: $export->requester->name,
            generatedAt: CarbonImmutable::now()->format('d/m/Y H:i'),
        );
    }

    /**
     * @return array{present: int, permit: int, sick: int, absent: int, rate: string}
     */
    private function studentAttendanceStats(Placement $placement, ReportExport $export): array
    {
        $query = DB::table('attendances')
            ->where('placement_id', $placement->id)
            ->where('verification_status', '!=', VerificationStatus::Rejected->value);

        $this->applyDateRange($query, $export, 'date');

        $counts = $query->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $present = (int) ($counts[AttendanceStatus::Present->value] ?? 0);
        $permit = (int) ($counts[AttendanceStatus::Permit->value] ?? 0);
        $sick = (int) ($counts[AttendanceStatus::Sick->value] ?? 0);
        $absent = (int) ($counts[AttendanceStatus::Absent->value] ?? 0);
        $total = $present + $permit + $sick + $absent;

        $rate = $total === 0 ? 0.0 : round($present / $total * 100, 1);

        return ['present' => $present, 'permit' => $permit, 'sick' => $sick, 'absent' => $absent, 'rate' => "{$rate}%"];
    }

    /**
     * @return array{total: int, approved: int, pending: int, rejected: int}
     */
    private function studentJournalStats(Placement $placement, ReportExport $export): array
    {
        $query = DB::table('journals')->whereNull('deleted_at')->where('placement_id', $placement->id);

        $this->applyDateRange($query, $export, 'date');

        $counts = $query->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $approved = (int) ($counts[ReviewStatus::Approved->value] ?? 0);
        $pending = (int) ($counts[ReviewStatus::Pending->value] ?? 0);
        $rejected = (int) ($counts[ReviewStatus::Rejected->value] ?? 0);

        return ['total' => $approved + $pending + $rejected, 'approved' => $approved, 'pending' => $pending, 'rejected' => $rejected];
    }

    /**
     * @return array<int, array{visit_date: string, note: string, recommendation: ?string}>
     */
    private function studentMonitoringVisits(Placement $placement, ReportExport $export): array
    {
        $query = DB::table('monitorings')->whereNull('deleted_at')->where('placement_id', $placement->id);

        $this->applyDateRange($query, $export, 'visit_date');

        return $query
            ->orderBy('visit_date')
            ->get(['visit_date', 'note', 'recommendation'])
            ->map(fn (object $row): array => [
                'visit_date' => CarbonImmutable::parse((string) $row->visit_date)->format('d/m/Y'),
                'note' => (string) $row->note,
                'recommendation' => $row->recommendation === null ? null : (string) $row->recommendation,
            ])
            ->all();
    }
```

- [ ] **Step 7: Run the tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/Report/StudentSummaryReportTest.php`
Expected: All 4 tests pass.

- [ ] **Step 8: Run the full suite**

Run: `vendor/bin/pest`
Expected: No regressions (the permission/enum changes are additive; `PermissionCatalog::all()`/`Role::count()` assertions in `RolePermissionTest.php` are computed from the catalogue, not hardcoded, so they stay green automatically).

- [ ] **Step 9: Commit**

```bash
git add app/DataTransferObjects/StudentSummaryDataset.php app/Enums/ReportType.php app/Support/PermissionCatalog.php app/Services/ReportService.php tests/Feature/Report/StudentSummaryReportTest.php
git commit -m "feat: add student-summary report data layer"
```

---

### Task 2: HTTP layer — request, controller, sidebar, Blade view

**Files:**
- Modify: `app/Http/Requests/Report/StoreReportRequest.php`
- Modify: `app/Http/Controllers/Report/ReportController.php`
- Modify: `app/Support/SidebarBuilder.php`
- Create: `resources/views/reports/student-summary.blade.php`
- Modify: `tests/Feature/Report/StudentSummaryReportTest.php` (append HTTP-level cases)

**Interfaces:**
- Consumes: `App\Services\ReportService::buildStudentSummary()` (Task 1), `App\DataTransferObjects\StudentSummaryDataset` (Task 1).
- Produces: `POST /reports` with `type=student_summary` now completes end-to-end. `GET /reports` response gains a `students` prop (Task 3 consumes it).

- [ ] **Step 1: Write the failing HTTP-level tests**

Append to `tests/Feature/Report/StudentSummaryReportTest.php` (after the existing tests, same file):

```php
test('generating a student summary produces a completed PDF export', function (): void {
    Storage::fake('local');

    Attendance::factory()->create([
        'placement_id' => $this->placement->id,
        'status' => AttendanceStatus::Present,
        'verification_status' => VerificationStatus::Verified,
    ]);

    $guru = $this->teacher->user;

    $this->actingAs($guru)
        ->post('/reports', [
            'type' => 'student_summary',
            'format' => 'pdf',
            'student_id' => $this->student->id,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $export = ReportExport::query()->where('requested_by', $guru->id)->sole();

    expect($export->status)->toBe(\App\Enums\ReportStatus::Completed)
        ->and($export->format)->toBe(\App\Enums\ReportFormat::Pdf)
        ->and($export->file_path)->not->toBeNull();

    Storage::disk('local')->assertExists($export->file_path);
});

test('a student summary is forced to PDF even if xlsx is submitted', function (): void {
    Storage::fake('local');

    $guru = $this->teacher->user;

    $this->actingAs($guru)
        ->post('/reports', [
            'type' => 'student_summary',
            'format' => 'xlsx',
            'student_id' => $this->student->id,
        ])
        ->assertSessionHasNoErrors();

    $export = ReportExport::query()->where('requested_by', $guru->id)->sole();

    expect($export->format)->toBe(\App\Enums\ReportFormat::Pdf);
});

test('a supervisor can not generate a student summary at all', function (): void {
    $supervisor = \App\Models\CompanySupervisor::factory()->create(['company_id' => $this->company->id]);

    $this->actingAs($supervisor->user)
        ->post('/reports', [
            'type' => 'student_summary',
            'format' => 'pdf',
            'student_id' => $this->student->id,
        ])
        ->assertForbidden();
});

test('a guru pembimbing generating a summary for a student outside their placements fails', function (): void {
    Storage::fake('local');

    $otherStudent = Student::factory()->create();
    $otherTeacher = Teacher::factory()->create();
    Placement::factory()->active()->create([
        'student_id' => $otherStudent->id,
        'teacher_id' => $otherTeacher->id,
    ]);

    $guru = $this->teacher->user;

    $this->actingAs($guru)
        ->post('/reports', [
            'type' => 'student_summary',
            'format' => 'pdf',
            'student_id' => $otherStudent->id,
        ])
        ->assertSessionHasNoErrors();

    $export = ReportExport::query()->where('requested_by', $guru->id)->sole();

    expect($export->status)->toBe(\App\Enums\ReportStatus::Failed)
        ->and($export->error)->not->toBeNull();
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/Report/StudentSummaryReportTest.php`
Expected: The 4 new tests fail — `student_id` isn't a recognized field yet, the controller doesn't branch on `StudentSummary`, and `reports.student-summary` view doesn't exist.

- [ ] **Step 3: Add `student_id` validation to `StoreReportRequest`**

In `app/Http/Requests/Report/StoreReportRequest.php`, add to `rules()` (after `company_id`):

```php
            'student_id' => ['required_if:type,student_summary', 'uuid', Rule::exists('students', 'id')],
```

Add to `filters()`'s returned array:

```php
    public function filters(): array
    {
        return array_filter([
            'department_id' => (string) $this->input('department_id', ''),
            'company_id' => (string) $this->input('company_id', ''),
            'date_from' => (string) $this->input('date_from', ''),
            'date_to' => (string) $this->input('date_to', ''),
            'student_id' => (string) $this->input('student_id', ''),
        ], fn (string $value): bool => $value !== '');
    }
```

Add to `attributes()`:

```php
            'student_id' => 'siswa',
```

- [ ] **Step 4: Branch `ReportController::store()` on `StudentSummary`, and add the `students` picker to `index()`**

In `app/Http/Controllers/Report/ReportController.php`, add imports:

```php
use App\Enums\PlacementStatus;
use App\Models\Placement;
use App\Models\Student;
```

Replace `store()` with:

```php
    public function store(StoreReportRequest $request, ReportService $service): RedirectResponse
    {
        $type = $request->enum('type', ReportType::class);
        $format = $type === ReportType::StudentSummary ? ReportFormat::Pdf : $request->enum('format', ReportFormat::class);

        $export = ReportExport::create([
            'requested_by' => $request->user()->id,
            'type' => $type,
            'format' => $format,
            'filters' => $request->filters(),
            'status' => ReportStatus::Processing,
        ]);

        try {
            if ($type === ReportType::StudentSummary) {
                $summary = $service->buildStudentSummary($export);
                $contents = Pdf::loadView('reports.student-summary', ['dataset' => $summary])->output();
                $rowCount = 1;
            } else {
                $dataset = $service->build($export);
                $contents = match ($export->format) {
                    ReportFormat::Pdf => Pdf::loadView('reports.pdf', ['dataset' => $dataset])->output(),
                    ReportFormat::Xlsx => Excel::raw(new ReportSpreadsheet($dataset), ExcelWriter::XLSX),
                };
                $rowCount = count($dataset->rows);
            }

            $path = 'reports/'.$export->id.'.'.$export->format->extension();
            $fileName = Str::slug($export->type->label())
                .'-'.CarbonImmutable::now()->format('Ymd-His')
                .'.'.$export->format->extension();

            // Private disk: reports contain student data and are only ever streamed
            // back to their owner through the authorized download route.
            Storage::disk('local')->put($path, $contents);

            $export->update([
                'status' => ReportStatus::Completed,
                'file_path' => $path,
                'file_name' => $fileName,
                'row_count' => $rowCount,
                'completed_at' => CarbonImmutable::now(),
            ]);
        } catch (Throwable $exception) {
            $export->update([
                'status' => ReportStatus::Failed,
                'error' => Str::limit($exception->getMessage(), 1000),
            ]);
        }

        return back()->with('success', 'Laporan berhasil dibuat.');
    }
```

In `index()`, add a `students` key to the returned Inertia props, alongside `filterOptions` (as a sibling top-level key, not nested inside `filterOptions`, since it's a different kind of picker — a single student, not a scope-narrowing filter):

```php
        return Inertia::render('reports/index', [
            'exports' => $exports,
            'table' => $table->toArray(),
            'initialType' => $initialType,
            'types' => collect(ReportType::cases())
                ->filter(fn (ReportType $type): bool => $user->can($type->permission()))
                ->map(fn (ReportType $type): array => ['value' => $type->value, 'label' => $type->label()])
                ->values(),
            'formats' => collect(ReportFormat::cases())
                ->map(fn (ReportFormat $format): array => ['value' => $format->value, 'label' => $format->label()])
                ->values(),
            'filterOptions' => [
                'departments' => $user->can('departments.viewAny')
                    ? Department::query()->orderBy('name')->get(['id', 'name'])
                    : [],
                'companies' => $user->can('companies.viewAny')
                    ? Company::query()->visibleTo($user)->orderBy('name')->get(['id', 'name'])
                    : [],
            ],
            'students' => $user->can('reports.student_summary')
                ? Placement::query()
                    ->visibleTo($user)
                    ->whereIn('status', [PlacementStatus::Active->value, PlacementStatus::Completed->value])
                    ->with('student.schoolClass')
                    ->get()
                    ->pluck('student')
                    ->unique('id')
                    ->sortBy('name')
                    ->map(fn (Student $s): array => [
                        'value' => $s->id,
                        'label' => "{$s->name} — {$s->nis} ({$s->schoolClass->name})",
                    ])
                    ->values()
                : [],
        ]);
```

- [ ] **Step 5: Add the Blade view**

Create `resources/views/reports/student-summary.blade.php`, following the same inline-style dompdf convention as `reports/pdf.blade.php`:

```blade
{{--
    PDF template rendered by dompdf. Inline styles are intentional here: dompdf
    has no Tailwind pipeline, so the "no custom CSS" UI rule does not apply to
    this print artefact.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Rekap Siswa — {{ $dataset->studentName }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 11px; color: #111; margin: 24px; }
        h1 { font-size: 18px; margin: 0 0 2px; }
        h2 { font-size: 12px; margin: 18px 0 6px; text-transform: uppercase; letter-spacing: .03em; color: #444; }
        .meta { color: #555; font-size: 10px; margin-bottom: 4px; }
        .meta span { margin-right: 14px; }
        .identity { border: 1px solid #d1d5db; border-radius: 4px; padding: 10px 14px; margin-top: 12px; }
        .identity div { margin-bottom: 3px; }
        .summary { display: table; width: 100%; }
        .summary .cell { display: table-cell; width: 25%; border: 1px solid #d1d5db; padding: 8px; text-align: center; }
        .summary .cell strong { display: block; font-size: 15px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 5px 7px; text-align: left; }
        th { background: #f3f4f6; font-size: 10px; text-transform: uppercase; letter-spacing: .03em; }
        tr:nth-child(even) td { background: #fafafa; }
        .empty { text-align: center; color: #777; padding: 12px; }
        .footer { margin-top: 18px; color: #777; font-size: 9px; }
    </style>
</head>
<body>
    <h1>Rekap Siswa</h1>
    <div class="meta">
        <span><strong>Cakupan:</strong> {{ $dataset->scope }}</span>
        <span><strong>Dibuat oleh:</strong> {{ $dataset->requestedBy }}</span>
        <span><strong>Waktu:</strong> {{ $dataset->generatedAt }}</span>
    </div>

    <div class="identity">
        <div><strong>Nama:</strong> {{ $dataset->studentName }} ({{ $dataset->nis }})</div>
        <div><strong>Kelas:</strong> {{ $dataset->className }}</div>
        <div><strong>Perusahaan:</strong> {{ $dataset->companyName }}</div>
        <div><strong>Guru Pembimbing:</strong> {{ $dataset->teacherName }}</div>
        <div><strong>Periode PKL:</strong> {{ $dataset->period }}</div>
    </div>

    <h2>Absensi</h2>
    <div class="summary">
        <div class="cell"><strong>{{ $dataset->attendancePresent }}</strong>Hadir</div>
        <div class="cell"><strong>{{ $dataset->attendancePermit }}</strong>Izin</div>
        <div class="cell"><strong>{{ $dataset->attendanceSick }}</strong>Sakit</div>
        <div class="cell"><strong>{{ $dataset->attendanceAbsent }}</strong>Alpa</div>
    </div>
    <p class="meta">Tingkat kehadiran: <strong>{{ $dataset->attendanceRate }}</strong></p>

    <h2>Jurnal</h2>
    <div class="summary">
        <div class="cell"><strong>{{ $dataset->journalTotal }}</strong>Total</div>
        <div class="cell"><strong>{{ $dataset->journalApproved }}</strong>Disetujui</div>
        <div class="cell"><strong>{{ $dataset->journalPending }}</strong>Pending</div>
        <div class="cell"><strong>{{ $dataset->journalRejected }}</strong>Ditolak</div>
    </div>

    <h2>Nilai Akhir</h2>
    @if ($dataset->finalScore === null)
        <p class="empty">Belum dinilai.</p>
    @else
        <div class="summary">
            <div class="cell"><strong>{{ $dataset->industryScore }}</strong>Nilai Industri</div>
            <div class="cell"><strong>{{ $dataset->teacherScore }}</strong>Nilai Guru</div>
            <div class="cell"><strong>{{ $dataset->finalScore }}</strong>Nilai Akhir</div>
            <div class="cell"><strong>{{ $dataset->grade }}</strong>Grade ({{ $dataset->assessmentStatus }})</div>
        </div>
    @endif

    <h2>Kunjungan Monitoring</h2>
    @if (count($dataset->monitorings) === 0)
        <p class="empty">Belum ada kunjungan.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Catatan</th>
                    <th>Rekomendasi</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($dataset->monitorings as $visit)
                    <tr>
                        <td>{{ $visit['visit_date'] }}</td>
                        <td>{{ $visit['note'] }}</td>
                        <td>{{ $visit['recommendation'] ?? '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
```

- [ ] **Step 6: Add the sidebar shortcut**

In `app/Support/SidebarBuilder.php`, add to the `'Laporan'` group (after the `'Monitoring'` item, line 59):

```php
                $this->item('Rekap Siswa', 'IdCard', 'reports.student_summary', route: 'reports.index', query: ['type' => 'student_summary']),
```

- [ ] **Step 7: Run the tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/Report/StudentSummaryReportTest.php`
Expected: All 8 tests (4 from Task 1 + 4 new) pass.

- [ ] **Step 8: Run the full suite**

Run: `vendor/bin/pest`
Expected: No regressions.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Requests/Report/StoreReportRequest.php app/Http/Controllers/Report/ReportController.php app/Support/SidebarBuilder.php resources/views/reports/student-summary.blade.php tests/Feature/Report/StudentSummaryReportTest.php
git commit -m "feat: wire up student-summary report generation end to end"
```

---

### Task 3: Frontend — student picker on the reports form

**Files:**
- Modify: `resources/js/pages/reports/index.tsx`

**Interfaces:**
- Consumes: `Props.students: {value: string; label: string}[]` (new prop from Task 2's `index()` change — same `SelectOption` shape already imported), `Combobox` component (`resources/js/components/shared/combobox.tsx`, existing, unchanged).
- Produces: no change to what the page exports or how routing works.

- [ ] **Step 1: Add the `students` prop and `Combobox` import**

In `resources/js/pages/reports/index.tsx`, add to the import block:

```tsx
import { Combobox } from '@/components/shared/combobox';
```

Add to `Props`:

```tsx
type Props = {
    exports: Paginated<ReportRow>;
    table: TableState;
    initialType: string | null;
    types: SelectOption[];
    formats: SelectOption[];
    filterOptions: {
        departments: { id: string; name: string }[];
        companies: { id: string; name: string }[];
    };
    students: SelectOption[];
};
```

Destructure it in the component signature:

```tsx
export default function ReportIndex({
    exports,
    table,
    initialType,
    types,
    formats,
    filterOptions,
    students,
}: Props) {
```

- [ ] **Step 2: Add `studentId` state and a derived flag**

Add alongside the existing `type`/`format`/`departmentId`/`companyId` state:

```tsx
    const [studentId, setStudentId] = useState('');

    const isStudentSummary = type === 'student_summary';
```

In the `Form`'s `onSuccess`, reset it alongside the existing resets:

```tsx
                            onSuccess={() => {
                                setDepartmentId(NONE);
                                setCompanyId(NONE);
                                setStudentId('');
                            }}
```

- [ ] **Step 3: Swap the format field for a student picker, and hide department/company filters, when `isStudentSummary`**

Replace the format `Field` block:

```tsx
                                    <input
                                        type="hidden"
                                        name="format"
                                        value={format}
                                    />
                                    <Field
                                        id="format"
                                        label="Format"
                                        error={errors.format}
                                        required
                                    >
                                        <Select
                                            value={format}
                                            onValueChange={setFormat}
                                        >
                                            <SelectTrigger id="format">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {formats.map((option) => (
                                                    <SelectItem
                                                        key={option.value}
                                                        value={option.value}
                                                    >
                                                        {option.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </Field>
```

with:

```tsx
                                    {isStudentSummary ? (
                                        <>
                                            <input
                                                type="hidden"
                                                name="format"
                                                value="pdf"
                                            />
                                            <input
                                                type="hidden"
                                                name="student_id"
                                                value={studentId}
                                            />
                                            <Field
                                                id="student_id"
                                                label="Siswa"
                                                error={errors.student_id}
                                                required
                                            >
                                                <Combobox
                                                    id="student_id"
                                                    value={studentId}
                                                    onValueChange={setStudentId}
                                                    options={students}
                                                    placeholder="Pilih siswa"
                                                    searchPlaceholder="Cari nama/NIS..."
                                                    emptyText="Tidak ada siswa."
                                                    aria-invalid={!!errors.student_id}
                                                />
                                            </Field>
                                        </>
                                    ) : (
                                        <>
                                            <input
                                                type="hidden"
                                                name="format"
                                                value={format}
                                            />
                                            <Field
                                                id="format"
                                                label="Format"
                                                error={errors.format}
                                                required
                                            >
                                                <Select
                                                    value={format}
                                                    onValueChange={setFormat}
                                                >
                                                    <SelectTrigger id="format">
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {formats.map((option) => (
                                                            <SelectItem
                                                                key={option.value}
                                                                value={option.value}
                                                            >
                                                                {option.label}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </Field>
                                        </>
                                    )}
```

Wrap the existing department `Field` block's outer condition to also require `!isStudentSummary`:

```tsx
                                    {!isStudentSummary && filterOptions.departments.length > 0 ? (
```

Do the same for the company block:

```tsx
                                    {!isStudentSummary && filterOptions.companies.length > 0 ? (
```

- [ ] **Step 4: Update the submit button's disabled condition**

```tsx
                                    <Button
                                        type="submit"
                                        disabled={
                                            processing ||
                                            type === '' ||
                                            (isStudentSummary && studentId === '')
                                        }
                                    >
```

- [ ] **Step 5: Type-check the frontend**

Run: `npm run build`
Expected: Build succeeds with no TypeScript errors.

- [ ] **Step 6: Manual smoke check**

Use the `verify` skill (or `run` skill) to log in as a seeded Admin PKL or Guru Pembimbing user, open `/reports`, select "Rekap Siswa" in the type dropdown, confirm the format `Select` is replaced by the searchable student `Combobox`, submit, and confirm the export row appears `completed` with a working download link.

- [ ] **Step 7: Commit**

```bash
git add resources/js/pages/reports/index.tsx
git commit -m "feat: add student picker to the reports form for Rekap Siswa"
```
