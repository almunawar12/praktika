# Sync Report Generation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Generate PKL reports synchronously inside the create request instead of on a queue, so a report row is always `completed` (or `failed`) by the time the response returns — no queue worker required in any environment.

**Architecture:** `ReportController::store()` builds the dataset via the existing `ReportService`, renders the PDF/XLSX, writes it to disk, and creates the `ReportExport` row already in its final state — all inline, mirroring what `GenerateReportJob::handle()` did on the queue. `GenerateReportJob` is deleted. The frontend list stops polling for status changes since there's nothing left to wait on.

**Tech Stack:** Laravel 13, PHP 8.3, Pest, Maatwebsite Excel, barryvdh/laravel-dompdf, Inertia.js + React 19 + TypeScript.

## Global Constraints

- Strict typing: `declare(strict_types=1);` at the top of every PHP file touched.
- Follow PSR-12 / Laravel Pint formatting (run `vendor/bin/pint --dirty` before committing PHP changes if available).
- No new abstractions beyond what's needed — `ReportService::build()` stays untouched, only the file-write + status bookkeeping moves from the job into the controller.
- Report generation failures must land as a `failed` `ReportExport` row with a truncated error message, never a bare 500.

---

### Task 1: Synchronous report generation (backend)

**Files:**
- Modify: `app/Http/Controllers/Report/ReportController.php:1-22` (imports), `:69-81` (`store()`)
- Delete: `app/Jobs/GenerateReportJob.php`
- Modify: `tests/Feature/Report/ReportTest.php` (full rewrite of the queue-based assertions)

**Interfaces:**
- Consumes: `App\Services\ReportService::build(ReportExport $export): ReportDataset` (unchanged, existing), `App\Exports\ReportSpreadsheet` (unchanged, existing), `App\Enums\ReportStatus` (`Processing`, `Completed`, `Failed` cases, unchanged), `App\Enums\ReportFormat::extension()` / `::contentType()` (unchanged).
- Produces: `ReportController::store()` still returns `RedirectResponse` (`back()->with('success', ...)`) — same as before, so the frontend `Form` component's `onSuccess` handling needs no change.

- [ ] **Step 1: Rewrite `tests/Feature/Report/ReportTest.php` to expect synchronous completion**

Replace the entire file with:

```php
<?php

declare(strict_types=1);

use App\Enums\AttendanceStatus;
use App\Enums\ReportFormat;
use App\Enums\ReportStatus;
use App\Enums\ReportType;
use App\Enums\RoleName;
use App\Enums\VerificationStatus;
use App\Models\Attendance;
use App\Models\Company;
use App\Models\CompanySupervisor;
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

    Attendance::factory()->create([
        'placement_id' => $this->placement->id,
        'status' => AttendanceStatus::Present,
        'verification_status' => VerificationStatus::Verified,
    ]);
});

test('generating a report renders the file and completes the export in the same request', function (ReportFormat $format): void {
    Storage::fake('local');

    $admin = userWithRole(RoleName::AdminPkl);

    $this->actingAs($admin)
        ->post('/reports', ['type' => 'attendance', 'format' => $format->value])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $export = ReportExport::query()->where('requested_by', $admin->id)->sole();

    expect($export->status)->toBe(ReportStatus::Completed)
        ->and($export->type)->toBe(ReportType::Attendance)
        ->and($export->file_path)->not->toBeNull()
        // One row per placement in scope.
        ->and($export->row_count)->toBe(1);

    Storage::disk('local')->assertExists($export->file_path);
})->with([
    'pdf' => ReportFormat::Pdf,
    'xlsx' => ReportFormat::Xlsx,
]);

test('a report that fails to render records the error instead of a 500', function (): void {
    $admin = userWithRole(RoleName::AdminPkl);

    $this->mock(ReportService::class, function ($mock): void {
        $mock->shouldReceive('build')->andThrow(new RuntimeException('disk penuh'));
    });

    $this->actingAs($admin)
        ->post('/reports', ['type' => 'attendance', 'format' => 'pdf'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $export = ReportExport::query()->where('requested_by', $admin->id)->sole();

    expect($export->status)->toBe(ReportStatus::Failed)
        ->and($export->error)->toContain('disk penuh')
        ->and($export->file_path)->toBeNull();
});

test('a completed report is downloaded only by its owner', function (): void {
    Storage::fake('local');

    $owner = userWithRole(RoleName::AdminPkl);
    $other = userWithRole(RoleName::AdminPkl);

    $this->actingAs($owner)
        ->post('/reports', ['type' => 'attendance', 'format' => 'pdf'])
        ->assertSessionHasNoErrors();

    $export = ReportExport::query()->where('requested_by', $owner->id)->sole();

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
    Storage::fake('local');

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

    $this->actingAs($owner)
        ->post('/reports', ['type' => 'attendance', 'format' => 'pdf'])
        ->assertSessionHasNoErrors();

    $export = ReportExport::query()->where('requested_by', $owner->id)->sole();
    $path = $export->file_path;

    $this->actingAs($owner)->delete("/reports/{$export->id}")->assertSessionHasNoErrors();

    Storage::disk('local')->assertMissing($path);
    expect(ReportExport::query()->whereKey($export->id)->exists())->toBeFalse();
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/Report/ReportTest.php`
Expected: Failures — `store()` still dispatches `GenerateReportJob` and leaves the export `pending`, so `status`/`file_path`/`row_count` assertions fail, and `ReportService` isn't mocked/consumed the new way.

