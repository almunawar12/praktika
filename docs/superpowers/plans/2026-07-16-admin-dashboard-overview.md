# Admin Dashboard Overview Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rich dashboard for Admin PKL and Super Admin — six stat cards with trend sub-labels, 4-series activity line chart, today-attendance donut, pending-journal / recent-assessment lists, and active-placement share per jurusan. Other roles keep the current dashboard.

**Architecture:** New `App\Services\DashboardOverviewService` builds the admin-only `overview` payload; `DashboardService` injects it and adds `overview` (null for non-admin roles) — existing `stats`/`recentActivities` keys unchanged. Frontend: `dashboard.tsx` branches on `overview`; new components under `resources/js/components/dashboard/` composed by `admin-overview.tsx`. Charts via recharts through the shadcn chart component.

**Tech Stack:** Laravel 13 / PHP 8.3 / PostgreSQL, Pest, Inertia + React 19 + TypeScript, Tailwind v4 + shadcn/ui, recharts (new dependency).

**Spec:** `docs/superpowers/specs/2026-07-16-admin-dashboard-overview-design.md`

## Global Constraints

- `declare(strict_types=1);` in every PHP file; PSR-12; run `vendor/bin/pint --dirty` before each commit.
- All Eloquent queries scoped with `->visibleTo($user)` where the model defines it (all PKL models do).
- Tests: Pest, PostgreSQL test DB (`db_praktika_test`). `userWithRole(RoleName $role)` helper exists in `tests/Pest.php`.
- Frontend: shadcn UI + Tailwind only; no custom CSS files; inline `style` only for dynamic progress-bar width. 4-space indent, single quotes (Prettier enforces).
- Indonesian copy is exact as written in each task — do not paraphrase.
- Chart palette (validated for CVD both modes — do not substitute): series `#2a78d6 / #008300 / #e87ba4 / #eda100` (light), `#3987e5 / #008300 / #d55181 / #c98500` (dark); donut `#0ca30c / #2a78d6 / #eda100 / #d03b3b` (light), izin `#3987e5`, sakit `#c98500` in dark.
- No new routes, migrations, or permissions — dashboard route + `dashboard.view` gate already exist.
- Icons must be names already registered in `resources/js/components/layout/nav-icon.tsx`: `GraduationCap`, `Building2`, `MapPin`, `Award`, `NotebookPen`, `CalendarCheck`.

---

### Task 1: Overview service — stats + payload wiring

**Files:**
- Create: `app/Services/DashboardOverviewService.php`
- Modify: `app/Services/DashboardService.php` (constructor + `forUser()`)
- Test: `tests/Feature/DashboardOverviewTest.php`

**Interfaces:**
- Consumes: `Placement::scopeVisibleTo`, enums `PlacementStatus`, `ReviewStatus`, `AttendanceStatus`, `VerificationStatus`, `RoleName`.
- Produces: `DashboardOverviewService::forAdmin(User $user): array` returning `['stats' => array<int, array{label: string, icon: string, value: int, subLabel: string, subVariant: string}>]` (later tasks add sibling keys). Inertia prop `overview` — array for SuperAdmin/AdminPkl, `null` otherwise.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/DashboardOverviewTest.php`:

```php
<?php

declare(strict_types=1);

use App\Enums\ReviewStatus;
use App\Enums\RoleName;
use App\Models\Company;
use App\Models\Journal;
use App\Models\Placement;
use App\Models\Student;
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

test('non-admin roles get no overview', function (RoleName $role): void {
    $this->actingAs(userWithRole($role))
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('overview', null));
})->with([
    'guru' => RoleName::GuruPembimbing,
    'industri' => RoleName::PembimbingIndustri,
    'siswa' => RoleName::Siswa,
]);

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
    Journal::factory()->create(['status' => ReviewStatus::Approved]);

    $this->actingAs(userWithRole(RoleName::AdminPkl))
        ->get('/dashboard')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('overview.stats.4.value', 1)
            ->where('overview.stats.4.subLabel', 'Perlu verifikasi')
            ->where('overview.stats.4.subVariant', 'warning')
        );
});
```

Note: if `Journal::factory()->create()` needs a placement, it creates one through its factory defaults — check `database/factories/JournalFactory.php` and pass `['placement_id' => Placement::factory()->active()]` explicitly if the factory does not.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=DashboardOverviewTest`
Expected: FAIL — `overview` prop missing (`Inertia property [overview] does not exist`).

- [ ] **Step 3: Implement the service and wire it in**

