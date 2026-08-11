# Laporan Live (Filter, Bukan Generate) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ubah modul Laporan dari alur *generate lalu unduh* menjadi tampilan langsung: pilih tipe lewat sidebar, setel filter, tabel (atau kartu rekap siswa) tampil di layar, dan unduhan menstreaming berkas sesuai filter aktif tanpa menyimpan apa pun.

**Architecture:** `ReportFilters` (readonly DTO dari query string) menggantikan model `ReportExport` sebagai sumber filter. `ReportService` mendapat tiga entry point — `paginate()` untuk layar, `dataset()` untuk unduhan tabel, `studentSummary()` untuk rekap siswa — yang berbagi satu row-builder. Route menjadi satu per tipe (`/reports/{type}`) dengan enum binding, diotorisasi `ReportPolicy` yang didaftarkan ke `ReportType` lewat `Gate::policy()`. Tabel `report_exports` beserta file tersimpannya di-drop.

**Tech Stack:** Laravel 13, PHP 8.3, PostgreSQL 17, Inertia + React 19 + TypeScript, Tailwind v4, Shadcn UI, Pest, Pint, Larastan, Wayfinder, dompdf, maatwebsite/excel, spatie/laravel-activitylog, spatie/laravel-permission.

**Spec:** `docs/superpowers/specs/2026-08-11-live-reports-design.md`

## Global Constraints

- Semua file PHP baru diawali `declare(strict_types=1);` dan mengikuti PSR-12 / Laravel Pint.
- Controller tetap tipis: logika kueri hidup di `ReportService`, normalisasi filter di `ReportFilters`, validasi di Form Request.
- Setiap route diotorisasi lewat Policy. Tidak ada `Gate::before()` bypass Super Admin di proyek ini.
- Pembatasan data selalu lewat `Placement::visibleTo($user)`. Tidak boleh ada kueri laporan yang melewatinya.
- Paginasi default 15 baris per halaman (`TableRequest::DEFAULT_PER_PAGE`).
- Eager loading wajib; tidak boleh ada N+1.
- Teks UI berbahasa Indonesia.
- Migrasi harus reversibel (`down()` mengembalikan skema).
- Perintah verifikasi: `composer lint` (Pint), `composer types:check` (phpstan), `php artisan test`, `npm run types:check`, `npm run lint:check`, `npm run format:check`, `npm run build`.
- Setiap task diakhiri commit dengan pesan Conventional Commits.

## File Structure

**Dibuat:**

| File | Tanggung jawab |
| --- | --- |
| `app/DataTransferObjects/ReportFilters.php` | Normalisasi + default filter laporan dari query string, deskripsi cakupan |
| `app/Policies/ReportPolicy.php` | Otorisasi area laporan dan per-tipe |
| `app/Http/Requests/Report/ReportFilterRequest.php` | Validasi query string filter |
| `database/migrations/2026_08_12_000001_drop_report_exports_table.php` | Drop tabel + direktori file laporan |
| `resources/js/pages/reports/show.tsx` | Halaman satu tipe laporan |
| `resources/js/components/reports/report-filter-bar.tsx` | Kontrol filter per tipe |
| `resources/js/components/reports/report-table.tsx` | Tabel generik dari `columns` + `rows` |
| `resources/js/components/reports/student-summary-card.tsx` | Kartu rekap satu siswa |
| `tests/Feature/Report/ReportFiltersTest.php` | Tes DTO filter |
| `tests/Feature/Report/ReportPolicyTest.php` | Tes policy |

**Diubah:**

| File | Perubahan |
| --- | --- |
| `app/Services/ReportService.php` | Ganti `ReportExport` → `ReportFilters` + `User`; tambah `paginate()`, `dataset()`, `studentSummary()` publik; filter kelas/guru/cari; urut nama di SQL |
| `app/Http/Controllers/Report/ReportController.php` | `index()` redirect, `show()`, `download()`; hapus `store()`/`destroy()` |
| `routes/reports.php` | Route read-only per tipe |
| `app/Providers/AppServiceProvider.php` | `Gate::policy(ReportType::class, ReportPolicy::class)` |
| `app/Support/SidebarBuilder.php` | Item laporan menunjuk `reports.show` dengan param tipe |
| `tests/Feature/Report/ReportTest.php` | Ditulis ulang untuk alur baru |
| `tests/Feature/Report/StudentSummaryReportTest.php` | Ditulis ulang untuk alur baru |

**Dihapus:** `app/Models/ReportExport.php`, `app/Policies/ReportExportPolicy.php`, `app/Http/Requests/Report/StoreReportRequest.php`, `app/Enums/ReportStatus.php`, `database/factories/ReportExportFactory.php`, `resources/js/pages/reports/index.tsx`.

**Tidak berubah:** `app/Enums/ReportType.php`, `app/Enums/ReportFormat.php`, `app/DataTransferObjects/ReportDataset.php`, `app/DataTransferObjects/StudentSummaryDataset.php`, `app/Exports/ReportSpreadsheet.php`, `resources/views/reports/*.blade.php`.

---

### Task 1: `ReportFilters` DTO

Menormalkan filter laporan dari query string, mengisi default rentang tahun ajaran aktif, dan menyediakan deskripsi cakupan untuk header PDF/Excel.

**Files:**
- Create: `app/DataTransferObjects/ReportFilters.php`
- Test: `tests/Feature/Report/ReportFiltersTest.php`

**Interfaces:**
- Consumes: `App\Enums\ReportType` (sudah ada), `App\Models\AcademicYear` (sudah ada, punya `start_date` dan `end_date` bertipe date immutable).
- Produces:
  - `ReportFilters::fromRequest(Illuminate\Http\Request $request, ReportType $type): ReportFilters`
  - Properti publik readonly: `?string $dateFrom`, `?string $dateTo`, `?string $departmentId`, `?string $companyId`, `?string $schoolClassId`, `?string $teacherId`, `?string $search`, `?string $studentId`, `int $perPage`
  - `ReportFilters::describeScope(): string`
  - `ReportFilters::toArray(): array<string, mixed>`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Report/ReportFiltersTest.php`:

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Report/ReportFiltersTest.php`
Expected: FAIL — `Class "App\DataTransferObjects\ReportFilters" not found`.

- [ ] **Step 3: Write the DTO**

Create `app/DataTransferObjects/ReportFilters.php`:

