# Admin Dashboard Overview — Design

**Date:** 2026-07-16
**Status:** Approved for planning

## Goal

Rework the dashboard page for Admin PKL and Super Admin to match the approved reference layout: six stat cards with trend sub-labels, a multi-series activity line chart, a today-attendance donut, pending-journal and recent-assessment lists, and active-placement distribution per jurusan. Other roles (Guru Pembimbing, Pembimbing Industri, Siswa) keep the current dashboard unchanged.

## Decisions

- **Scope:** rich layout for Admin PKL and Super Admin only.
- **Agenda & Pengingat section from the reference:** skipped — no data source exists; may become a separate module later.
- **Trend sub-labels:** computed from real data (no hardcoded copy).
- **Chart library:** recharts through the shadcn/ui chart component (theme-aware, idiomatic for the stack). New dependency: `recharts`.
- **Header date picker and notification bell in the reference are topbar concerns, out of scope for this page.**

## Backend

`DashboardService::forUser()` gains an `overview` key, populated only when the user's role is Admin PKL or Super Admin; `null` otherwise. Existing `stats`/`recentActivities` payload stays for other roles. All queries remain `visibleTo($user)` scoped.

### `overview.stats` — six cards

| Card | Value | Sub-label |
|---|---|---|
| Total Siswa PKL | total students (`Student::count()`, matching the existing service) | `+N dari bulan lalu` (students created since start of current month) |
| Total Perusahaan | company count | `+N dari bulan lalu` (same rule) |
| PKL Aktif | active placements | `X% dari total` placements |
| PKL Selesai | completed placements | `X% dari total` placements |
| Jurnal Pending | journals with status pending | `Perlu verifikasi` (static, red accent) |
| Kehadiran Hari Ini | present today (excl. rejected verification) | `X% hadir` of active placements |

Each stat: `label`, `icon`, `value`, `subLabel`, `subVariant` (`positive` / `warning` / `muted`).

### `overview.activityChart`

Daily counts for the last 30 days (frontend filters to 7/14/30): series `absensi` (attendance records), `jurnal` (journals by date), `monitoring` (visit_date), `penilaian` (assessments submitted_at). One grouped query per series. Shape: `[{ date, absensi, jurnal, monitoring, penilaian }]`, zero-filled for missing days.

### `overview.attendanceToday`

`{ hadir, izin, sakit, alpha, total, rate }`. `hadir`/`izin`/`sakit` from today's attendance rows by status (rejected verification excluded). `alpha` = active placements with no attendance row today. `total` = active placements. `rate` = hadir / total (0 when total is 0).

### `overview.pendingJournals`

Latest 4 pending journals: student name, class name, company name, submitted-at ISO timestamp (frontend renders relative time).

### `overview.recentAssessments`

Latest 4 submitted assessments: student name, company name, type label (`Industri` / `Guru`), score.

### `overview.activeByDepartment`

Active placements grouped by student's department: `{ name, count, percentage }`, sorted by count desc. Percentage of total active placements.

## Frontend

`resources/js/pages/dashboard.tsx` branches: when `overview` is present render the rich layout, otherwise the existing layout untouched.

New components in `resources/js/components/dashboard/`:

- `overview-stat-card.tsx` — colored icon tile, value, trend sub-label.
- `activity-chart.tsx` — shadcn LineChart, 4 series with legend, range select (7/14/30 hari) filtering client-side.
- `attendance-donut.tsx` — donut with center total, status legend with counts + percentages, attendance-rate banner.
- `pending-journals-card.tsx` — list with initials avatar, class, company, relative time, "Lihat semua" link to journals index.
- `recent-assessments-card.tsx` — list with type badge and score, link to assessments index.
- `department-progress-card.tsx` — progress bars per jurusan with percentages and total footer.

Layout (mobile-first): greeting header (`Selamat datang, {nama} 👋` + one-line description); stat grid `2 / 3 / 6` columns; row of chart (2/3 width) + donut (1/3); row of three list cards; all stacking to one column on mobile. Shadcn + Tailwind only, no custom CSS.

## Error handling

- Empty states per card: chart and donut render an empty message when all counts are zero; lists show "Tidak ada data" placeholder.
- Division-by-zero guarded (rates/percentages return 0).

## Testing

- Feature: dashboard for Admin PKL and Super Admin includes `overview` with correct shape; Guru/Industri/Siswa payload has `overview: null` and keeps existing keys.
- Feature/unit: attendanceToday alpha derivation (active placement without today's record counts as alpha); month-delta sub-label counts; activityChart zero-fill.
