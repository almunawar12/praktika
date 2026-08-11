# Rekap Siswa (Per-Student Combined Report)

## Problem

The Reports area (`/reports`) only produces org-wide recaps (attendance, journal, assessment, monitoring), each a flat table across many placements. There's no single document combining everything for one student — e.g. a guru pembimbing or admin wanting a printable transcript-style summary of one student's whole PKL (attendance rate, journal counts, final score, monitoring visits) has to generate four separate reports and cross-reference them by hand.

## Decision

Add a fifth report type, `student_summary` ("Rekap Siswa"), scoped to exactly one student's most recent Active/Completed placement. Unlike the other four types it isn't a flat table — it's a PDF-only, multi-section document (identity header, attendance summary, journal summary, final score, monitoring visit list). It reuses the same `/reports` create form, `ReportExport` row, and sync-generation flow from the recent sync-report-generation change — only the dataset shape and renderer differ for this one type.

## Scope & permissions

- New permission `reports.student_summary`, granted to `AdminPkl` and `GuruPembimbing` only. `PembimbingIndustri` doesn't get it: they hold `reports.attendance`/`reports.journal` but not `reports.assessment`, and this combined report always includes the final score section — granting it would leak assessment data they can't otherwise see.
- The student a report is generated for must resolve to a `Placement` visible to the requester (`Placement::visibleTo`), status `Active` or `Completed`, most recent by `start_date`. A guru pembimbing can therefore only generate this for their own students, same as every other report type. If no such placement exists, the export lands `failed` with a clear message rather than a 500.
- `date_from`/`date_to` (already on the form) scope the attendance/journal/monitoring sections the same way they scope the existing tabular reports. They don't affect the identity header or the final score (a final score has no date range).

## Data shape

New DTO `App\DataTransferObjects\StudentSummaryDataset` (readonly, mirrors the existing `ReportDataset` role: format-agnostic, built once by the service, consumed by the Blade view):

```php
final readonly class StudentSummaryDataset
{
    public function __construct(
        public string $studentName,
        public string $nis,
        public string $className,
        public string $companyName,
        public string $teacherName,
        public string $period,          // "dd/mm/yyyy – dd/mm/yyyy", from the placement's own start/end
        public string $scope,           // date_from/date_to filter description, same wording as ReportService::describeScope
        public int $attendancePresent,
        public int $attendancePermit,
        public int $attendanceSick,
        public int $attendanceAbsent,
        public string $attendanceRate,  // "87.5%", pre-formatted like the existing attendance report
        public int $journalTotal,
        public int $journalApproved,
        public int $journalPending,
        public int $journalRejected,
        public ?string $industryScore,  // number_format'd, null when unscored — mirrors ReportService::assessmentRows
        public ?string $teacherScore,
        public ?string $finalScore,
        public ?string $grade,
        public string $assessmentStatus, // 'Belum dinilai' | 'Draft' | 'Dipublikasikan'
        public array $monitorings,       // list<array{visit_date: string, note: string, recommendation: ?string}>
        public string $requestedBy,
        public string $generatedAt,
    ) {}
}
```

## Changes

**`app/Enums/ReportType.php`**
Add `case StudentSummary = 'student_summary';`. `label()` → `'Rekap Siswa'`. `permission()` already derives from the value (`reports.student_summary`) — no change needed there. `columns()` gets a `self::StudentSummary => []` arm (never read for this type, since it doesn't go through the tabular `ReportDataset` path, but the match must stay exhaustive).

**`app/Support/PermissionCatalog.php`**
Add `'student_summary'` to the `reports` resource's action list (line 47). Add `'reports.student_summary'` to `adminPkl()` and `guruPembimbing()`'s permission lists. Do not add it to `pembimbingIndustri()`.

**`app/DataTransferObjects/StudentSummaryDataset.php`** (new)
As shown above.

**`app/Services/ReportService.php`**
Add `buildStudentSummary(ReportExport $export): StudentSummaryDataset`. Resolves the placement (`visibleTo` + status filter + `student_id` from `$export->filters['student_id']`, throwing `RuntimeException` if absent/not found — the controller's existing catch turns that into a `failed` row), then assembles the DTO using the same counting logic already in `attendanceRows`/`journalRows`/`assessmentRows`, narrowed to one placement instead of a collection. The existing `applyDateRange`/`filter` private helpers are reused as-is.

**`app/Http/Requests/Report/StoreReportRequest.php`**
Add `'student_id' => ['required_if:type,student_summary', 'uuid', Rule::exists('students', 'id')]` to `rules()`. Leave the existing `format` rule untouched — the controller forces `format` to `pdf` server-side for `student_summary` regardless of what's submitted (see below), so no extra validation rule is needed for it. Add `'student_id'` to `filters()`'s returned array alongside the existing four keys.

**`app/Http/Controllers/Report/ReportController.php`**
- `index()`: add a `'students'` key to the Inertia props — the distinct students behind placements visible to the user, but only computed when `$user->can('reports.student_summary')` (mirrors the existing `departments`/`companies` conditional pattern). Query: `Placement::query()->visibleTo($user)->whereIn('status', [...])->with('student:id,name,nis')->get()->pluck('student')->unique('id')->values()`.
- `store()`: branch on `$export->type`. For `ReportType::StudentSummary`, call `$service->buildStudentSummary($export)`, force `$export->format` to `ReportFormat::Pdf` before saving (ignore whatever format was submitted), render with a new `reports.student-summary` Blade view instead of the existing `reports.pdf`/`ReportSpreadsheet` branch. All other types keep today's path unchanged.

**`resources/views/reports/student-summary.blade.php`** (new)
Same inline-style dompdf convention as `reports/pdf.blade.php` (no Tailwind pipeline available to dompdf). Sections: header (name/NIS/class/company/teacher/period), a small key-value summary block for attendance, one for journal, one for the final score, then a `visit_date | note | recommendation` table for monitorings (or an empty-state line if none).

**`resources/js/pages/reports/index.tsx`**
- Add `students: SelectOption[]` (well, `{id,name,nis}[]` like the existing `filterOptions.departments`/`companies` shape) to `Props`.
- When `type === 'student_summary'`: render a `Combobox` (from `@/components/shared/combobox`) bound to a new `studentId` state, submitting as a hidden `student_id` input; hide the format `Select` and instead render a fixed hidden `format` input value `"pdf"`.
- Otherwise: today's format `Select` + department/company filters render exactly as now (student_summary doesn't use department/company filters).

## Testing

`tests/Feature/Report/ReportTest.php` (or a new `tests/Feature/Report/StudentSummaryReportTest.php`, cleaner given the divergent setup): cover — generating a summary produces a `completed` export with a PDF `file_path`; a guru pembimbing cannot generate a summary for a student outside their placements (`failed`, or a 403 if resolved at the request-validation layer — matches how other cross-scope attempts already behave in the suite); a supervisor (`PembimbingIndustri`) gets `403` on `POST /reports` with `type=student_summary` since they lack the permission; a student with no Active/Completed placement produces a `failed` export with a descriptive `error`; the assessment section reads `null`/`'Belum dinilai'` correctly when unscored.