```php
<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Enums\ReportType;
use App\Models\AcademicYear;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * The scope a report is being read at, taken straight from the URL.
 *
 * A report is no longer an artefact that gets stored; it is a query. This is
 * that query's parameters, normalised once so the service, the download, and
 * the activity log all read the same values.
 */
final readonly class ReportFilters
{
    private const DEFAULT_PER_PAGE = 15;

    private const MIN_PER_PAGE = 5;

    private const MAX_PER_PAGE = 100;

    public function __construct(
        public ?string $dateFrom,
        public ?string $dateTo,
        public ?string $departmentId,
        public ?string $companyId,
        public ?string $schoolClassId,
        public ?string $teacherId,
        public ?string $search,
        public ?string $studentId,
        public int $perPage,
    ) {}

    public static function fromRequest(Request $request, ReportType $type): self
    {
        $from = self::text($request, 'date_from');
        $to = self::text($request, 'date_to');

        // Only when the user has said nothing about the period: an explicit
        // half-open range is a deliberate choice and must not be widened.
        if ($from === null && $to === null) {
            $year = AcademicYear::query()->where('is_active', true)->first();

            if ($year !== null) {
                $from = $year->start_date->toDateString();
                $to = $year->end_date->toDateString();
            }
        }

        return new self(
            dateFrom: $from,
            dateTo: $to,
            departmentId: self::text($request, 'department_id'),
            companyId: self::text($request, 'company_id'),
            schoolClassId: self::text($request, 'school_class_id'),
            teacherId: self::text($request, 'teacher_id'),
            search: self::text($request, 'search'),
            // Only Rekap Siswa is about a single student; elsewhere the key is noise.
            studentId: $type === ReportType::StudentSummary ? self::text($request, 'student_id') : null,
            perPage: max(self::MIN_PER_PAGE, min(self::MAX_PER_PAGE, $request->integer('per_page', self::DEFAULT_PER_PAGE))),
        );
    }

    /**
     * The period in words, printed on the PDF and the spreadsheet header.
     */
    public function describeScope(): string
    {
        if ($this->dateFrom !== null && $this->dateTo !== null) {
            return 'Periode '.CarbonImmutable::parse($this->dateFrom)->format('d/m/Y')
                .' – '.CarbonImmutable::parse($this->dateTo)->format('d/m/Y');
        }

        if ($this->dateFrom !== null) {
            return 'Sejak '.CarbonImmutable::parse($this->dateFrom)->format('d/m/Y');
        }

        if ($this->dateTo !== null) {
            return 'Sampai '.CarbonImmutable::parse($this->dateTo)->format('d/m/Y');
        }

        return 'Seluruh periode';
    }

    /**
     * Echoed back to the frontend so the filter bar can render its current state,
     * and recorded on the activity log entry for a download.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'department_id' => $this->departmentId,
            'company_id' => $this->companyId,
            'school_class_id' => $this->schoolClassId,
            'teacher_id' => $this->teacherId,
            'search' => $this->search,
            'student_id' => $this->studentId,
            'per_page' => $this->perPage,
        ];
    }

    /**
     * `all` is what the shared FilterSelect submits for "no filter"; treat it,
     * blanks, and whitespace the same way.
     */
    private static function text(Request $request, string $key): ?string
    {
        $value = trim((string) $request->string($key));

        return $value === '' || $value === 'all' ? null : $value;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Report/ReportFiltersTest.php`
Expected: PASS (8 tests).

- [ ] **Step 5: Lint and static analysis**

Run: `composer lint && composer types:check`
Expected: Pint melaporkan tidak ada masalah (atau memperbaiki format), phpstan `[OK] No errors`.

- [ ] **Step 6: Commit**

```bash
git add app/DataTransferObjects/ReportFilters.php tests/Feature/Report/ReportFiltersTest.php
git commit -m "feat: add ReportFilters DTO for live report scoping"
```

---

### Task 2: `ReportPolicy` untuk area dan tipe laporan

Otorisasi berpindah dari kepemilikan baris `report_exports` ke izin per tipe. Policy dipasang pada enum `ReportType`, sehingga `authorize('view', $type)` bekerja seperti biasa.

**Files:**
- Create: `app/Policies/ReportPolicy.php`
- Modify: `app/Providers/AppServiceProvider.php` (method `configureAuthorization()`)
- Test: `tests/Feature/Report/ReportPolicyTest.php`

**Interfaces:**
- Consumes: `ReportType::permission()` (sudah ada, mengembalikan `reports.{value}`), helper tes `userWithRole(RoleName)` (sudah ada di `tests/Pest.php`).
- Produces:
  - `ReportPolicy::viewAny(User $user): bool`
  - `ReportPolicy::view(User $user, ReportType $type): bool`
  - Gate mapping `ReportType::class => ReportPolicy::class`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Report/ReportPolicyTest.php`:

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Report/ReportPolicyTest.php`
Expected: FAIL — tidak ada policy terdaftar untuk `ReportType`, sehingga `allows()` mengembalikan `false` untuk admin.

- [ ] **Step 3: Write the policy**

Create `app/Policies/ReportPolicy.php`:

```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ReportType;
use App\Models\User;

/**
 * Reports are read, not owned.
 *
 * There is no stored artefact to guard any more, so authorization is only about
 * which kinds of recap a user may open. What data appears inside one is decided
 * separately by Placement::visibleTo().
 */
class ReportPolicy
{
    /**
     * The reports area is reachable by anyone holding at least one report
     * permission. A student, who holds none, never sees it.
     */
    public function viewAny(User $user): bool
    {
        foreach (ReportType::cases() as $type) {
            if ($user->can($type->permission())) {
                return true;
            }
        }

        return false;
    }

    public function view(User $user, ReportType $type): bool
    {
        return $user->can($type->permission());
    }
}
```

- [ ] **Step 4: Register the policy on the enum**

Modify `app/Providers/AppServiceProvider.php` — isi `configureAuthorization()` (biarkan komentar yang sudah ada di atasnya) dan tambahkan `use` yang diperlukan:

```php
use App\Enums\ReportType;
use App\Policies\ReportPolicy;
use Illuminate\Support\Facades\Gate;
```

```php
    protected function configureAuthorization(): void
    {
        // A report has no model, so the enum itself is the policy target: Gate
        // resolves a policy from the class of the object handed to authorize().
        Gate::policy(ReportType::class, ReportPolicy::class);
    }
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Report/ReportPolicyTest.php`
Expected: PASS (3 tests).

- [ ] **Step 6: Lint and static analysis**

Run: `composer lint && composer types:check`
Expected: bersih.

- [ ] **Step 7: Commit**

```bash
git add app/Policies/ReportPolicy.php app/Providers/AppServiceProvider.php tests/Feature/Report/ReportPolicyTest.php
git commit -m "feat: authorize reports per type via ReportPolicy on the enum"
```

---

### Task 3: `ReportService` membaca filter, bukan export

Inti perubahan. Service berhenti bergantung pada model `ReportExport`, mendapat paginasi di level basis data, urutan nama siswa di SQL, dan tiga filter baru.

**Files:**
- Modify: `app/Services/ReportService.php`
- Test: `tests/Feature/Report/ReportServiceTest.php` (create)

**Interfaces:**
- Consumes: `ReportFilters` (Task 1), `Placement::visibleTo()` (sudah ada), `ReportDataset` dan `StudentSummaryDataset` (sudah ada, bentuknya tidak berubah).
- Produces:
  - `ReportService::paginate(ReportType $type, ReportFilters $filters, User $user): LengthAwarePaginator` — item berbentuk `array{key: string, cells: array<int, string|int|float>}`
  - `ReportService::dataset(ReportType $type, ReportFilters $filters, User $user): ReportDataset`
  - `ReportService::studentSummary(ReportFilters $filters, User $user): ?StudentSummaryDataset`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Report/ReportServiceTest.php`:

```php
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

beforeEach(function (): void {
    $this->service = app(ReportService::class);
    $this->admin = userWithRole(RoleName::AdminPkl);
    $this->company = Company::factory()->create(['quota' => 10]);
    $this->teacher = Teacher::factory()->create();
});