`app/Services/DashboardOverviewService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\PlacementStatus;
use App\Enums\ReviewStatus;
use App\Enums\VerificationStatus;
use App\Models\Attendance;
use App\Models\Company;
use App\Models\Journal;
use App\Models\Placement;
use App\Models\Student;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Builds the rich dashboard payload for Admin PKL and Super Admin.
 *
 * Separate from DashboardService so the per-role tile logic and the admin
 * overview never tangle: this class knows nothing about roles — the caller
 * decides who gets an overview at all.
 */
class DashboardOverviewService
{
    /**
     * @return array<string, mixed>
     */
    public function forAdmin(User $user): array
    {
        return [
            'stats' => $this->stats($user),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function stats(User $user): array
    {
        $now = CarbonImmutable::now();
        $monthStart = $now->startOfMonth();

        // One grouped scan covers every placement figure on the page.
        $placements = Placement::query()
            ->visibleTo($user)
            ->groupBy('status')
            ->selectRaw('status, count(*) as total')
            ->pluck('total', 'status');

        $totalPlacements = (int) $placements->sum();
        $active = (int) ($placements[PlacementStatus::Active->value] ?? 0);
        $completed = (int) ($placements[PlacementStatus::Completed->value] ?? 0);

        $students = Student::count();
        $newStudents = Student::query()->where('created_at', '>=', $monthStart)->count();
        $companies = Company::count();
        $newCompanies = Company::query()->where('created_at', '>=', $monthStart)->count();

        $pendingJournals = Journal::query()
            ->visibleTo($user)
            ->where('status', ReviewStatus::Pending)
            ->count();

        $presentToday = Attendance::query()
            ->visibleTo($user)
            ->whereDate('date', $now->toDateString())
            ->where('status', AttendanceStatus::Present)
            ->where('verification_status', '!=', VerificationStatus::Rejected)
            ->count();

        return [
            $this->stat('Total Siswa PKL', 'GraduationCap', $students, $this->delta($newStudents)),
            $this->stat('Total Perusahaan', 'Building2', $companies, $this->delta($newCompanies)),
            $this->stat('PKL Aktif', 'MapPin', $active, [$this->share($active, $totalPlacements).' dari total', 'muted']),
            $this->stat('PKL Selesai', 'Award', $completed, [$this->share($completed, $totalPlacements).' dari total', 'muted']),
            $this->stat('Jurnal Pending', 'NotebookPen', $pendingJournals, ['Perlu verifikasi', $pendingJournals > 0 ? 'warning' : 'muted']),
            $this->stat('Kehadiran Hari Ini', 'CalendarCheck', $presentToday, [$this->share($presentToday, $this->activeCount($user)).' hadir', 'positive']),
        ];
    }

    private function activeCount(User $user): int
    {
        return Placement::query()->visibleTo($user)->active()->count();
    }

    /**
     * @return array{string, string}
     */
    private function delta(int $added): array
    {
        return ['+'.$added.' dari bulan lalu', $added > 0 ? 'positive' : 'muted'];
    }

    /**
     * "75%"-style share, with the zero denominator guarded.
     */
    private function share(int $part, int $whole): string
    {
        if ($whole === 0) {
            return '0%';
        }

        return (string) (float) round($part / $whole * 100, 1).'%';
    }

    /**
     * @param  array{string, string}  $sub
     * @return array<string, mixed>
     */
    private function stat(string $label, string $icon, int $value, array $sub): array
    {
        return [
            'label' => $label,
            'icon' => $icon,
            'value' => $value,
            'subLabel' => $sub[0],
            'subVariant' => $sub[1],
        ];
    }
}
```

Note on `share()`: `(string) (float) round(75.0, 1)` renders `75`, `82.85…` renders `82.9` — matches the reference copy (`82.9% dari total`, `75% dari total`).

`app/Services/DashboardService.php` — add constructor and the `overview` key:

```php
public function __construct(private readonly DashboardOverviewService $overview) {}
```

In `forUser()`, extend the returned array:

```php
return [
    'role' => $role?->value,
    'roleLabel' => $role?->label(),
    'stats' => $this->statsFor($user, $role),
    'recentActivities' => $this->recentActivities($user),
    'overview' => $this->overviewFor($user, $role),
];
```

And add the private method:

```php
/**
 * The rich overview exists only for the two admin roles; everyone else
 * renders the plain tile dashboard and gets an explicit null.
 *
 * @return array<string, mixed>|null
 */
private function overviewFor(User $user, ?RoleName $role): ?array
{
    if (! in_array($role, [RoleName::SuperAdmin, RoleName::AdminPkl], true)) {
        return null;
    }

    return $this->overview->forAdmin($user);
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=DashboardOverviewTest`
Expected: PASS (all 5 tests, 8 assertions groups). Also run `php artisan test --filter=DashboardTest` — the existing suite must stay green.

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty
git add app/Services/DashboardOverviewService.php app/Services/DashboardService.php tests/Feature/DashboardOverviewTest.php
git commit -m "feat: dashboard — stat overview admin dengan tren bulanan"
```

---

### Task 2: Overview service — activity chart + attendance today

**Files:**
- Modify: `app/Services/DashboardOverviewService.php`
- Test: `tests/Feature/DashboardOverviewTest.php` (append)

**Interfaces:**
- Consumes: `forAdmin()` from Task 1.
- Produces: `overview.activityChart` = 30 items `{date: 'Y-m-d', absensi: int, jurnal: int, monitoring: int, penilaian: int}` (oldest first, today last, zero-filled); `overview.attendanceToday` = `{hadir: int, izin: int, sakit: int, alpha: int, total: int, rate: float}`.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/DashboardOverviewTest.php` (add `use App\Enums\AttendanceStatus; use App\Models\Assessment; use App\Models\Attendance; use App\Models\Monitoring;` to the imports; `App\Enums\AssessmentType` too):

```php
test('the activity chart zero-fills thirty days of counts', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-07-15 10:00:00'));

    $placement = Placement::factory()->active()->create();

    Attendance::factory()->count(2)->create([
        'placement_id' => $placement->id,
        'date' => '2026-07-10',
    ]);
    Journal::factory()->create([
        'placement_id' => $placement->id,
        'date' => '2026-07-10',
        'status' => ReviewStatus::Pending,
    ]);
    Monitoring::factory()->create([
        'placement_id' => $placement->id,
        'visit_date' => '2026-07-10',
    ]);
    Assessment::factory()->create([
        'placement_id' => $placement->id,
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
    Attendance::factory()->create([
        'placement_id' => $placements[1]->id,
        'date' => '2026-07-15',
        'status' => AttendanceStatus::Permit,
    ]);
    Attendance::factory()->create([
        'placement_id' => $placements[2]->id,
        'date' => '2026-07-15',
        'status' => AttendanceStatus::Sick,
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
```

