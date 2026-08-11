# Sync Report Generation

## Problem

Report generation (`GenerateReportJob`) runs on the `database` queue. Production/staging (`Dockerfile`) only runs php-fpm+nginx — no queue worker process exists in the deploy pipeline (`.github/workflows/deploy.yml`). Jobs never get consumed, so every report a user creates gets stuck at `pending` forever. The frontend (`resources/js/pages/reports/index.tsx`) polls every 4s waiting for a status that never changes.

## Decision

Drop the queue. Generate the report file synchronously inside the request that creates it — same request/response shape as any other datatable create action (student, company, etc.). No worker to deploy, no polling to maintain.

Trade-off accepted: a very large report (many hundreds of placements) will hold the HTTP request open for the duration of rendering. Current PKL scale (single school, per-department/company scoped) makes this acceptable; revisit with a queue only if report size becomes a real problem.

## Changes

**`app/Http/Controllers/Report/ReportController.php` — `store()`**
Build the dataset and render the file (PDF via dompdf / XLSX via Maatwebsite Excel) inline, using the same logic currently in `GenerateReportJob::handle()`. Create the `ReportExport` row already populated: `status`, `file_path`, `file_name`, `row_count`, `completed_at` set together. On a render exception, still create the row but with `status: Failed` and `error` set to the exception message (truncated), so the failure shows up in the report history instead of a raw 500 — mirrors the job's existing `failed()` behavior.

**`app/Jobs/GenerateReportJob.php`**
Delete. Its `handle()` body moves into the controller (or a small private method / the existing `ReportService` stays as-is, only the file-writing + status bookkeeping moves).

**`resources/js/pages/reports/index.tsx`**
Remove the `hasInFlight` computation and the polling `useEffect`. A row is always `completed` or `failed` by the time it's rendered — no `pending`/`processing` state will occur going forward. Keep the `STATUS_META` badge map as-is (harmless, and old historical rows may still carry those statuses).

**Not changed**
`ReportService::build()` stays untouched — it's format-agnostic and already takes a `ReportExport`. `ReportStatus`, `ReportFormat`, `ReportType` enums stay as-is. Policies/authorization unchanged.

## Testing

Update `tests/Feature/Report/ReportTest.php`: assertions that relied on `Queue::fake()` / asserting `GenerateReportJob` was dispatched must instead assert the response completes with the export row already `completed` and the file present on disk. Add a case for the failure path (force `ReportService::build` or the renderer to throw) asserting the row lands as `failed` with an error message, not a 500.