function placementFor(string $name, string $nis, Company $company, Teacher $teacher, ?SchoolClass $class = null): Placement
{
    $student = Student::factory()->create(array_filter([
        'name' => $name,
        'nis' => $nis,
        'school_class_id' => $class?->id,
    ]));

    return Placement::factory()->active()->create([
        'student_id' => $student->id,
        'company_id' => $company->id,
        'teacher_id' => $teacher->id,
    ]);
}

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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Report/ReportServiceTest.php`
Expected: FAIL — `Call to undefined method App\Services\ReportService::paginate()`.

- [ ] **Step 3: Rewrite the service signatures and query**

Modify `app/Services/ReportService.php`.

Ganti blok `use`:

```php
use App\DataTransferObjects\ReportDataset;
use App\DataTransferObjects\ReportFilters;
use App\DataTransferObjects\StudentSummaryDataset;
use App\Enums\AttendanceStatus;
use App\Enums\PlacementStatus;
use App\Enums\ReportType;
use App\Enums\ReviewStatus;
use App\Enums\VerificationStatus;
use App\Models\Placement;
use App\Models\Student;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
```

Ganti `build()` dan tambahkan entry point baru di bagian atas kelas:

```php
    /**
     * One page of a report, aggregated only over the placements on that page.
     *
     * @return LengthAwarePaginator<int, array{key: string, cells: array<int, string|int|float>}>
     */
    public function paginate(ReportType $type, ReportFilters $filters, User $user, int $page = 1): LengthAwarePaginator
    {
        $paginator = $this->placementQuery($filters, $user)
            ->paginate(perPage: $filters->perPage, page: $page)
            ->withQueryString();

        /** @var Collection<int, Placement> $placements */
        $placements = new Collection($paginator->items());

        return $paginator->setCollection(collect($this->rows($type, $placements, $filters)));
    }

    /**
     * The whole report, for a download. Unlike paginate() this is never trimmed:
     * a file the user asked for must contain every row their filters describe.
     */
    public function dataset(ReportType $type, ReportFilters $filters, User $user): ReportDataset
    {
        $placements = $this->placementQuery($filters, $user)->get();

        return new ReportDataset(
            type: $type,
            title: $type->label(),
            scope: $filters->describeScope(),
            columns: $type->columns(),
            rows: array_column($this->rows($type, $placements, $filters), 'cells'),
            requestedBy: $user->name,
            generatedAt: CarbonImmutable::now()->format('d/m/Y H:i'),
        );
    }

    /**
     * @param  Collection<int, Placement>  $placements
     * @return array<int, array{key: string, cells: array<int, string|int|float>}>
     */
    private function rows(ReportType $type, Collection $placements, ReportFilters $filters): array
    {
        return match ($type) {
            ReportType::Attendance => $this->attendanceRows($placements, $filters),
            ReportType::Journal => $this->journalRows($placements, $filters),
            ReportType::Assessment => $this->assessmentRows($placements),
            ReportType::Monitoring => $this->monitoringRows($placements, $filters),
            // Rendered as a card, not a table — see studentSummary().
            ReportType::StudentSummary => [],
        };
    }

    /**
     * Placements the user may see, narrowed by the filters.
     *
     * Draft and cancelled placements are left out: a report is about PKL that is
     * happening or has happened, not proposals. Ordering is a correlated
     * subquery rather than a join, so visibleTo()'s unqualified columns stay
     * unambiguous.
     *
     * @return EloquentBuilder<Placement>
     */
    private function placementQuery(ReportFilters $filters, User $user): EloquentBuilder
    {
        return Placement::query()
            ->visibleTo($user)
            ->whereIn('status', [PlacementStatus::Active->value, PlacementStatus::Completed->value])
            ->when($filters->companyId, fn (EloquentBuilder $query, string $id) => $query->where('company_id', $id))
            ->when($filters->teacherId, fn (EloquentBuilder $query, string $id) => $query->where('teacher_id', $id))
            ->when($filters->schoolClassId, fn (EloquentBuilder $query, string $id) => $query
                ->whereHas('student', fn ($student) => $student->where('school_class_id', $id)))
            ->when($filters->departmentId, fn (EloquentBuilder $query, string $id) => $query
                ->whereHas('student.schoolClass', fn ($class) => $class->where('department_id', $id)))
            ->when($filters->search, fn (EloquentBuilder $query, string $search) => $query
                ->whereHas('student', fn ($student) => $student
                    ->where('name', 'ilike', "%{$search}%")
                    ->orWhere('nis', 'ilike', "%{$search}%")))
            ->with([
                'student:id,name,nis',
                'company:id,name',
                'teacher:id,name',
            ])
            ->orderBy(Student::query()->select('name')->whereColumn('students.id', 'placements.student_id'));
    }
```

Hapus method `build()` dan `scopedPlacements()` yang lama.

- [ ] **Step 4: Swap `ReportExport` for `ReportFilters` in the row builders**

Masih di `app/Services/ReportService.php`, ubah tanda tangan dan isi berikut:

```php
    private function applyDateRange(Builder $query, ReportFilters $filters, string $column): void
    {
        if ($filters->dateFrom !== null) {
            $query->whereDate($column, '>=', $filters->dateFrom);
        }

        if ($filters->dateTo !== null) {
            $query->whereDate($column, '<=', $filters->dateTo);
        }
    }

    /**
     * @return array<string, array<string, int>>
     */
    private function countByStatus(Builder $query, ReportFilters $filters, string $dateColumn): array
    {
        // unchanged body, but calls $this->applyDateRange($query, $filters, $dateColumn)
    }
```

Hapus method `filter()` dan `describeScope()` — keduanya pindah ke `ReportFilters`.

Setiap row-builder berganti parameter kedua dari `ReportExport $export` menjadi `ReportFilters $filters`, dan mengembalikan baris berkunci. Contoh lengkap untuk `attendanceRows()`; terapkan pola `key`/`cells` yang sama pada `journalRows()`, `assessmentRows()`, dan `monitoringRows()` tanpa mengubah isi selnya:

```php
    /**
     * @param  Collection<int, Placement>  $placements
     * @return array<int, array{key: string, cells: array<int, string|int|float>}>
     */
    private function attendanceRows(Collection $placements, ReportFilters $filters): array
    {
        $counts = $this->countByStatus(
            DB::table('attendances')
                ->where('verification_status', '!=', VerificationStatus::Rejected->value),
            $filters,
            'date',
        );

        return $placements->map(function (Placement $placement) use ($counts): array {
            $row = $counts[$placement->id] ?? [];

            $present = $row[AttendanceStatus::Present->value] ?? 0;
            $permit = $row[AttendanceStatus::Permit->value] ?? 0;
            $sick = $row[AttendanceStatus::Sick->value] ?? 0;
            $absent = $row[AttendanceStatus::Absent->value] ?? 0;
            $total = $present + $permit + $sick + $absent;

            $rate = $total === 0 ? 0.0 : round($present / $total * 100, 1);

            return [
                'key' => $placement->id,
                'cells' => [
                    $placement->student->name,
                    $placement->student->nis,
                    $placement->company->name,
                    $present,
                    $permit,
                    $sick,
                    $absent,
                    "{$rate}%",
                ],
            ];
        })->all();
    }