Note: check `database/factories/AttendanceFactory.php` first — if its default `verification_status` is `Rejected` (unlikely) pass an explicit non-rejected one; if defaults already create a placement, keep the explicit `placement_id` anyway so counts are deterministic.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=DashboardOverviewTest`
Expected: the two new tests FAIL (`overview.activityChart` missing); the Task-1 tests stay green.

- [ ] **Step 3: Implement**

In `DashboardOverviewService`, add to the imports: `App\Models\Assessment`, `App\Models\Monitoring`, `Illuminate\Database\Eloquent\Builder`. Extend `forAdmin()`:

```php
return [
    'stats' => $this->stats($user),
    'activityChart' => $this->activityChart($user),
    'attendanceToday' => $this->attendanceToday($user),
];
```

Add the methods:

```php
/**
 * Daily activity counts for the last 30 days, zero-filled so the chart
 * never has to guess at missing days. The frontend slices 7/14/30.
 *
 * @return array<int, array<string, mixed>>
 */
private function activityChart(User $user): array
{
    $start = CarbonImmutable::now()->subDays(29)->startOfDay();

    $absensi = $this->dailyCounts(Attendance::query()->visibleTo($user), 'date', $start);
    $jurnal = $this->dailyCounts(Journal::query()->visibleTo($user), 'date', $start);
    $monitoring = $this->dailyCounts(Monitoring::query()->visibleTo($user), 'visit_date', $start);
    $penilaian = $this->dailyCounts(
        Assessment::query()->visibleTo($user)->whereNotNull('submitted_at'),
        'submitted_at',
        $start,
    );

    $days = [];

    for ($i = 0; $i < 30; $i++) {
        $date = $start->addDays($i)->toDateString();

        $days[] = [
            'date' => $date,
            'absensi' => $absensi[$date] ?? 0,
            'jurnal' => $jurnal[$date] ?? 0,
            'monitoring' => $monitoring[$date] ?? 0,
            'penilaian' => $penilaian[$date] ?? 0,
        ];
    }

    return $days;
}

/**
 * Count per calendar day. `date(...)` truncates timestamps in PostgreSQL.
 *
 * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
 * @return array<string, int>
 */
private function dailyCounts(Builder $query, string $column, CarbonImmutable $start): array
{
    return $query
        ->where($column, '>=', $start)
        ->groupByRaw("date({$column})")
        ->selectRaw("date({$column}) as day, count(*) as total")
        ->pluck('total', 'day')
        ->mapWithKeys(fn (int|string $total, string $day): array => [
            CarbonImmutable::parse($day)->toDateString() => (int) $total,
        ])
        ->all();
}

/**
 * Today's attendance split. Alpha is derived, not stored: every active
 * placement not accounted for by a non-rejected hadir/izin/sakit record —
 * which also absorbs the nightly `absent` rows.
 *
 * @return array<string, mixed>
 */
private function attendanceToday(User $user): array
{
    $total = $this->activeCount($user);

    $today = Attendance::query()
        ->visibleTo($user)
        ->whereDate('date', CarbonImmutable::now()->toDateString())
        ->where('verification_status', '!=', VerificationStatus::Rejected)
        ->groupBy('status')
        ->selectRaw('status, count(*) as total')
        ->pluck('total', 'status');

    $hadir = (int) ($today[AttendanceStatus::Present->value] ?? 0);
    $izin = (int) ($today[AttendanceStatus::Permit->value] ?? 0);
    $sakit = (int) ($today[AttendanceStatus::Sick->value] ?? 0);

    return [
        'hadir' => $hadir,
        'izin' => $izin,
        'sakit' => $sakit,
        'alpha' => max(0, $total - $hadir - $izin - $sakit),
        'total' => $total,
        'rate' => $total > 0 ? (float) round($hadir / $total * 100, 1) : 0.0,
    ];
}
```

`activeCount()` is now called twice per request (stats + attendanceToday) — memoize it:

```php
private ?int $activeCount = null;