- [ ] **Step 3: Rewrite `ReportController::store()` to generate synchronously**

In `app/Http/Controllers/Report/ReportController.php`, replace the imports block (lines 1-21) with:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Report;

use App\DataTransferObjects\TableRequest;
use App\Enums\ReportFormat;
use App\Enums\ReportStatus;
use App\Enums\ReportType;
use App\Exports\ReportSpreadsheet;
use App\Http\Controllers\Controller;
use App\Http\Requests\Report\StoreReportRequest;
use App\Models\Company;
use App\Models\Department;
use App\Models\ReportExport;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
```

Then replace `store()` (lines 69-81 of the original) with:

```php
    public function store(StoreReportRequest $request, ReportService $service): RedirectResponse
    {
        $export = ReportExport::create([
            'requested_by' => $request->user()->id,
            'type' => $request->enum('type', ReportType::class),
            'format' => $request->enum('format', ReportFormat::class),
            'filters' => $request->filters(),
            'status' => ReportStatus::Processing,
        ]);

        try {
            $dataset = $service->build($export);

            $path = 'reports/'.$export->id.'.'.$export->format->extension();
            $fileName = Str::slug($export->type->label())
                .'-'.CarbonImmutable::now()->format('Ymd-His')
                .'.'.$export->format->extension();

            $contents = match ($export->format) {
                ReportFormat::Pdf => Pdf::loadView('reports.pdf', ['dataset' => $dataset])->output(),
                ReportFormat::Xlsx => Excel::raw(new ReportSpreadsheet($dataset), ExcelWriter::XLSX),
            };

            // Private disk: reports contain student data and are only ever streamed
            // back to their owner through the authorized download route.
            Storage::disk('local')->put($path, $contents);

            $export->update([
                'status' => ReportStatus::Completed,
                'file_path' => $path,
                'file_name' => $fileName,
                'row_count' => count($dataset->rows),
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

Leave `index()`, `download()`, and `destroy()` exactly as they are.

- [ ] **Step 4: Delete the now-unused job**

Run: `rm app/Jobs/GenerateReportJob.php`

- [ ] **Step 5: Run the tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/Report/ReportTest.php`
Expected: All tests pass.

- [ ] **Step 6: Run the full test suite to catch any other reference to the deleted job**

Run: `vendor/bin/pest`
Expected: No failures. (If anything else imports `App\Jobs\GenerateReportJob`, it will fail to resolve the class — fix by removing that reference.)

- [ ] **Step 7: Update the stale ADR entry**

`docs/ARCHITECTURE.md` line 45 (ADR-30) describes the old queue-based lifecycle and cites `GenerateReportJob::failed()`. Replace that row with:

```
| ADR-30 | **Baris export ada sebelum berkasnya; status = siklus hidup** | Berkas dibangun langsung di request `store()`, jadi baris lahir `processing` lalu langsung `completed`/`failed` sebelum respons dikirim — tidak ada jeda menunggu worker. `report_exports_file_check` mengikat: hanya `completed` yang boleh punya `file_path`, dan `completed` wajib punya. Kegagalan render ditangkap dan dicatat ke kolom `error` alih-alih melempar 500 atau menggantung. |
```

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Report/ReportController.php tests/Feature/Report/ReportTest.php docs/ARCHITECTURE.md
git rm app/Jobs/GenerateReportJob.php
git commit -m "feat: generate reports synchronously instead of on the queue"
```

---

### Task 2: Drop status polling from the reports list (frontend)

**Files:**
- Modify: `resources/js/pages/reports/index.tsx:1-107`

**Interfaces:**
- Consumes: `Props.exports: Paginated<ReportRow>` (unchanged shape — `status` field still exists and can still be `pending`/`processing`/`failed` for historical rows, just never for a freshly created one).
- Produces: no change to the component's exported default, no change to routes.

- [ ] **Step 1: Remove the polling effect and its trigger**

In `resources/js/pages/reports/index.tsx`, delete lines 91-107 (the `hasInFlight` computation and the `useEffect` polling block):

```tsx
    // Reports build on the queue, so a fresh row starts pending/processing. Poll
    // while any is still in flight so the download button appears on its own.
    const hasInFlight = exports.data.some(
        (row) => row.status === 'pending' || row.status === 'processing',
    );

    useEffect(() => {
        if (!hasInFlight) {
            return;
        }

        const timer = window.setInterval(() => {
            router.reload({ only: ['exports'] });
        }, 4000);

        return () => window.clearInterval(timer);
    }, [hasInFlight]);
```

- [ ] **Step 2: Remove the now-unused `useEffect` import**

Change line 3 from:

```tsx
import { useEffect, useState } from 'react';
```

to:

```tsx
import { useState } from 'react';
```

`router` (from `@inertiajs/react`, line 1) stays imported — it's still used by the delete button's `router.delete(...)` call.

- [ ] **Step 3: Type-check the frontend**

Run: `npm run build`
Expected: Build succeeds with no TypeScript errors (no unused-import or unused-variable errors for `useEffect`/`hasInFlight`).

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/reports/index.tsx
git commit -m "refactor: drop report status polling now that generation is synchronous"
```