```

`assessmentRows()` tetap menerima hanya `Collection $placements` (tidak memakai rentang tanggal), namun ikut mengembalikan bentuk `key`/`cells`. `monitoringRows()` tetap membatasi agregatnya dengan `whereIn('placement_id', $placements->pluck('id'))`, yang kini otomatis berarti "hanya placement di halaman ini".

- [ ] **Step 5: Rewrite `buildStudentSummary()` as `studentSummary()`**

Masih di file yang sama:

```php
    /**
     * One student's whole PKL, or null when no student is chosen or the chosen
     * student has no placement the user may see. Both are ordinary states of the
     * screen, so neither throws.
     */
    public function studentSummary(ReportFilters $filters, User $user): ?StudentSummaryDataset
    {
        if ($filters->studentId === null) {
            return null;
        }

        $placement = Placement::query()
            ->visibleTo($user)
            ->whereIn('status', [PlacementStatus::Active->value, PlacementStatus::Completed->value])
            ->where('student_id', $filters->studentId)
            ->with(['student.schoolClass', 'company', 'teacher', 'finalScore'])
            ->latest('start_date')
            ->first();

        if ($placement === null) {
            return null;
        }

        $attendance = $this->studentAttendanceStats($placement, $filters);
        $journal = $this->studentJournalStats($placement, $filters);
        $score = $placement->finalScore;

        return new StudentSummaryDataset(
            // ...seluruh argumen sama seperti sebelumnya, kecuali:
            scope: $filters->describeScope(),
            monitorings: $this->studentMonitoringVisits($placement, $filters),
            requestedBy: $user->name,
            generatedAt: CarbonImmutable::now()->format('d/m/Y H:i'),
        );
    }
```

`studentAttendanceStats()`, `studentJournalStats()`, dan `studentMonitoringVisits()` berganti parameter kedua dari `ReportExport $export` menjadi `ReportFilters $filters`; isinya tidak berubah selain memanggil `applyDateRange($query, $filters, …)`.

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Report/ReportServiceTest.php`
Expected: PASS.

Jika `paginate()` gagal karena `setCollection()` menolak tipe, pastikan koleksi yang diberikan adalah `Illuminate\Support\Collection` hasil `collect(...)` — bukan `Eloquent\Collection`.

- [ ] **Step 7: Lint and static analysis**

Run: `composer lint && composer types:check`
Expected: bersih. Larastan akan mengeluh jika anotasi generic `LengthAwarePaginator` tidak cocok — sesuaikan anotasi, bukan tipenya.

- [ ] **Step 8: Commit**

```bash
git add app/Services/ReportService.php tests/Feature/Report/ReportServiceTest.php
git commit -m "refactor: build reports from filters with database-level pagination"
```

---

### Task 4: Controller, request, route, dan audit unduhan

Menghubungkan service baru ke HTTP: satu route per tipe, filter tervalidasi, unduhan yang menstreaming tanpa menyimpan berkas.

**Files:**
- Create: `app/Http/Requests/Report/ReportFilterRequest.php`
- Modify: `app/Http/Controllers/Report/ReportController.php`, `routes/reports.php`
- Test: `tests/Feature/Report/ReportTest.php` (tulis ulang penuh)

**Interfaces:**
- Consumes: `ReportFilters::fromRequest()` (Task 1), `ReportPolicy` (Task 2), `ReportService::paginate()/dataset()/studentSummary()` (Task 3), `ReportFormat` (sudah ada), `ReportSpreadsheet` (sudah ada), Blade `reports.pdf` dan `reports.student-summary` (sudah ada).
- Produces:
  - Route `reports.index` (GET `/reports`), `reports.show` (GET `/reports/{type}`), `reports.download` (GET `/reports/{type}/download`)
  - Inertia page `reports/show` dengan props: `type`, `columns`, `rows` (paginator), `table`, `filters`, `filterOptions`, `summary`, `scope`, `formats`
  - Entri activity log dengan nama log `report` dan deskripsi `report.download`

- [ ] **Step 1: Write the failing test**

Ganti seluruh isi `tests/Feature/Report/ReportTest.php`:

```php
<?php

declare(strict_types=1);

use App\Enums\AttendanceStatus;
use App\Enums\ReportFormat;
use App\Enums\RoleName;
use App\Enums\VerificationStatus;
use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Company;
use App\Models\CompanySupervisor;
use App\Models\Placement;
use App\Models\Student;
use App\Models\Teacher;

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
    $this->actingAs(userWithRole(RoleName::Siswa))
        ->get('/reports')
        ->assertForbidden();

    $this->actingAs(userWithRole(RoleName::Siswa))
        ->get('/reports/attendance')
        ->assertForbidden();
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
    expect(app(App\Services\ReportService::class)->dataset(
        App\Enums\ReportType::Attendance,
        App\DataTransferObjects\ReportFilters::fromRequest(
            Illuminate\Http\Request::create('/reports/attendance'),
            App\Enums\ReportType::Attendance,
        ),
        $admin,
    )->rows)->toHaveCount(21);
});

test('downloading a report the user may not open is forbidden', function (): void {
    $this->actingAs(userWithRole(RoleName::PembimbingIndustri))
        ->get('/reports/assessment/download?format=pdf')
        ->assertForbidden();
});

test('the old generate and delete routes are gone', function (): void {
    $admin = userWithRole(RoleName::AdminPkl);

    $this->actingAs($admin)->post('/reports', ['type' => 'attendance', 'format' => 'pdf'])
        ->assertStatus(405);
});
```

Catatan: kalau `App\Models\Activity` tidak ada di proyek, pakai `Spatie\Activitylog\Models\Activity` — periksa `app/Models/Activity.php` sebelum menjalankan test dan sesuaikan import.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Report/ReportTest.php`
Expected: FAIL — `/reports/attendance` belum ada route-nya (404).

- [ ] **Step 3: Write the form request**

Create `app/Http/Requests/Report/ReportFilterRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Report;

use App\Enums\ReportFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the query string a report is read at. Authorization lives in the
 * controller, against ReportPolicy and the bound ReportType.
 */
class ReportFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'department_id' => ['nullable', 'uuid', 'exists:departments,id'],
            'company_id' => ['nullable', 'uuid', 'exists:companies,id'],
            'school_class_id' => ['nullable', 'uuid', 'exists:school_classes,id'],
            'teacher_id' => ['nullable', 'uuid', 'exists:teachers,id'],
            'student_id' => ['nullable', 'uuid', 'exists:students,id'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'format' => ['nullable', Rule::enum(ReportFormat::class)],
        ];
    }

    /**
     * The shared FilterSelect submits `all` for "no filter"; drop those before
     * validation so they are not mistaken for ids.
     */
    protected function prepareForValidation(): void
    {
        $this->replace(array_filter(
            $this->all(),
            fn (mixed $value): bool => $value !== 'all' && $value !== '' && $value !== null,
        ));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date_to.after_or_equal' => 'Tanggal akhir tidak boleh mendahului tanggal mulai.',
        ];
    }
}
```

- [ ] **Step 4: Rewrite the controller**

Ganti seluruh isi `app/Http/Controllers/Report/ReportController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Report;