private function activeCount(User $user): int
{
    return $this->activeCount ??= Placement::query()->visibleTo($user)->active()->count();
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=DashboardOverviewTest`
Expected: PASS.

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty
git add app/Services/DashboardOverviewService.php tests/Feature/DashboardOverviewTest.php
git commit -m "feat: dashboard — grafik aktivitas 30 hari dan kehadiran hari ini"
```

---

### Task 3: Overview service — journals, assessments, departments

**Files:**
- Modify: `app/Services/DashboardOverviewService.php`
- Test: `tests/Feature/DashboardOverviewTest.php` (append)

**Interfaces:**
- Consumes: `forAdmin()` from Tasks 1–2.
- Produces: `overview.pendingJournals` = up to 4 items `{id, student, className, company, submittedAt}` (newest first); `overview.recentAssessments` = up to 4 items `{id, student, company, type: 'Industri'|'Guru', score: float|null}`; `overview.activeByDepartment` = `{name, count, percentage}[]` sorted by count desc.

- [ ] **Step 1: Write the failing tests**

Append (add imports `App\Models\Department`, `App\Models\SchoolClass`):

```php
test('pending journals list the four newest with student, class, and company', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-07-15 10:00:00'));

    $placement = Placement::factory()->active()->create();

    Journal::factory()->count(5)->sequence(
        ['created_at' => '2026-07-10 08:00:00'],
        ['created_at' => '2026-07-11 08:00:00'],
        ['created_at' => '2026-07-12 08:00:00'],
        ['created_at' => '2026-07-13 08:00:00'],
        ['created_at' => '2026-07-14 08:00:00'],
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
            ->where('overview.pendingJournals.0.submittedAt', '2026-07-14T08:00:00+00:00')
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

    Placement::factory()->active()->count(3)->create([
        'student_id' => fn (): string => Student::factory()->create(['class_id' => $rplClass->id])->id,
    ]);
    Placement::factory()->active()->create([
        'student_id' => Student::factory()->create(['class_id' => $aklClass->id])->id,
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
```

Timestamp note: the app timezone decides the ISO offset in `submittedAt` — if `config('app.timezone')` is not UTC, adjust the expected string (run the test and read the actual value once; asserting the exact string documents the wire format).

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=DashboardOverviewTest`
Expected: three new FAILs (`overview.pendingJournals` missing).

- [ ] **Step 3: Implement**

Extend `forAdmin()`:

```php
return [
    'stats' => $this->stats($user),
    'activityChart' => $this->activityChart($user),
    'attendanceToday' => $this->attendanceToday($user),
    'pendingJournals' => $this->pendingJournals($user),
    'recentAssessments' => $this->recentAssessments($user),
    'activeByDepartment' => $this->activeByDepartment($user),
];
```

Add (import `App\Enums\AssessmentType`):

```php
/**
 * @return array<int, array<string, mixed>>
 */
private function pendingJournals(User $user): array
{
    return Journal::query()
        ->visibleTo($user)
        ->where('status', ReviewStatus::Pending)
        ->with(['placement.student.schoolClass', 'placement.company'])
        ->latest('created_at')
        ->limit(4)
        ->get()
        ->map(fn (Journal $journal): array => [
            'id' => $journal->id,
            'student' => $journal->placement->student->name,
            'className' => $journal->placement->student->schoolClass->name,
            'company' => $journal->placement->company->name,
            'submittedAt' => $journal->created_at?->toIso8601String(),
        ])
        ->all();
}

/**
 * @return array<int, array<string, mixed>>
 */
private function recentAssessments(User $user): array
{
    return Assessment::query()
        ->visibleTo($user)
        ->whereNotNull('submitted_at')
        ->with(['placement.student', 'placement.company'])
        ->latest('submitted_at')
        ->limit(4)
        ->get()
        ->map(fn (Assessment $assessment): array => [
            'id' => $assessment->id,
            'student' => $assessment->placement->student->name,
            'company' => $assessment->placement->company->name,
            'type' => $assessment->type === AssessmentType::Industry ? 'Industri' : 'Guru',
            'score' => $assessment->average_score !== null ? (float) $assessment->average_score : null,
        ])
        ->all();
}

/**
 * Active placements per jurusan. Joins walk placement → student → class →
 * department; soft-deleted rows on the joined tables are excluded manually
 * since joins bypass the models' global scopes.
 *
 * @return array<int, array<string, mixed>>
 */
private function activeByDepartment(User $user): array
{
    $rows = Placement::query()
        ->visibleTo($user)
        ->active()
        ->join('students', 'students.id', '=', 'placements.student_id')
        ->join('school_classes', 'school_classes.id', '=', 'students.class_id')
        ->join('departments', 'departments.id', '=', 'school_classes.department_id')
        ->whereNull('students.deleted_at')
        ->whereNull('school_classes.deleted_at')
        ->whereNull('departments.deleted_at')
        ->groupBy('departments.id', 'departments.name')
        ->selectRaw('departments.name, count(*) as total')
        ->orderByDesc('total')
        ->get();

    $totalActive = (int) $rows->sum('total');

    return $rows
        ->map(fn (object $row): array => [
            'name' => (string) $row->name,
            'count' => (int) $row->total,
            'percentage' => $totalActive > 0 ? (float) round((int) $row->total / $totalActive * 100, 1) : 0.0,
        ])
        ->all();
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=DashboardOverviewTest`
Expected: PASS. Then the full backend suite: `php artisan test` — everything green.

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty
git add app/Services/DashboardOverviewService.php tests/Feature/DashboardOverviewTest.php
git commit -m "feat: dashboard — jurnal pending, penilaian terbaru, sebaran jurusan"
```

---

### Task 4: Frontend setup — recharts, shadcn chart, palette

**Files:**
- Modify: `package.json` (via CLI), `resources/css/app.css`
- Create: `resources/js/components/ui/chart.tsx` (via CLI)

**Interfaces:**
- Produces: `ChartContainer`, `ChartTooltip`, `ChartTooltipContent`, `ChartLegend`, `ChartLegendContent`, `ChartConfig` from `@/components/ui/chart`; CSS vars `--chart-1..4` (series) and `--status-hadir/izin/sakit/alpha` (donut), themed light/dark.

- [ ] **Step 1: Add the shadcn chart component (installs recharts)**

Run: `npx shadcn@latest add chart`
Expected: `resources/js/components/ui/chart.tsx` created, `recharts` added to `package.json` dependencies. If the CLI does not install the package: `npm install recharts`.

- [ ] **Step 2: Set the validated chart palette**

In `resources/css/app.css`, in the **light** theme block (`:root`), replace the grayscale `--chart-1` … `--chart-4` values and add the status vars (leave `--chart-5` as-is):

```css
--chart-1: #2a78d6;
--chart-2: #008300;
--chart-3: #e87ba4;
--chart-4: #eda100;
--status-hadir: #0ca30c;
--status-izin: #2a78d6;
--status-sakit: #eda100;
--status-alpha: #d03b3b;
```

In the **dark** theme block (`.dark`), same slots:

```css
--chart-1: #3987e5;
--chart-2: #008300;
--chart-3: #d55181;
--chart-4: #c98500;
--status-hadir: #0ca30c;
--status-izin: #3987e5;
--status-sakit: #c98500;
--status-alpha: #d03b3b;
```

These exact values passed the CVD/contrast validator for both surfaces — do not adjust them by eye. The `--color-chart-*` aliases at the top of the file already point at `--chart-*`; mirror that pattern only if a `--color-status-*` alias turns out to be needed (Tailwind arbitrary values `bg-(--status-hadir)` do not need one; plain `var(--status-hadir)` in recharts props does not either).

- [ ] **Step 3: Verify the build**

Run: `npm run types:check` then `npm run build`
Expected: both succeed with the new `chart.tsx` compiled in.

- [ ] **Step 4: Commit**

```bash
git add package.json package-lock.json resources/js/components/ui/chart.tsx resources/css/app.css
git commit -m "feat: dashboard — pasang recharts dan palet grafik tervalidasi"
```

---

### Task 5: Frontend — types, stat cards, dashboard branch

**Files:**
- Create: `resources/js/types/dashboard.ts`, `resources/js/components/dashboard/overview-stat-card.tsx`, `resources/js/components/dashboard/admin-overview.tsx`
- Modify: `resources/js/pages/dashboard.tsx`, `resources/js/layouts/app-layout.tsx`

**Interfaces:**
- Consumes: `overview` Inertia prop (Tasks 1–3 shape), `NavIcon`, `AppLayout`.
- Produces: `DashboardOverview` type; `<OverviewStatCard stat={OverviewStat} />`; `<AdminOverview overview={DashboardOverview} />` (grid grows in Tasks 6–7); `AppLayout` gains optional `headTitle` prop.

- [ ] **Step 1: Write the types**

`resources/js/types/dashboard.ts`:

```ts
export type OverviewStat = {
    label: string;
    icon: string;
    value: number;
    subLabel: string;
    subVariant: 'positive' | 'warning' | 'muted';
};

export type ActivityChartPoint = {
    date: string;
    absensi: number;
    jurnal: number;
    monitoring: number;
    penilaian: number;
};

export type AttendanceToday = {
    hadir: number;
    izin: number;
    sakit: number;
    alpha: number;
    total: number;
    rate: number;
};

export type PendingJournal = {
    id: string;
    student: string;
    className: string;
    company: string;
    submittedAt: string | null;
};

export type RecentAssessment = {
    id: string;
    student: string;
    company: string;
    type: 'Industri' | 'Guru';
    score: number | null;
};

export type DepartmentShare = {
    name: string;
    count: number;
    percentage: number;
};

export type DashboardOverview = {
    stats: OverviewStat[];
    activityChart: ActivityChartPoint[];
    attendanceToday: AttendanceToday;
    pendingJournals: PendingJournal[];
    recentAssessments: RecentAssessment[];
    activeByDepartment: DepartmentShare[];
};
```

- [ ] **Step 2: Write the stat card**

`resources/js/components/dashboard/overview-stat-card.tsx`:

```tsx
import { NavIcon } from '@/components/layout/nav-icon';
import { Card, CardContent } from '@/components/ui/card';
import type { OverviewStat } from '@/types/dashboard';

/**
 * Decorative icon tint per tile, keyed by the icon the server names so the
 * palette needs no server round-trip. Fallback keeps unknown icons readable.
 */
const tones: Record<string, string> = {
    GraduationCap: 'bg-violet-500/10 text-violet-600 dark:text-violet-400',
    Building2: 'bg-orange-500/10 text-orange-600 dark:text-orange-400',
    MapPin: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
    Award: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
    NotebookPen: 'bg-rose-500/10 text-rose-600 dark:text-rose-400',
    CalendarCheck: 'bg-sky-500/10 text-sky-600 dark:text-sky-400',
};

const subStyles: Record<OverviewStat['subVariant'], string> = {
    positive: 'text-emerald-600 dark:text-emerald-400',
    warning: 'text-red-600 dark:text-red-400',
    muted: 'text-muted-foreground',
};

export function OverviewStatCard({ stat }: { stat: OverviewStat }) {
    return (
        <Card>
            <CardContent className="flex flex-col gap-3">
                <div className="flex items-center gap-3">
                    <span
                        className={`flex size-10 shrink-0 items-center justify-center rounded-lg ${tones[stat.icon] ?? 'bg-muted text-muted-foreground'}`}
                    >
                        <NavIcon name={stat.icon} className="size-5" />
                    </span>
                    <p className="truncate text-sm text-muted-foreground">
                        {stat.label}
                    </p>
                </div>

                <p className="text-2xl font-semibold tabular-nums">
                    {stat.value}
                </p>

                <p className={`text-xs ${subStyles[stat.subVariant]}`}>
                    {stat.subLabel}
                </p>
            </CardContent>
        </Card>
    );
}
```

- [ ] **Step 3: Write the overview shell**

`resources/js/components/dashboard/admin-overview.tsx` (chart/donut/list slots arrive in Tasks 6–7):

```tsx
import { OverviewStatCard } from '@/components/dashboard/overview-stat-card';
import type { DashboardOverview } from '@/types/dashboard';

export function AdminOverview({ overview }: { overview: DashboardOverview }) {
    return (
        <div className="flex flex-col gap-6">
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                {overview.stats.map((stat) => (
                    <OverviewStatCard key={stat.label} stat={stat} />
                ))}
            </div>
        </div>
    );
}
```

- [ ] **Step 4: Add `headTitle` to AppLayout**

In `resources/js/layouts/app-layout.tsx`: extend the props type and the `Head`:

```tsx
type AppLayoutProps = PropsWithChildren<{
    title: string;
    /** Browser-tab title when it should differ from the page heading. */
    headTitle?: string;
    description?: string;
}>;
```

Destructure `headTitle` in the component and change the head line to:

```tsx
<Head title={headTitle ?? title} />
```

- [ ] **Step 5: Branch the dashboard page**

In `resources/js/pages/dashboard.tsx`: add to the imports

```tsx
import { AdminOverview } from '@/components/dashboard/admin-overview';
import type { DashboardOverview } from '@/types/dashboard';
```

extend the props type with `overview: DashboardOverview | null;`, destructure it, and insert the branch at the top of the component body (existing return stays untouched below it):

```tsx
if (overview !== null) {
    return (
        <AppLayout
            headTitle="Dashboard"
            title={`Selamat datang, ${user?.name ?? ''} 👋`}
            description="Berikut ringkasan kegiatan PKL hari ini."
        >
            <AdminOverview overview={overview} />
        </AppLayout>
    );
}
```

- [ ] **Step 6: Verify**

Run: `npm run types:check && npm run lint:check && npm run build`
Expected: all pass. Then `php artisan test --filter=Dashboard` — still green (payload untouched).

- [ ] **Step 7: Commit**

```bash
git add resources/js/types/dashboard.ts resources/js/components/dashboard/overview-stat-card.tsx resources/js/components/dashboard/admin-overview.tsx resources/js/layouts/app-layout.tsx resources/js/pages/dashboard.tsx
git commit -m "feat: dashboard — kartu stat admin dan cabang layout overview"
```

---

### Task 6: Frontend — activity chart + attendance donut

**Files:**
- Create: `resources/js/components/dashboard/activity-chart.tsx`, `resources/js/components/dashboard/attendance-donut.tsx`
- Modify: `resources/js/components/dashboard/admin-overview.tsx`

**Interfaces:**
- Consumes: `ActivityChartPoint[]`, `AttendanceToday` from `@/types/dashboard`; `@/components/ui/chart` primitives; CSS vars from Task 4.
- Produces: `<ActivityChart data={ActivityChartPoint[]} />`, `<AttendanceDonut data={AttendanceToday} />`.

- [ ] **Step 1: Write the activity chart**

`resources/js/components/dashboard/activity-chart.tsx`:

```tsx
import { useMemo, useState } from 'react';
import { CartesianGrid, Line, LineChart, XAxis, YAxis } from 'recharts';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    ChartContainer,
    ChartLegend,
    ChartLegendContent,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import type { ChartConfig } from '@/components/ui/chart';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { ActivityChartPoint } from '@/types/dashboard';

const chartConfig = {
    absensi: { label: 'Absensi', color: 'var(--chart-1)' },
    jurnal: { label: 'Jurnal', color: 'var(--chart-2)' },
    monitoring: { label: 'Monitoring', color: 'var(--chart-3)' },
    penilaian: { label: 'Penilaian', color: 'var(--chart-4)' },
} satisfies ChartConfig;

const series = Object.keys(chartConfig) as (keyof typeof chartConfig)[];

const ranges = [
    { value: '7', label: '7 Hari Terakhir' },
    { value: '14', label: '14 Hari Terakhir' },
    { value: '30', label: '30 Hari Terakhir' },
];

function formatDay(iso: string): string {
    return new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'short',
    }).format(new Date(iso));
}

export function ActivityChart({ data }: { data: ActivityChartPoint[] }) {
    const [range, setRange] = useState('7');

    const visible = useMemo(() => data.slice(-Number(range)), [data, range]);

    const isEmpty = visible.every((point) =>
        series.every((key) => point[key] === 0),
    );

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between gap-2">
                <CardTitle>Grafik Aktivitas PKL</CardTitle>
                <Select value={range} onValueChange={setRange}>
                    <SelectTrigger className="w-44">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {ranges.map((option) => (
                            <SelectItem
                                key={option.value}
                                value={option.value}
                            >
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </CardHeader>
            <CardContent>
                {isEmpty ? (
                    <p className="flex h-72 items-center justify-center text-sm text-muted-foreground">
                        Belum ada aktivitas pada rentang ini.
                    </p>
                ) : (
                    <ChartContainer config={chartConfig} className="h-72 w-full">
                        <LineChart data={visible} accessibilityLayer>
                            <CartesianGrid vertical={false} />
                            <XAxis
                                dataKey="date"
                                tickLine={false}
                                axisLine={false}
                                tickMargin={8}
                                tickFormatter={formatDay}
                            />
                            <YAxis
                                tickLine={false}
                                axisLine={false}
                                width={32}
                                allowDecimals={false}
                            />
                            <ChartTooltip
                                content={
                                    <ChartTooltipContent
                                        labelFormatter={(value) =>
                                            formatDay(String(value))
                                        }
                                    />
                                }
                            />
                            <ChartLegend content={<ChartLegendContent />} />
                            {series.map((key) => (
                                <Line
                                    key={key}
                                    dataKey={key}
                                    type="monotone"
                                    stroke={`var(--color-${key})`}
                                    strokeWidth={2}
                                    dot={false}
                                />
                            ))}
                        </LineChart>
                    </ChartContainer>
                )}
            </CardContent>
        </Card>
    );
}
```

(`var(--color-${key})` works because `ChartContainer` emits a `--color-<seriesKey>` var per config entry — that is the shadcn chart contract.)

- [ ] **Step 2: Write the attendance donut**

`resources/js/components/dashboard/attendance-donut.tsx`:

```tsx
import { CheckCircle2 } from 'lucide-react';
import { Label, Pie, PieChart } from 'recharts';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import type { ChartConfig } from '@/components/ui/chart';
import type { AttendanceToday } from '@/types/dashboard';

const chartConfig = {
    hadir: { label: 'Hadir', color: 'var(--status-hadir)' },
    izin: { label: 'Izin', color: 'var(--status-izin)' },
    sakit: { label: 'Sakit', color: 'var(--status-sakit)' },
    alpha: { label: 'Alpha', color: 'var(--status-alpha)' },
} satisfies ChartConfig;

export function AttendanceDonut({ data }: { data: AttendanceToday }) {
    const slices = (
        [
            { key: 'hadir', value: data.hadir },
            { key: 'izin', value: data.izin },
            { key: 'sakit', value: data.sakit },
            { key: 'alpha', value: data.alpha },
        ] as const
    ).map((slice) => ({
        ...slice,
        label: chartConfig[slice.key].label,
        fill: `var(--color-${slice.key})`,
        percentage:
            data.total > 0
                ? Math.round((slice.value / data.total) * 1000) / 10
                : 0,
    }));

    return (
        <Card>
            <CardHeader>
                <CardTitle>Kehadiran Hari Ini</CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                {data.total === 0 ? (
                    <p className="flex h-48 items-center justify-center text-sm text-muted-foreground">
                        Tidak ada siswa PKL aktif hari ini.
                    </p>
                ) : (
                    <>
                        <ChartContainer
                            config={chartConfig}
                            className="mx-auto aspect-square max-h-52 w-full"
                        >
                            <PieChart>
                                <ChartTooltip
                                    content={<ChartTooltipContent hideLabel />}
                                />
                                <Pie
                                    data={slices.filter(
                                        (slice) => slice.value > 0,
                                    )}
                                    dataKey="value"
                                    nameKey="key"
                                    innerRadius={55}
                                    paddingAngle={2}
                                >
                                    <Label
                                        content={({ viewBox }) => {
                                            if (
                                                !viewBox ||
                                                !('cx' in viewBox) ||
                                                viewBox.cx === undefined ||
                                                viewBox.cy === undefined
                                            ) {
                                                return null;
                                            }

                                            return (
                                                <text
                                                    x={viewBox.cx}
                                                    y={viewBox.cy}
                                                    textAnchor="middle"
                                                    dominantBaseline="middle"
                                                >
                                                    <tspan
                                                        x={viewBox.cx}
                                                        y={viewBox.cy - 6}
                                                        className="fill-foreground text-2xl font-semibold"
                                                    >
                                                        {data.total}
                                                    </tspan>
                                                    <tspan
                                                        x={viewBox.cx}
                                                        y={viewBox.cy + 14}
                                                        className="fill-muted-foreground text-xs"
                                                    >
                                                        Total Siswa
                                                    </tspan>
                                                </text>
                                            );
                                        }}
                                    />
                                </Pie>
                            </PieChart>
                        </ChartContainer>

                        <ul className="flex flex-col gap-2">
                            {slices.map((slice) => (
                                <li
                                    key={slice.key}
                                    className="flex items-center gap-2 text-sm"
                                >
                                    <span
                                        aria-hidden="true"
                                        className="size-2.5 rounded-full"
                                        style={{ backgroundColor: `var(--status-${slice.key})` }}
                                    />
                                    <span>{slice.label}</span>
                                    <span className="ml-auto tabular-nums">
                                        {slice.value}{' '}
                                        <span className="text-muted-foreground">
                                            ({slice.percentage}%)
                                        </span>
                                    </span>
                                </li>
                            ))}
                        </ul>

                        <div className="flex items-center gap-2 rounded-lg bg-emerald-500/10 px-4 py-3 text-sm text-emerald-700 dark:text-emerald-400">
                            <CheckCircle2
                                className="size-4 shrink-0"
                                aria-hidden="true"
                            />
                            <span>Tingkat kehadiran hari ini</span>
                            <span className="ml-auto font-semibold tabular-nums">
                                {data.rate}%
                            </span>
                        </div>
                    </>
                )}
            </CardContent>
        </Card>
    );
}
```

- [ ] **Step 3: Slot both into the overview grid**

In `admin-overview.tsx`, add imports and append below the stat grid (inside the outer flex column):

```tsx
<div className="grid gap-6 xl:grid-cols-3">
    <div className="xl:col-span-2">
        <ActivityChart data={overview.activityChart} />
    </div>
    <AttendanceDonut data={overview.attendanceToday} />
</div>
```

- [ ] **Step 4: Verify**

Run: `npm run types:check && npm run lint:check && npm run build`
Expected: all pass.

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/dashboard/activity-chart.tsx resources/js/components/dashboard/attendance-donut.tsx resources/js/components/dashboard/admin-overview.tsx
git commit -m "feat: dashboard — grafik aktivitas dan donat kehadiran"
```

---

### Task 7: Frontend — list cards, final layout, end-to-end verification

**Files:**
- Create: `resources/js/components/dashboard/pending-journals-card.tsx`, `resources/js/components/dashboard/recent-assessments-card.tsx`, `resources/js/components/dashboard/department-progress-card.tsx`, `resources/js/lib/format.ts`
- Modify: `resources/js/components/dashboard/admin-overview.tsx`

**Interfaces:**
- Consumes: `PendingJournal[]`, `RecentAssessment[]`, `DepartmentShare[]`; `Avatar`, `Badge`, `Button` shadcn components; routes `/journals` and `/assessments` (plain hrefs, matching existing pages).
- Produces: the three list cards; `formatRelativeTime(iso: string | null): string` in `@/lib/format`.

- [ ] **Step 1: Write the relative-time helper**

`resources/js/lib/format.ts`:

```ts
const rtf = new Intl.RelativeTimeFormat('id', { numeric: 'auto' });

/**
 * "5 jam yang lalu"-style label for list rows. Coarse on purpose: the rows
 * link to the module where exact timestamps live.
 */
export function formatRelativeTime(iso: string | null): string {
    if (iso === null) {
        return '-';
    }

    const minutes = Math.round(
        (Date.now() - new Date(iso).getTime()) / 60_000,
    );

    if (minutes < 60) {
        return rtf.format(-Math.max(minutes, 0), 'minute');
    }

    const hours = Math.round(minutes / 60);

    if (hours < 24) {
        return rtf.format(-hours, 'hour');
    }

    return rtf.format(-Math.round(hours / 24), 'day');
}
```

- [ ] **Step 2: Write the pending journals card**

`resources/js/components/dashboard/pending-journals-card.tsx`:

```tsx
import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatRelativeTime } from '@/lib/format';
import type { PendingJournal } from '@/types/dashboard';

function initials(name: string): string {
    return name
        .split(' ')
        .slice(0, 2)
        .map((word) => word.charAt(0))
        .join('')
        .toUpperCase();
}

export function PendingJournalsCard({ items }: { items: PendingJournal[] }) {
    return (
        <Card className="flex flex-col">
            <CardHeader className="flex flex-row items-center justify-between">
                <CardTitle>Jurnal Pending</CardTitle>
                <Button variant="outline" size="sm" asChild>
                    <Link href="/journals">Lihat Semua</Link>
                </Button>
            </CardHeader>
            <CardContent className="flex flex-1 flex-col gap-4">
                {items.length === 0 ? (
                    <p className="flex flex-1 items-center justify-center text-sm text-muted-foreground">
                        Tidak ada jurnal pending.
                    </p>
                ) : (
                    items.map((journal) => (
                        <div key={journal.id} className="flex items-start gap-3">
                            <Avatar className="size-9">
                                <AvatarFallback className="text-xs">
                                    {initials(journal.student)}
                                </AvatarFallback>
                            </Avatar>
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm font-medium">
                                    {journal.student}
                                </p>
                                <p className="truncate text-xs text-muted-foreground">
                                    {journal.className} · {journal.company}
                                </p>
                            </div>
                            <span className="shrink-0 text-xs text-muted-foreground">
                                {formatRelativeTime(journal.submittedAt)}
                            </span>
                        </div>
                    ))
                )}
            </CardContent>
            {items.length > 0 ? (
                <CardFooter>
                    <Link
                        href="/journals"
                        className="flex items-center gap-1 text-sm text-primary hover:underline"
                    >
                        Lihat semua jurnal pending
                        <ArrowRight className="size-4" aria-hidden="true" />
                    </Link>
                </CardFooter>
            ) : null}
        </Card>
    );
}
```

- [ ] **Step 3: Write the recent assessments card**

`resources/js/components/dashboard/recent-assessments-card.tsx`:

```tsx
import { Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { RecentAssessment } from '@/types/dashboard';

export function RecentAssessmentsCard({
    items,
}: {
    items: RecentAssessment[];
}) {
    return (
        <Card className="flex flex-col">
            <CardHeader className="flex flex-row items-center justify-between">
                <CardTitle>Penilaian Terbaru</CardTitle>
                <Button variant="outline" size="sm" asChild>
                    <Link href="/assessments">Lihat Semua</Link>
                </Button>
            </CardHeader>
            <CardContent className="flex flex-1 flex-col gap-4">
                {items.length === 0 ? (
                    <p className="flex flex-1 items-center justify-center text-sm text-muted-foreground">
                        Belum ada penilaian masuk.
                    </p>
                ) : (
                    items.map((assessment) => (
                        <div
                            key={assessment.id}
                            className="flex items-center gap-3"
                        >
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm font-medium">
                                    {assessment.student}
                                </p>
                                <p className="truncate text-xs text-muted-foreground">
                                    {assessment.company}
                                </p>
                            </div>
                            <Badge variant="secondary">{assessment.type}</Badge>
                            <span className="w-8 text-right text-sm font-semibold tabular-nums">
                                {assessment.score ?? '-'}
                            </span>
                        </div>
                    ))
                )}
            </CardContent>
        </Card>
    );
}
```

- [ ] **Step 4: Write the department progress card**

`resources/js/components/dashboard/department-progress-card.tsx`:

```tsx
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { DepartmentShare } from '@/types/dashboard';

export function DepartmentProgressCard({
    items,
}: {
    items: DepartmentShare[];
}) {
    const total = items.reduce((sum, item) => sum + item.count, 0);

    return (
        <Card className="flex flex-col">
            <CardHeader>
                <CardTitle>PKL Aktif Berdasarkan Jurusan</CardTitle>
            </CardHeader>
            <CardContent className="flex flex-1 flex-col gap-4">
                {items.length === 0 ? (
                    <p className="flex flex-1 items-center justify-center text-sm text-muted-foreground">
                        Belum ada PKL aktif.
                    </p>
                ) : (
                    items.map((item) => (
                        <div key={item.name} className="flex flex-col gap-1.5">
                            <div className="flex items-baseline justify-between gap-2 text-sm">
                                <span className="truncate">{item.name}</span>
                                <span className="shrink-0 text-xs text-muted-foreground tabular-nums">
                                    {item.count} ({item.percentage}%)
                                </span>
                            </div>
                            <div
                                role="progressbar"
                                aria-valuenow={item.percentage}
                                aria-valuemin={0}
                                aria-valuemax={100}
                                aria-label={item.name}
                                className="h-2 overflow-hidden rounded-full bg-muted"
                            >
                                <div
                                    className="h-full rounded-full bg-(--chart-1)"
                                    style={{ width: `${item.percentage}%` }}
                                />
                            </div>
                        </div>
                    ))
                )}
            </CardContent>
            {items.length > 0 ? (
                <CardFooter className="justify-between border-t text-sm">
                    <span className="text-muted-foreground">
                        Total Siswa Aktif
                    </span>
                    <span className="font-medium tabular-nums">
                        {total} Siswa
                    </span>
                </CardFooter>
            ) : null}
        </Card>
    );
}
```

- [ ] **Step 5: Complete the overview grid**

In `admin-overview.tsx`, add the three imports and append the final row inside the outer flex column, below the chart row:

```tsx
<div className="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
    <PendingJournalsCard items={overview.pendingJournals} />
    <RecentAssessmentsCard items={overview.recentAssessments} />
    <DepartmentProgressCard items={overview.activeByDepartment} />
</div>
```

- [ ] **Step 6: Full verification**

Run, in order:

```bash
npm run types:check
npm run lint:check
npm run format:check
npm run build
php artisan test
```

Expected: everything green. Then drive it for real (superpowers:verification-before-completion — evidence, not assertion): `composer dev` or `npm run dev` + `php artisan serve`, log in as an Admin PKL user (seeded), open `/dashboard`, and check — 6 stat tiles with sub-labels; chart renders with the range select switching 7/14/30; donut legend counts sum to the total; three list cards populated or showing their empty states; layout stacks cleanly at mobile width; dark mode readable. Log in as a Siswa — old dashboard unchanged.

- [ ] **Step 7: Commit**

```bash
git add resources/js/components/dashboard/pending-journals-card.tsx resources/js/components/dashboard/recent-assessments-card.tsx resources/js/components/dashboard/department-progress-card.tsx resources/js/lib/format.ts resources/js/components/dashboard/admin-overview.tsx
git commit -m "feat: dashboard — daftar jurnal, penilaian, dan sebaran jurusan"
```
