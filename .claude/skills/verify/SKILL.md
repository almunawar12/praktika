---
name: verify
description: Use when verifying a change to Praktika by driving the real app — builds assets, serves the app, logs in as a seeded role, and captures screenshots + DOM observations with Playwright.
---

# Verifying Praktika in the real app

Praktika is Laravel + Inertia + React. The surface is a browser page, so
verification means logging in as a role and looking at the rendered page.

## Handle

```bash
npm run build                 # Inertia reads the built manifest; skip only if `npm run dev` is up
php artisan serve --port=8000 # run in background
```

`php artisan serve` alone is enough once assets are built — you do not need the
Vite dev server.

## Logging in

Seeded accounts (`database/seeders/UserSeeder.php`), password is `password` for
all of them in local:

| Role | Email |
|---|---|
| Super Admin | `superadmin@praktika.test` |
| Admin PKL | `admin@praktika.test` |
| Guru Pembimbing | `guru@praktika.test` |
| Pembimbing Industri | `industri@praktika.test` |
| Siswa | `siswa@praktika.test` |

Confirm what actually exists before driving:

```bash
php artisan tinker --execute="echo App\Models\User::role('admin-pkl')->first()?->email;"
```

The local DB (`db_praktika`) is normally seeded with placements, journals,
attendance and assessments. Attendance is seeded for *past* dates, so
"today"-scoped panels legitimately read zero.

## Driving with Playwright

Playwright is not a project dependency. Install it in the scratchpad, not here:

```bash
cd "$SCRATCHPAD" && npm init -y && npm install playwright && npx playwright install chromium
```

Login flow that works:

```js
await page.goto('http://localhost:8000/login', { waitUntil: 'networkidle' });
await page.fill('input[type="email"]', 'admin@praktika.test');
await page.fill('input[type="password"]', 'password');
await page.click('button[type="submit"]');
await page.waitForURL('**/dashboard');
await page.waitForLoadState('networkidle');
```

## Gotchas

- **Dark mode has no toggle yet.** `.dark` on `<html>` is the only hook
  (`@custom-variant dark (&:is(.dark *))` in `resources/css/app.css`);
  `next-themes` is present but only wired into the sonner toaster.
  `emulateMedia({colorScheme:'dark'})` alone does nothing. Stamp the class via
  `context.addInitScript` so components mount already-dark.
- **Recharts animates on mount (~1.5s).** Flipping a class or switching data
  restarts it. Screenshot too early and lines/arcs are missing — that is the
  animation, not a bug. Wait ~3s before capturing a chart.
- **Charts inside a grid need `min-w-0` on the cell.** A grid item defaults to
  `min-width:auto`, so recharts' measured width pushes the page wider than the
  viewport instead of shrinking. Always assert no horizontal overflow at 390px:
  ```js
  await page.evaluate(() => document.documentElement.scrollWidth >
      document.documentElement.clientWidth);
  ```
  To find the culprit, walk every element and report ones whose
  `getBoundingClientRect().right` exceeds `clientWidth`, deepest first.
- **Test tall UI at a short viewport (640–700px), not just 1000px+.** The sidebar
  is `fixed inset-y-0` and the menu is ~990px tall, so anything that pins to the
  viewport needs its own scroller — page scroll won't move it. Assert
  reachability, not just "is it clipped": scroll the element and check the last
  row lands inside `window.innerHeight`.
- **shadcn `CardTitle` is a `div`, not a heading** — `getByRole('heading')` finds
  only the `<h1>` from `AppLayout`. Query card titles by text.
- **Selects are Radix, not `<select>`** — drive them with
  `getByRole('combobox').click()` then `getByRole('option', {name}).click()`.

## Checks worth running

Collect `pageerror` and `console` errors — an Inertia prop shape mismatch shows
up there rather than on screen. Capture desktop (1536), mobile (390), and dark.