use App\DataTransferObjects\ReportFilters;
use App\DataTransferObjects\TableRequest;
use App\Enums\PlacementStatus;
use App\Enums\ReportFormat;
use App\Enums\ReportType;
use App\Exports\ReportSpreadsheet;
use App\Http\Controllers\Controller;
use App\Http\Requests\Report\ReportFilterRequest;
use App\Models\Company;
use App\Models\Department;
use App\Models\Placement;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reports are read, not generated: each type is a page whose filters are its URL.
 * Nothing is written to disk — a download re-runs the same query and streams it.
 */
class ReportController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', ReportType::class);

        $user = $request->user();

        $first = collect(ReportType::cases())
            ->first(fn (ReportType $type): bool => $user->can($type->permission()));

        return redirect()->route('reports.show', ['type' => $first->value]);
    }

    public function show(ReportFilterRequest $request, ReportType $type, ReportService $service): Response
    {
        $this->authorize('view', $type);

        $user = $request->user();
        $filters = ReportFilters::fromRequest($request, $type);

        return Inertia::render('reports/show', [
            'type' => ['value' => $type->value, 'label' => $type->label()],
            'columns' => $type->columns(),
            'scope' => $filters->describeScope(),
            'rows' => $type === ReportType::StudentSummary
                ? null
                : $service->paginate($type, $filters, $user, (int) $request->integer('page', 1)),
            'summary' => $type === ReportType::StudentSummary
                ? $service->studentSummary($filters, $user)
                : null,
            'filters' => $filters->toArray(),
            'table' => TableRequest::fromRequest($request, [
                'date_from', 'date_to', 'department_id', 'company_id',
                'school_class_id', 'teacher_id', 'student_id',
            ])->toArray(),
            'formats' => collect(ReportFormat::cases())
                ->map(fn (ReportFormat $format): array => ['value' => $format->value, 'label' => $format->label()])
                ->values(),
            'filterOptions' => $this->filterOptions($request, $type),
        ]);
    }

    public function download(ReportFilterRequest $request, ReportType $type, ReportService $service): StreamedResponse
    {
        $this->authorize('view', $type);

        $user = $request->user();
        $filters = ReportFilters::fromRequest($request, $type);

        // Rekap Siswa has no spreadsheet shape; it is always the PDF card.
        $format = $type === ReportType::StudentSummary
            ? ReportFormat::Pdf
            : ($request->enum('format', ReportFormat::class) ?? ReportFormat::Pdf);

        if ($type === ReportType::StudentSummary) {
            $summary = $service->studentSummary($filters, $user);

            abort_if($summary === null, 404, 'Siswa tidak memiliki penempatan yang dapat dilaporkan.');

            $contents = Pdf::loadView('reports.student-summary', ['dataset' => $summary])->output();
        } else {
            $dataset = $service->dataset($type, $filters, $user);

            $contents = match ($format) {
                ReportFormat::Pdf => Pdf::loadView('reports.pdf', ['dataset' => $dataset])->output(),
                ReportFormat::Xlsx => Excel::raw(new ReportSpreadsheet($dataset), ExcelWriter::XLSX),
            };
        }

        $fileName = Str::slug($type->label())
            .'-'.CarbonImmutable::now()->format('Ymd-His')
            .'.'.$format->extension();

        // No model to log against, so the download is recorded by hand — this is
        // the moment student data leaves the app.
        activity('report')
            ->causedBy($user)
            ->withProperties([
                'type' => $type->value,
                'format' => $format->value,
                'filters' => $filters->toArray(),
            ])
            ->log('report.download');

        return response()->streamDownload(
            fn () => print($contents),
            $fileName,
            ['Content-Type' => $format->contentType()],
        );
    }

    /**
     * Option lists for the filter bar, each one scoped to what the user may see.
     *
     * @return array<string, mixed>
     */
    private function filterOptions(Request $request, ReportType $type): array
    {
        $user = $request->user();

        return [
            'departments' => $user->can('departments.viewAny')
                ? Department::query()->orderBy('name')->get(['id', 'name'])
                : [],
            'classes' => $user->can('school-classes.viewAny')
                ? SchoolClass::query()->orderBy('name')->get(['id', 'name'])
                : [],
            // Scoped: a supervisor only ever sees their own company here.
            'companies' => $user->can('companies.viewAny')
                ? Company::query()->visibleTo($user)->orderBy('name')->get(['id', 'name'])
                : [],
            'teachers' => $user->can('teachers.viewAny')
                ? Teacher::query()->orderBy('name')->get(['id', 'name'])
                : [],
            'students' => $type === ReportType::StudentSummary
                ? Placement::query()
                    ->visibleTo($user)
                    ->whereIn('status', [PlacementStatus::Active->value, PlacementStatus::Completed->value])
                    ->with('student.schoolClass')
                    ->get()
                    ->pluck('student')
                    ->unique('id')
                    ->sortBy('name')
                    ->map(fn (Student $student): array => [
                        'value' => $student->id,
                        'label' => "{$student->name} — {$student->nis} ({$student->schoolClass->name})",
                    ])
                    ->values()
                : [],
        ];
    }
}
```

- [ ] **Step 5: Rewrite the routes**

Ganti seluruh isi `routes/reports.php`:

```php
<?php

declare(strict_types=1);

use App\Http\Controllers\Report\ReportController;
use Illuminate\Support\Facades\Route;

/*
| Reports. Read-only: a report is a query over data the user may already see,
| so each type is one page whose filters live in the URL. Authorization is per
| type (ReportPolicy); what appears inside is scoped by Placement::visibleTo().
*/

Route::middleware('auth')->name('reports.')->prefix('reports')->group(function (): void {
    Route::get('/', [ReportController::class, 'index'])->name('index');
    Route::get('{type}', [ReportController::class, 'show'])->name('show');
    Route::get('{type}/download', [ReportController::class, 'download'])->name('download');
});
```

Karena `{type}` terikat implisit ke enum `ReportType` lewat type-hint controller, nilai di luar enum menghasilkan 404 tanpa kode tambahan. Route `{type}/download` didaftarkan setelah `{type}` — Laravel mencocokkan berdasarkan jumlah segmen, jadi urutan ini aman.

- [ ] **Step 6: Regenerate Wayfinder output**

Run: `php artisan wayfinder:generate`
Expected: `resources/js/routes/reports/index.ts` dan `resources/js/actions/App/Http/Controllers/Report/ReportController.ts` diperbarui tanpa `store`/`destroy`.

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Report/ReportTest.php`
Expected: PASS. Test `reports/show` akan gagal merender komponen React sampai Task 5 — Inertia hanya memeriksa nama komponen di test, jadi ini tetap hijau.

- [ ] **Step 8: Lint and static analysis**

Run: `composer lint && composer types:check`
Expected: bersih.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Report/ReportController.php app/Http/Requests/Report/ReportFilterRequest.php routes/reports.php resources/js/routes resources/js/actions tests/Feature/Report/ReportTest.php
git commit -m "feat: serve reports as filtered pages with streamed downloads"
```

---

### Task 5: Layar laporan

Mengganti halaman generate + riwayat dengan satu halaman per tipe: bar filter, tabel, atau kartu rekap siswa.

**Files:**
- Create: `resources/js/pages/reports/show.tsx`, `resources/js/components/reports/report-filter-bar.tsx`, `resources/js/components/reports/report-table.tsx`, `resources/js/components/reports/student-summary-card.tsx`
- Delete: `resources/js/pages/reports/index.tsx`
- Modify: `app/Support/SidebarBuilder.php`

**Interfaces:**
- Consumes: props dari `ReportController::show()` (Task 4); komponen bersama `DataTableCard`, `FilterSelect`, `Combobox`, `Card`, `Button`, `Input`; hook `useTableQuery(baseUrl, table)`; tipe `Paginated`, `TableState`, `SelectOption`.
- Produces: komponen Inertia bernama `reports/show`; item sidebar menunjuk `reports.show`.

- [ ] **Step 1: Write the table component**

Create `resources/js/components/reports/report-table.tsx`:

```tsx
import type { Column } from '@/components/data-table/data-table';
import { DataTableCard } from '@/components/data-table/data-table-card';
import type { Paginated, TableState } from '@/types/table';

export type ReportRow = {
    key: string;
    cells: (string | number)[];
};

type ReportTableProps = {
    columns: string[];
    rows: Paginated<ReportRow>;
    table: TableState;
    search: string;
    onSearchChange: (value: string) => void;
    onPageChange: (page: number) => void;
    onPerPageChange: (value: string) => void;
    filters: React.ReactNode;
};

/**
 * One recap, rendered from the column headings the server sent. Nothing here
 * knows which report it is showing — the shape comes entirely from props.
 */
export function ReportTable({
    columns,
    rows,
    table,
    search,
    onSearchChange,
    onPageChange,
    onPerPageChange,
    filters,
}: ReportTableProps) {
    const tableColumns: Column<ReportRow>[] = columns.map((header, index) => ({
        key: String(index),
        header,
        // The first three columns identify the student; the rest are figures that
        // may fold away on a phone.
        hideOnMobile: index > 2,
        cell: (row) => row.cells[index],
    }));

    return (
        <DataTableCard
            columns={tableColumns}
            page={rows}
            state={table}
            onSort={() => undefined}
            onPageChange={onPageChange}
            onPerPageChange={onPerPageChange}
            rowKey={(row) => row.key}
            search={search}
            onSearchChange={onSearchChange}
            searchPlaceholder="Cari nama atau NIS"
            filters={filters}
            emptyMessage="Tidak ada data untuk filter ini."
        />
    );
}
```

- [ ] **Step 2: Write the filter bar**

Create `resources/js/components/reports/report-filter-bar.tsx`:

```tsx
import { FilterSelect } from '@/components/data-table/filter-select';
import { Combobox } from '@/components/shared/combobox';
import { Input } from '@/components/ui/input';
import type { SelectOption } from '@/types/table';

export type ReportFilterOptions = {
    departments: { id: string; name: string }[];
    classes: { id: string; name: string }[];
    companies: { id: string; name: string }[];
    teachers: { id: string; name: string }[];
    students: SelectOption[];
};

export type ReportFilterState = {
    date_from: string | null;
    date_to: string | null;
    department_id: string | null;
    company_id: string | null;
    school_class_id: string | null;
    teacher_id: string | null;
    student_id: string | null;
};

type ReportFilterBarProps = {
    options: ReportFilterOptions;
    filters: ReportFilterState;
    isStudentSummary: boolean;
    onFilterChange: (key: string, value: string) => void;
};

const ALL = 'all';

function toOptions(rows: { id: string; name: string }[]): SelectOption[] {
    return rows.map((row) => ({ value: row.id, label: row.name }));
}

/**
 * The whole report scope in one row of controls. Every change is a visit, so the
 * URL is always the report the user is looking at.
 */
export function ReportFilterBar({
    options,
    filters,
    isStudentSummary,
    onFilterChange,
}: ReportFilterBarProps) {
    return (
        <>
            <Input
                type="date"
                value={filters.date_from ?? ''}
                onChange={(event) =>
                    onFilterChange('date_from', event.target.value)
                }
                className="sm:w-40"
                aria-label="Tanggal mulai"
            />
            <Input
                type="date"
                value={filters.date_to ?? ''}
                onChange={(event) =>
                    onFilterChange('date_to', event.target.value)
                }
                className="sm:w-40"
                aria-label="Tanggal akhir"
            />

            {options.departments.length > 0 && (
                <FilterSelect
                    value={filters.department_id ?? ALL}
                    onValueChange={(value) =>
                        onFilterChange('department_id', value)
                    }
                    options={toOptions(options.departments)}
                    allLabel="Semua jurusan"
                    label="Filter jurusan"
                />
            )}

            {options.classes.length > 0 && (
                <FilterSelect
                    value={filters.school_class_id ?? ALL}
                    onValueChange={(value) =>
                        onFilterChange('school_class_id', value)
                    }
                    options={toOptions(options.classes)}
                    allLabel="Semua kelas"
                    label="Filter kelas"
                />
            )}

            {options.companies.length > 0 && (
                <FilterSelect
                    value={filters.company_id ?? ALL}
                    onValueChange={(value) =>
                        onFilterChange('company_id', value)
                    }
                    options={toOptions(options.companies)}
                    allLabel="Semua perusahaan"
                    label="Filter perusahaan"
                />
            )}

            {options.teachers.length > 0 && (
                <FilterSelect
                    value={filters.teacher_id ?? ALL}
                    onValueChange={(value) =>
                        onFilterChange('teacher_id', value)
                    }
                    options={toOptions(options.teachers)}
                    allLabel="Semua guru"
                    label="Filter guru pembimbing"
                />
            )}

            {isStudentSummary && (
                <Combobox
                    value={filters.student_id ?? ''}
                    onValueChange={(value) =>
                        onFilterChange('student_id', value)
                    }
                    options={options.students}
                    placeholder="Pilih siswa"
                    searchPlaceholder="Cari nama atau NIS"
                    emptyText="Siswa tidak ditemukan."
                    className="sm:w-72"
                />
            )}
        </>
    );
}
```

- [ ] **Step 3: Write the student summary card**

Create `resources/js/components/reports/student-summary-card.tsx`:

```tsx
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

export type StudentSummary = {
    studentName: string;
    nis: string;
    className: string;
    companyName: string;
    teacherName: string;
    period: string;
    attendancePresent: number;
    attendancePermit: number;
    attendanceSick: number;
    attendanceAbsent: number;
    attendanceRate: string;
    journalTotal: number;
    journalApproved: number;
    journalPending: number;
    journalRejected: number;
    industryScore: string | null;
    teacherScore: string | null;
    finalScore: string | null;
    grade: string | null;
    assessmentStatus: string;
    monitorings: {
        visit_date: string;
        note: string;
        recommendation: string | null;
    }[];
};

function Stat({ label, value }: { label: string; value: string | number }) {
    return (
        <div className="flex items-baseline justify-between gap-4 text-sm">
            <span className="text-muted-foreground">{label}</span>
            <span className="font-medium">{value}</span>
        </div>
    );
}

export function StudentSummaryCard({ summary }: { summary: StudentSummary }) {
    return (
        <div className="grid gap-4">
            <Card>
                <CardHeader>
                    <CardTitle>
                        {summary.studentName} · {summary.nis} ·{' '}
                        {summary.className}
                    </CardTitle>
                </CardHeader>
                <CardContent className="grid gap-1 text-sm text-muted-foreground">
                    <span>{summary.companyName}</span>
                    <span>Guru pembimbing: {summary.teacherName}</span>
                    <span>Periode: {summary.period}</span>
                </CardContent>
            </Card>

            <div className="grid gap-4 md:grid-cols-3">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Absensi</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-2">
                        <Stat label="Hadir" value={summary.attendancePresent} />
                        <Stat label="Izin" value={summary.attendancePermit} />
                        <Stat label="Sakit" value={summary.attendanceSick} />
                        <Stat label="Alpa" value={summary.attendanceAbsent} />
                        <Stat
                            label="Kehadiran"
                            value={summary.attendanceRate}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Jurnal</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-2">
                        <Stat label="Total" value={summary.journalTotal} />
                        <Stat
                            label="Disetujui"
                            value={summary.journalApproved}
                        />
                        <Stat label="Pending" value={summary.journalPending} />
                        <Stat
                            label="Ditolak"
                            value={summary.journalRejected}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Nilai</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-2">
                        <Stat
                            label="Industri"
                            value={summary.industryScore ?? '-'}
                        />
                        <Stat
                            label="Guru"
                            value={summary.teacherScore ?? '-'}
                        />
                        <Stat
                            label="Akhir"
                            value={summary.finalScore ?? '-'}
                        />
                        <Stat label="Grade" value={summary.grade ?? '-'} />
                        <Stat
                            label="Status"
                            value={summary.assessmentStatus}
                        />
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle className="text-base">
                        Kunjungan Monitoring
                    </CardTitle>
                </CardHeader>
                <CardContent className="grid gap-3">
                    {summary.monitorings.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Belum ada kunjungan pada periode ini.
                        </p>
                    ) : (
                        summary.monitorings.map((visit) => (
                            <div
                                key={`${visit.visit_date}-${visit.note}`}
                                className="grid gap-1 border-b pb-3 last:border-0 last:pb-0"
                            >
                                <span className="text-sm font-medium">
                                    {visit.visit_date}
                                </span>
                                <span className="text-sm text-muted-foreground">
                                    {visit.note}
                                </span>
                                {visit.recommendation !== null && (
                                    <span className="text-sm text-muted-foreground">
                                        Rekomendasi: {visit.recommendation}
                                    </span>
                                )}
                            </div>
                        ))
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
```

- [ ] **Step 4: Write the page**

Create `resources/js/pages/reports/show.tsx`:

```tsx
import { Download } from 'lucide-react';
import type { ReportFilterOptions, ReportFilterState } from '@/components/reports/report-filter-bar';
import { ReportFilterBar } from '@/components/reports/report-filter-bar';
import type { ReportRow } from '@/components/reports/report-table';
import { ReportTable } from '@/components/reports/report-table';
import type { StudentSummary } from '@/components/reports/student-summary-card';
import { StudentSummaryCard } from '@/components/reports/student-summary-card';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useTableQuery } from '@/hooks/use-table-query';
import AppLayout from '@/layouts/app-layout';
import type { Paginated, SelectOption, TableState } from '@/types/table';

type Props = {
    type: { value: string; label: string };
    columns: string[];
    scope: string;
    rows: Paginated<ReportRow> | null;
    summary: StudentSummary | null;
    filters: ReportFilterState;
    table: TableState;
    formats: SelectOption[];
    filterOptions: ReportFilterOptions;
};

export default function ReportShow({
    type,
    columns,
    scope,
    rows,
    summary,
    filters,
    table,
    formats,
    filterOptions,
}: Props) {
    const baseUrl = `/reports/${type.value}`;
    const { search, setSearch, filterBy, goToPage, setPerPage } = useTableQuery(
        baseUrl,
        table,
    );

    const isStudentSummary = type.value === 'student_summary';

    // The download repeats whatever the screen is showing, so it reuses the URL.
    const query = new URLSearchParams(
        Object.entries(filters)
            .filter(([, value]) => value !== null && value !== '')
            .map(([key, value]) => [key, String(value)]),
    );

    const downloadUrl = (format: string) => {
        const params = new URLSearchParams(query);
        params.set('format', format);

        return `${baseUrl}/download?${params.toString()}`;
    };

    const downloadButtons = (
        isStudentSummary
            ? formats.filter((format) => format.value === 'pdf')
            : formats
    ).map((format) =>
        isStudentSummary && summary === null ? (
            <Button key={format.value} variant="outline" disabled>
                <Download aria-hidden="true" />
                Unduh {format.label}
            </Button>
        ) : (
            <Button key={format.value} variant="outline" asChild>
                <a href={downloadUrl(format.value)}>
                    <Download aria-hidden="true" />
                    Unduh {format.label}
                </a>
            </Button>
        ),
    );

    return (
        <AppLayout
            title={type.label}
            description={scope}
            actions={<div className="flex flex-wrap gap-2">{downloadButtons}</div>}
        >
            <div className="grid gap-4">
                {isStudentSummary ? (
                    <>
                        <Card>
                            <CardContent className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                                <ReportFilterBar
                                    options={filterOptions}
                                    filters={filters}
                                    isStudentSummary
                                    onFilterChange={filterBy}
                                />
                            </CardContent>
                        </Card>

                        {summary === null ? (
                            <Card>
                                <CardContent className="py-10 text-center text-sm text-muted-foreground">
                                    Pilih siswa untuk melihat rekap.
                                </CardContent>
                            </Card>
                        ) : (
                            <StudentSummaryCard summary={summary} />
                        )}
                    </>
                ) : (
                    rows !== null && (
                        <ReportTable
                            columns={columns}
                            rows={rows}
                            table={table}
                            search={search}
                            onSearchChange={setSearch}
                            onPageChange={goToPage}
                            onPerPageChange={setPerPage}
                            filters={
                                <ReportFilterBar
                                    options={filterOptions}
                                    filters={filters}
                                    isStudentSummary={false}
                                    onFilterChange={filterBy}
                                />
                            }
                        />
                    )
                )}
            </div>
        </AppLayout>
    );
}
```

`AppLayout` menerima `title`, `description`, `actions`, dan `children` — judul halaman dan tombol unduh masuk lewat prop itu, bukan markup sendiri, agar seragam dengan modul lain.

- [ ] **Step 5: Delete the old page and point the sidebar at the new routes**

```bash
git rm resources/js/pages/reports/index.tsx
```

Modify `app/Support/SidebarBuilder.php`, grup `Laporan`:

```php
            $this->group('Laporan', [
                $this->item('Rekap Absensi', 'FileSpreadsheet', 'reports.attendance', route: 'reports.show', query: ['type' => 'attendance']),
                $this->item('Rekap Jurnal', 'FileText', 'reports.journal', route: 'reports.show', query: ['type' => 'journal']),
                $this->item('Nilai PKL', 'FileBadge', 'reports.assessment', route: 'reports.show', query: ['type' => 'assessment']),
                $this->item('Monitoring', 'FileSearch', 'reports.monitoring', route: 'reports.show', query: ['type' => 'monitoring']),
                $this->item('Rekap Siswa', 'IdCard', 'reports.student_summary', route: 'reports.show', query: ['type' => 'student_summary']),
            ]),
```

`item()` meneruskan array itu ke `route()`, dan `type` adalah parameter route — jadi hasilnya `/reports/attendance`, bukan query string.

- [ ] **Step 6: Verify the frontend**

Run: `npm run types:check && npm run lint:check && npm run format:check && npm run build`
Expected: keempatnya lolos. Jalankan `npm run format` dan `npm run lint` untuk memperbaiki otomatis bila perlu.

- [ ] **Step 7: Run the PHP suite**

Run: `php artisan test tests/Feature/Report tests/Feature/SidebarTest.php`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add resources/js/pages/reports resources/js/components/reports app/Support/SidebarBuilder.php
git commit -m "feat: render reports on screen with a filter bar"
```

---

### Task 6: Buang alur generate dan tabel `report_exports`

Membersihkan sisa konsep lama sekarang tidak ada lagi yang memakainya.

**Files:**
- Create: `database/migrations/2026_08_12_000001_drop_report_exports_table.php`
- Delete: `app/Models/ReportExport.php`, `app/Policies/ReportExportPolicy.php`, `app/Http/Requests/Report/StoreReportRequest.php`, `app/Enums/ReportStatus.php`, `database/factories/ReportExportFactory.php`
- Test: `tests/Feature/Report/StudentSummaryReportTest.php` (tulis ulang)

**Interfaces:**
- Consumes: seluruh Task 1–5.
- Produces: skema tanpa `report_exports`; tidak ada simbol baru.

- [ ] **Step 1: Rewrite the student summary test against the new flow**

Ganti seluruh isi `tests/Feature/Report/StudentSummaryReportTest.php`:

```php
<?php

declare(strict_types=1);

use App\DataTransferObjects\ReportFilters;
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
use App\Models\Student;
use App\Models\Teacher;
use App\Services\ReportService;

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

test('downloading a student outside the users scope is a 404', function (): void {
    $outsider = Student::factory()->create();

    $this->actingAs($this->admin)
        ->get("/reports/student_summary/download?student_id={$outsider->id}")
        ->assertNotFound();
});
```

- [ ] **Step 2: Run the test to see it pass against the current code**

Run: `php artisan test tests/Feature/Report/StudentSummaryReportTest.php`
Expected: PASS — Task 3 dan 4 sudah menyediakan semuanya. Kalau ada yang merah, perbaiki di sini sebelum melanjutkan.

- [ ] **Step 3: Write the drop migration**

Create `database/migrations/2026_08_12_000001_drop_report_exports_table.php`:

```php
<?php

declare(strict_types=1);

use App\Enums\ReportFormat;
use App\Enums\ReportType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Reports are no longer artefacts: they are read live from filters, so there is
 * nothing left to record or to keep on disk.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('report_exports');

        // The files were only ever reachable through the table that just went away.
        Storage::disk('local')->deleteDirectory('reports');
    }

    public function down(): void
    {
        Schema::create('report_exports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('requested_by')->constrained('users')->cascadeOnDelete();
            $table->string('type');
            $table->string('format');
            $table->string('status')->default('pending');
            $table->jsonb('filters')->default('{}');
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedInteger('row_count')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['requested_by', 'created_at']);
        });

        $types = implode("','", ReportType::values());
        $formats = implode("','", ReportFormat::values());
        // ReportStatus is gone with this change, so its values are spelled out here.
        $statuses = implode("','", ['pending', 'processing', 'completed', 'failed']);

        DB::statement("ALTER TABLE report_exports ADD CONSTRAINT report_exports_type_check
             CHECK (type IN ('{$types}'))");
        DB::statement("ALTER TABLE report_exports ADD CONSTRAINT report_exports_format_check
             CHECK (format IN ('{$formats}'))");
        DB::statement("ALTER TABLE report_exports ADD CONSTRAINT report_exports_status_check
             CHECK (status IN ('{$statuses}'))");
        DB::statement("ALTER TABLE report_exports ADD CONSTRAINT report_exports_file_check
             CHECK (
                (status = 'completed' AND file_path IS NOT NULL)
                OR (status <> 'completed' AND file_path IS NULL)
             )");
    }
};
```

- [ ] **Step 4: Delete the dead code**

```bash
git rm app/Models/ReportExport.php app/Policies/ReportExportPolicy.php app/Http/Requests/Report/StoreReportRequest.php app/Enums/ReportStatus.php database/factories/ReportExportFactory.php
```

Lalu cari sisa referensi:

Run: `grep -rn "ReportExport\|ReportStatus\|StoreReportRequest" app/ database/ tests/ resources/js/`
Expected: tidak ada hasil. Kalau `database/seeders/` atau `AuditTrailTest` menyebutnya, hapus baris itu juga.

- [ ] **Step 5: Verify the migration runs both ways**

Run: `php artisan migrate:fresh --env=testing && php artisan migrate:rollback --step=1 --env=testing && php artisan migrate --env=testing`
Expected: ketiganya sukses tanpa error constraint.

- [ ] **Step 6: Run the whole suite and the checks**

Run: `composer ci:check`
Expected: eslint, prettier, tsc, Pint, phpstan, dan seluruh Pest suite hijau.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "refactor: drop report_exports and the generate-then-download flow"
```

---

## Verifikasi Akhir

- [ ] **Jalankan aplikasi dan periksa langsung**

Gunakan skill `verify` proyek ini untuk membuka `/reports/attendance` sebagai Admin PKL dan sebagai Guru Pembimbing, ganti beberapa filter, buka halaman kedua, lalu unduh PDF dan Excel. Periksa juga `/reports/student_summary` sebelum dan sesudah memilih siswa.

Yang harus terlihat:
- Tabel muncul tanpa menekan tombol apa pun.
- Mengganti filter memperbarui URL dan tabel.
- Guru Pembimbing hanya melihat siswa bimbingannya.
- Berkas terunduh dan isinya mencakup seluruh baris, bukan hanya halaman aktif.
- Sidebar Laporan menunjuk `/reports/<tipe>`.

## Self-Review

**Cakupan spec:** Setiap bagian spec punya task — arsitektur/routing/otorisasi (Task 2, 4), filter dan default tahun ajaran (Task 1), service dan paginasi (Task 3), unduhan streaming dan audit (Task 4), UI dan sidebar (Task 5), pembuangan serta migrasi (Task 6), testing (tersebar di Task 1–6 plus `composer ci:check` di Task 6).

**Konsistensi tipe:** `ReportFilters` dipakai dengan nama properti yang sama di Task 1, 3, 4, dan 6. Baris laporan berbentuk `array{key, cells}` di `paginate()` (Task 3), dikonsumsi sebagai `ReportRow` di frontend (Task 5), dan diratakan dengan `array_column(..., 'cells')` untuk `ReportDataset` yang bentuknya tidak berubah. `ReportService::paginate()` menerima argumen `page` yang diteruskan controller di Task 4.

**Komponen bersama:** `Combobox` (`value`/`onValueChange`/`options`), `DataTableCard`, `FilterSelect`, dan `AppLayout` (`title`/`description`/`actions`) dipakai apa adanya. Pekerjaan ini tidak boleh mengubahnya.

**Satu asumsi yang perlu diperiksa saat Task 4 berjalan:** apakah model activity log proyek ini `App\Models\Activity` atau `Spatie\Activitylog\Models\Activity` — test unduhan mengimpornya, dan Step 1 Task 4 sudah menandai ini.
