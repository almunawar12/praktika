# Page → Modal Refactor Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ganti alur create/edit/view 8 menu dari halaman terpisah menjadi modal di atas index, dengan komponen reusable dan modal konfirmasi untuk hapus/create/edit.

**Architecture:** Komponen shell reusable (`FormModal`, `DetailModal`, `ConfirmDialog`) + hook `useResource` untuk fetch JSON saat modal dibuka. Controller `show`/`edit` diubah mengembalikan JSON; route `create` dihapus; `store`/`update` redirect ke index. Halaman `create.tsx`/`edit.tsx`/`show.tsx` tiap entity dihapus.

**Tech Stack:** Laravel 13 / PHP 8.3, Inertia.js + React 19 + TypeScript, Tailwind v4, Shadcn UI (Dialog/AlertDialog), Pest (PHP tests).

## Global Constraints

- Strict typing: PHP files mulai `declare(strict_types=1);`. TypeScript tanpa `any`.
- Form Request validation tetap dipakai; jangan pindah logika bisnis ke controller (tetap di Service).
- Otorisasi tiap action (`$this->authorize(...)`) dipertahankan persis.
- Standar JSON respons: `{ success: true, message: string, data: {...} }`.
- Mobile-first, responsive, accessible; pakai Tailwind + Shadcn, tanpa custom CSS/inline style.
- Bahasa UI: Indonesia (samakan dengan copy existing).
- Commit sering, per task. Pint + PSR-12 untuk PHP.
- Definition of Done: test wajib ada/lulus tiap task.

### Transformation Recipe (dipakai berulang tiap entity)

**A. Form → fields-only component**
- Rename `XForm` → `XFields`. Signature: `function XFields({ data, setData, errors }: FieldsProps<XValues>)`.
- Buang `<Form>`, `<Card>/<CardContent>`, tombol Simpan, dan link Batal (semua pindah ke `FormModal`).
- Tiap `<Input>`: ganti `defaultValue={values?.foo}` → `value={data.foo ?? ''}` + tambah `onChange={(e) => setData('foo', e.target.value)}`.
- Tiap `<Select>`: `value={data.foo}` + `onValueChange={(v) => setData('foo', v)}`.
- Pertahankan `<Field>` wrapper + tampilan `errors`.
- `FieldsProps<T>` didefinisikan di Task 3 (`FormModal`).

**B. Show page → detail-body component**
- Rename default-export page `XShow` → named `XDetail({ record }: { record: XRecord })`.
- Buang `AppLayout`, tombol "Kembali"/"Ubah" (chrome disediakan `DetailModal`; tombol Ubah jadi footer opsional via prop).
- Kembalikan hanya konten `<div>...</div>` bagian dalam.

**C. Controller**
- `show()`/`edit()`: ganti `Inertia::render(...)` → `response()->json(['success' => true, 'message' => 'OK', 'data' => [...]])`. Return type → `JsonResponse`. Muatan `load()`/`loadCount()` dipertahankan sama.
- Hapus method `create()`.
- `store()`/`update()`: redirect `->route('<prefix>.index')` (bukan `.show`). Flash tetap.
- `destroy()`: tidak berubah.

**D. Route**: tambah `->except('create')` pada resource (users: sudah `except('show')` → jadi `except(['create','show'])`).

**E. Index page**
- State: `const [modal, setModal] = useState<{ mode: 'create'|'edit'|'detail'; id?: string } | null>(null)`.
- Tombol "Tambah": `onClick={() => setModal({ mode: 'create' })}` (bukan `<Link>`).
- Kolom nama: tombol `onClick={() => setModal({ mode: 'detail', id: row.id })}` (bukan `<Link>`).
- `RowActions`: `onShow`/`onEdit` callback (Task 5).
- Render `<XFormModal>`, `<XDetailModal>` di bawah tabel, dikontrol `modal`.

**F. Hapus file**: `create.tsx`, `edit.tsx`, `show.tsx` entity tsb.

---

## Task 1: `ConfirmDialog` reusable + refactor `DeleteDialog`

**Files:**
- Create: `resources/js/components/shared/confirm-dialog.tsx`
- Modify: `resources/js/components/shared/delete-dialog.tsx`
- Test: `resources/js/components/shared/__tests__/confirm-dialog.test.tsx` (jika ada infra vitest; jika tidak, lihat Step 2)

**Interfaces:**
- Produces: `ConfirmDialog` props `{ open: boolean; onOpenChange: (o: boolean) => void; title: string; description: string; confirmLabel?: string; cancelLabel?: string; variant?: 'default' | 'destructive'; processing?: boolean; onConfirm: () => void }`.

- [ ] **Step 1: Cek infra test frontend**

Run: `cat package.json | grep -E "vitest|jest|@testing-library"`
Expected: tahu ada/tidaknya test runner React. Jika **tidak ada**, lewati langkah test frontend di seluruh plan (Task 1–14) dan andalkan test Pest + verifikasi `verify` skill. Jika **ada**, tulis test seperti Step 2.

- [ ] **Step 2 (jika ada test runner): Tulis test yang gagal**

```tsx
import { render, screen, fireEvent } from '@testing-library/react';
import { ConfirmDialog } from '../confirm-dialog';

test('memanggil onConfirm saat tombol konfirmasi diklik', () => {
    const onConfirm = vi.fn();
    render(
        <ConfirmDialog
            open
            onOpenChange={() => {}}
            title="Yakin?"
            description="Aksi ini permanen."
            confirmLabel="Lanjut"
            onConfirm={onConfirm}
        />,
    );
    fireEvent.click(screen.getByRole('button', { name: 'Lanjut' }));
    expect(onConfirm).toHaveBeenCalledOnce();
});
```

Run: `npx vitest run resources/js/components/shared/__tests__/confirm-dialog.test.tsx`
Expected: FAIL — module `../confirm-dialog` belum ada.

- [ ] **Step 3: Implementasi `ConfirmDialog`**

```tsx
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { cn } from '@/lib/utils';

type ConfirmDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description: string;
    confirmLabel?: string;
    cancelLabel?: string;
    variant?: 'default' | 'destructive';
    processing?: boolean;
    onConfirm: () => void;
};

export function ConfirmDialog({
    open,
    onOpenChange,
    title,
    description,
    confirmLabel = 'Konfirmasi',
    cancelLabel = 'Batal',
    variant = 'default',
    processing = false,
    onConfirm,
}: ConfirmDialogProps) {
    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{title}</AlertDialogTitle>
                    <AlertDialogDescription>{description}</AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel disabled={processing}>
                        {cancelLabel}
                    </AlertDialogCancel>
                    <AlertDialogAction
                        disabled={processing}
                        className={cn(
                            variant === 'destructive' &&
                                'bg-destructive text-white hover:bg-destructive/90',
                        )}
                        onClick={(event) => {
                            // Dialog tetap terbuka selama request berjalan; penutupan
                            // ditangani pemanggil lewat onOpenChange.
                            event.preventDefault();
                            onConfirm();
                        }}
                    >
                        {confirmLabel}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
```

- [ ] **Step 4: Refactor `DeleteDialog` jadi pemakai `ConfirmDialog`**

Ganti isi `delete-dialog.tsx` (pertahankan props publik agar `RowActions` tak berubah):

```tsx
import { router } from '@inertiajs/react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';

type DeleteDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Route to DELETE. */
    action: string;
    title?: string;
    description: string;
};

export function DeleteDialog({
    open,
    onOpenChange,
    action,
    title = 'Hapus data ini?',
    description,
}: DeleteDialogProps) {
    const [processing, setProcessing] = useState(false);

    return (
        <ConfirmDialog
            open={open}
            onOpenChange={onOpenChange}
            title={title}
            description={description}
            confirmLabel={processing ? 'Menghapus...' : 'Hapus'}
            variant="destructive"
            processing={processing}
            onConfirm={() => {
                setProcessing(true);
                router.delete(action, {
                    preserveScroll: true,
                    onFinish: () => {
                        setProcessing(false);
                        onOpenChange(false);
                    },
                });
            }}
        />
    );
}
```

- [ ] **Step 5: Verifikasi build + test**

Run: `npm run build` (dan `npx vitest run` jika ada). Expected: sukses / PASS.

- [ ] **Step 6: Commit**

```bash
git add resources/js/components/shared/confirm-dialog.tsx resources/js/components/shared/delete-dialog.tsx
git commit -m "feat: add reusable ConfirmDialog and refactor DeleteDialog onto it"
```

---

## Task 2: `useResource` fetch hook

**Files:**
- Create: `resources/js/hooks/use-resource.ts`

**Interfaces:**
- Produces: `useResource<T>(url: string | null): { data: T | null; loading: boolean; error: string | null }`. Fetch dijalankan saat `url` non-null; reset saat `url === null`. Endpoint mengembalikan `{ data: T }`; hook mengekstrak `.data`.

- [ ] **Step 1: Implementasi hook**

```ts
import { useEffect, useState } from 'react';

type State<T> = {
    data: T | null;
    loading: boolean;
    error: string | null;
};

export function useResource<T>(url: string | null): State<T> {
    const [state, setState] = useState<State<T>>({
        data: null,
        loading: false,
        error: null,
    });

    useEffect(() => {
        if (url === null) {
            setState({ data: null, loading: false, error: null });
            return;
        }

        let cancelled = false;
        setState({ data: null, loading: true, error: null });

        fetch(url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        })
            .then(async (res) => {
                if (!res.ok) {
                    throw new Error(`Gagal memuat data (${res.status}).`);
                }
                const body: { data: T } = await res.json();
                if (!cancelled) {
                    setState({ data: body.data, loading: false, error: null });
                }
            })
            .catch((err: Error) => {
                if (!cancelled) {
                    setState({
                        data: null,
                        loading: false,
                        error: err.message,
                    });
                }
            });

        return () => {
            cancelled = true;
        };
    }, [url]);

    return state;
}
```

- [ ] **Step 2: Typecheck**

Run: `npx tsc --noEmit`
Expected: tanpa error.

- [ ] **Step 3: Commit**

```bash
git add resources/js/hooks/use-resource.ts
git commit -m "feat: add useResource hook for lazy modal data fetch"
```

---

## Task 3: `FormModal` shell

**Files:**
- Create: `resources/js/components/shared/form-modal.tsx`

**Interfaces:**
- Consumes: `ConfirmDialog` (Task 1).
- Produces:
  - `type FieldsProps<T> = { data: T; setData: (key: keyof T, value: T[keyof T]) => void; errors: Partial<Record<keyof T, string>> }`
  - `FormModal<T>` props `{ open: boolean; onOpenChange: (o: boolean) => void; title: string; description?: string; action: string; method: 'post' | 'put'; initialData: T; submitLabel: string; requireConfirm?: boolean; confirmDescription?: string; children: (f: FieldsProps<T>) => React.ReactNode }`.
  - Perilaku: pakai Inertia `useForm(initialData)`. Klik Simpan → jika `requireConfirm` (default true) buka `ConfirmDialog` → submit `post`/`put` ke `action` dgn `onSuccess: () => onOpenChange(false)`. Reset saat modal ditutup.

- [ ] **Step 1: Implementasi `FormModal`**

```tsx
import { useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export type FieldsProps<T> = {
    data: T;
    setData: (key: keyof T, value: T[keyof T]) => void;
    errors: Partial<Record<keyof T, string>>;
};

type FormModalProps<T extends Record<string, unknown>> = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: string;
    action: string;
    method: 'post' | 'put';
    initialData: T;
    submitLabel: string;
    requireConfirm?: boolean;
    confirmDescription?: string;
    children: (fields: FieldsProps<T>) => ReactNode;
};

export function FormModal<T extends Record<string, unknown>>({
    open,
    onOpenChange,
    title,
    description,
    action,
    method,
    initialData,
    submitLabel,
    requireConfirm = true,
    confirmDescription = 'Pastikan data sudah benar sebelum disimpan.',
    children,
}: FormModalProps<T>) {
    const form = useForm<T>(initialData);
    const [confirming, setConfirming] = useState(false);

    const submit = () => {
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setConfirming(false);
                onOpenChange(false);
                form.reset();
            },
            onError: () => setConfirming(false),
        };
        if (method === 'post') {
            form.post(action, options);
        } else {
            form.put(action, options);
        }
    };

    const handleSave = () => {
        if (requireConfirm) {
            setConfirming(true);
        } else {
            submit();
        }
    };

    return (
        <>
            <Dialog
                open={open}
                onOpenChange={(next) => {
                    if (!next) form.reset();
                    onOpenChange(next);
                }}
            >
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>{title}</DialogTitle>
                        {description ? (
                            <DialogDescription>{description}</DialogDescription>
                        ) : null}
                    </DialogHeader>

                    <form
                        id="form-modal"
                        className="grid gap-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            handleSave();
                        }}
                    >
                        {children({
                            data: form.data,
                            setData: (key, value) => form.setData(key, value),
                            errors: form.errors as Partial<
                                Record<keyof T, string>
                            >,
                        })}
                    </form>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                            disabled={form.processing}
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            form="form-modal"
                            disabled={form.processing}
                        >
                            {form.processing ? (
                                <LoaderCircle
                                    className="size-4 animate-spin"
                                    aria-hidden="true"
                                />
                            ) : null}
                            {submitLabel}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={confirming}
                onOpenChange={setConfirming}
                title="Simpan data ini?"
                description={confirmDescription}
                confirmLabel={form.processing ? 'Menyimpan...' : 'Simpan'}
                processing={form.processing}
                onConfirm={submit}
            />
        </>
    );
}
```

- [ ] **Step 2: Typecheck + build**

Run: `npx tsc --noEmit && npm run build`
Expected: sukses.

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/shared/form-modal.tsx
git commit -m "feat: add reusable FormModal with confirm-before-save"
```

---

## Task 4: `DetailModal` shell

**Files:**
- Create: `resources/js/components/shared/detail-modal.tsx`

**Interfaces:**
- Produces: `DetailModal` props `{ open: boolean; onOpenChange: (o: boolean) => void; title: string; description?: string; loading: boolean; error: string | null; footer?: React.ReactNode; children: React.ReactNode }`. Saat `loading` tampilkan skeleton; saat `error` tampilkan pesan; else render `children`.

- [ ] **Step 1: Implementasi `DetailModal`**

```tsx
import { type ReactNode } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Skeleton } from '@/components/ui/skeleton';

type DetailModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: string;
    loading: boolean;
    error: string | null;
    footer?: ReactNode;
    children: ReactNode;
};

export function DetailModal({
    open,
    onOpenChange,
    title,
    description,
    loading,
    error,
    footer,
    children,
}: DetailModalProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    {description ? (
                        <DialogDescription>{description}</DialogDescription>
                    ) : null}
                </DialogHeader>

                {loading ? (
                    <div className="grid gap-3">
                        <Skeleton className="h-5 w-1/2" />
                        <Skeleton className="h-5 w-2/3" />
                        <Skeleton className="h-5 w-1/3" />
                    </div>
                ) : error ? (
                    <p className="text-sm text-destructive">{error}</p>
                ) : (
                    children
                )}

                {footer ? <DialogFooter>{footer}</DialogFooter> : null}
            </DialogContent>
        </Dialog>
    );
}
```

- [ ] **Step 2: Typecheck + build**

Run: `npx tsc --noEmit && npm run build`
Expected: sukses.

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/shared/detail-modal.tsx
git commit -m "feat: add reusable DetailModal with loading/error states"
```

---

## Task 5: `RowActions` → callback

**Files:**
- Modify: `resources/js/components/shared/row-actions.tsx`

**Interfaces:**
- Produces: `RowActions` props baru `{ onShow: () => void; onEdit: () => void; deleteHref: string; deleteDescription: string; canUpdate: boolean; canDelete: boolean }`. `onShow`/`onEdit` mengganti `showHref`/`editHref`.

- [ ] **Step 1: Ubah `RowActions`**

Ganti tipe props + isi menu Detail/Ubah dari `<Link href>` jadi `onSelect` callback:

```tsx
import { Eye, MoreHorizontal, Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { DeleteDialog } from '@/components/shared/delete-dialog';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

type RowActionsProps = {
    onShow: () => void;
    onEdit: () => void;
    deleteHref: string;
    deleteDescription: string;
    canUpdate: boolean;
    canDelete: boolean;
};

export function RowActions({
    onShow,
    onEdit,
    deleteHref,
    deleteDescription,
    canUpdate,
    canDelete,
}: RowActionsProps) {
    const [confirming, setConfirming] = useState(false);

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button variant="ghost" size="icon" className="size-8">
                        <MoreHorizontal className="size-4" aria-hidden="true" />
                        <span className="sr-only">Aksi</span>
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem onSelect={() => onShow()}>
                        <Eye className="size-4" aria-hidden="true" />
                        Detail
                    </DropdownMenuItem>

                    {canUpdate ? (
                        <DropdownMenuItem onSelect={() => onEdit()}>
                            <Pencil className="size-4" aria-hidden="true" />
                            Ubah
                        </DropdownMenuItem>
                    ) : null}

                    {canDelete ? (
                        <>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                variant="destructive"
                                onSelect={(event) => {
                                    event.preventDefault();
                                    setConfirming(true);
                                }}
                            >
                                <Trash2 className="size-4" aria-hidden="true" />
                                Hapus
                            </DropdownMenuItem>
                        </>
                    ) : null}
                </DropdownMenuContent>
            </DropdownMenu>

            <DeleteDialog
                open={confirming}
                onOpenChange={setConfirming}
                action={deleteHref}
                description={deleteDescription}
            />
        </>
    );
}
```

- [ ] **Step 2: Catatan**

Build akan gagal sampai semua index page (Task 6–14) memperbarui pemanggilan `RowActions`. Itu wajar — commit tetap dilakukan; build hijau dicek di Task 6 setelah entity pertama.

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/shared/row-actions.tsx
git commit -m "refactor: RowActions uses onShow/onEdit callbacks instead of hrefs"
```

---

## Task 6: Jurusan (departments) — TEMPLATE end-to-end

**Files:**
- Modify: `app/Http/Controllers/Master/DepartmentController.php`
- Modify: `routes/master.php:19-20`
- Modify: `resources/js/components/master/department-form.tsx`
- Create: `resources/js/components/master/department-detail.tsx`
- Modify: `resources/js/pages/master/departments/index.tsx`
- Delete: `resources/js/pages/master/departments/{create,edit,show}.tsx`
- Test: `tests/Feature/Master/DepartmentTest.php` (nama sesuaikan yang ada)

**Interfaces:**
- Consumes: `FormModal`/`FieldsProps` (Task 3), `DetailModal` (Task 4), `useResource` (Task 2), `RowActions` (Task 5).
- Produces: pola yang direplikasi Task 7–14.

- [ ] **Step 1: Cari test existing**

Run: `ls tests/Feature/Master/ && grep -rln "departments" tests/`
Expected: temukan file test departemen (mis. `DepartmentTest.php`).

- [ ] **Step 2: Tulis/ubah test yang gagal (backend JSON + redirect)**

Tambahkan/ubah di file test departemen:

```php
public function test_show_returns_json_for_modal(): void
{
    $admin = $this->actingAsAdmin();
    $department = Department::factory()->create();

    $this->getJson(route('master.departments.show', $department))
        ->assertOk()
        ->assertJsonPath('data.id', $department->id)
        ->assertJsonPath('data.name', $department->name);
}

public function test_edit_returns_json_for_modal(): void
{
    $this->actingAsAdmin();
    $department = Department::factory()->create();

    $this->getJson(route('master.departments.edit', $department))
        ->assertOk()
        ->assertJsonPath('data.code', $department->code);
}

public function test_store_redirects_to_index(): void
{
    $this->actingAsAdmin();

    $this->post(route('master.departments.store'), [
        'code' => 'RPL',
        'name' => 'Rekayasa Perangkat Lunak',
    ])->assertRedirect(route('master.departments.index'));
}

public function test_create_route_is_removed(): void
{
    $this->actingAsAdmin();
    $this->get('/master/departments/create')->assertNotFound();
}
```

Sesuaikan helper (`actingAsAdmin`) dengan konvensi test yang ada (cek file test lain).

- [ ] **Step 3: Jalankan test — pastikan gagal**

Run: `php artisan test --filter=Department`
Expected: FAIL (show/edit balikin Inertia HTML, store redirect ke show, create masih ada).

- [ ] **Step 4: Ubah controller**

Di `DepartmentController.php`: import `use Illuminate\Http\JsonResponse;`, hapus `use Inertia\Response;` jika tak terpakai lagi (index masih pakai `Response` → pertahankan importnya). Ubah:

```php
public function store(StoreDepartmentRequest $request): RedirectResponse
{
    $this->departments->create($request->validated());

    return redirect()
        ->route('master.departments.index')
        ->with('success', 'Jurusan berhasil ditambahkan.');
}

public function show(Department $department): JsonResponse
{
    $this->authorize('view', $department);

    $department->loadCount('schoolClasses');
    $department->load('schoolClasses:id,department_id,name');

    return response()->json([
        'success' => true,
        'message' => 'OK',
        'data' => $department,
    ]);
}

public function edit(Department $department): JsonResponse
{
    $this->authorize('update', $department);

    return response()->json([
        'success' => true,
        'message' => 'OK',
        'data' => $department,
    ]);
}

public function update(UpdateDepartmentRequest $request, Department $department): RedirectResponse
{
    $this->departments->update($department, $request->validated());

    return redirect()
        ->route('master.departments.index')
        ->with('success', 'Jurusan berhasil diperbarui.');
}
```

Hapus method `create()`.

- [ ] **Step 5: Ubah route**

`routes/master.php`:

```php
Route::resource('departments', DepartmentController::class)
    ->except('create')
    ->middleware('can:departments.viewAny');
```

- [ ] **Step 6: Jalankan test backend — hijau**

Run: `php artisan test --filter=Department`
Expected: PASS. Lalu `vendor/bin/pint app/Http/Controllers/Master/DepartmentController.php`.

- [ ] **Step 7: Ubah `department-form.tsx` → `DepartmentFields`** (Recipe A)

```tsx
import { Field } from '@/components/shared/field';
import type { FieldsProps } from '@/components/shared/form-modal';
import { Input } from '@/components/ui/input';

export type DepartmentValues = {
    code: string;
    name: string;
};

export function DepartmentFields({
    data,
    setData,
    errors,
}: FieldsProps<DepartmentValues>) {
    return (
        <>
            <Field id="code" label="Kode" error={errors.code} required>
                <Input
                    id="code"
                    value={data.code}
                    onChange={(e) => setData('code', e.target.value)}
                    autoFocus
                    required
                    maxLength={20}
                    aria-invalid={Boolean(errors.code)}
                />
            </Field>
            <Field id="name" label="Nama Jurusan" error={errors.name} required>
                <Input
                    id="name"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    required
                    maxLength={255}
                    aria-invalid={Boolean(errors.name)}
                />
            </Field>
        </>
    );
}
```

- [ ] **Step 8: Buat `department-detail.tsx`** (Recipe B — pindahkan JSX dari `show.tsx`)

```tsx
import { Badge } from '@/components/ui/badge';
import { DetailItem } from '@/components/shared/field';

export type DepartmentRecord = {
    id: string;
    code: string;
    name: string;
    school_classes_count: number;
    school_classes: { id: string; name: string }[];
};

export function DepartmentDetail({ record }: { record: DepartmentRecord }) {
    return (
        <div className="grid gap-4">
            <dl className="grid gap-4 sm:grid-cols-2">
                <DetailItem label="Kode">
                    <Badge variant="outline">{record.code}</Badge>
                </DetailItem>
                <DetailItem label="Nama">{record.name}</DetailItem>
                <DetailItem label="Jumlah Kelas">
                    {record.school_classes_count}
                </DetailItem>
            </dl>
            <div>
                <p className="mb-2 text-sm font-medium">Kelas</p>
                {record.school_classes.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Belum ada kelas pada jurusan ini.
                    </p>
                ) : (
                    <ul className="flex flex-wrap gap-2">
                        {record.school_classes.map((c) => (
                            <li key={c.id}>
                                <Badge variant="secondary">{c.name}</Badge>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </div>
    );
}
```

- [ ] **Step 9: Ubah `index.tsx`** (Recipe E)

Tambah import `useState`, `FormModal`, `DetailModal`, `useResource`, `DepartmentFields`, `DepartmentDetail`, `Button` (sudah), dan hapus import `Link`/`Plus` yang tak perlu (Plus tetap). Ganti kolom nama & actions & tombol tambah, tambah state + modal. Bagian inti:

```tsx
const [modal, setModal] = useState<
    { mode: 'create' | 'edit' | 'detail'; id?: string } | null
>(null);

const detailUrl =
    modal?.mode === 'detail' && modal.id
        ? `/master/departments/${modal.id}`
        : null;
const editUrl =
    modal?.mode === 'edit' && modal.id
        ? `/master/departments/${modal.id}/edit`
        : null;
const detail = useResource<DepartmentRecord>(detailUrl);
const edit = useResource<DepartmentValues & { id: string }>(editUrl);
```

Kolom nama:
```tsx
cell: (row) => (
    <button
        type="button"
        onClick={() => setModal({ mode: 'detail', id: row.id })}
        className="font-medium underline-offset-4 hover:underline"
    >
        {row.name}
    </button>
),
```

Kolom actions:
```tsx
cell: (row) => (
    <RowActions
        onShow={() => setModal({ mode: 'detail', id: row.id })}
        onEdit={() => setModal({ mode: 'edit', id: row.id })}
        deleteHref={`/master/departments/${row.id}`}
        deleteDescription={`Jurusan "${row.name}" akan dihapus. Jurusan yang masih memiliki kelas tidak dapat dihapus.`}
        canUpdate={can('departments.update')}
        canDelete={can('departments.delete')}
    />
),
```

Tombol tambah (di toolbar):
```tsx
{can('departments.create') ? (
    <Button onClick={() => setModal({ mode: 'create' })}>
        <Plus className="size-4" aria-hidden="true" />
        Tambah Jurusan
    </Button>
) : null}
```

Modal di bawah `<DataTablePagination>`:
```tsx
<FormModal
    open={modal?.mode === 'create'}
    onOpenChange={(o) => !o && setModal(null)}
    title="Tambah Jurusan"
    action="/master/departments"
    method="post"
    initialData={{ code: '', name: '' }}
    submitLabel="Simpan"
>
    {(f) => <DepartmentFields {...f} />}
</FormModal>

{edit.data ? (
    <FormModal
        open={modal?.mode === 'edit'}
        onOpenChange={(o) => !o && setModal(null)}
        title="Ubah Jurusan"
        action={`/master/departments/${edit.data.id}`}
        method="put"
        initialData={{ code: edit.data.code, name: edit.data.name }}
        submitLabel="Perbarui"
    >
        {(f) => <DepartmentFields {...f} />}
    </FormModal>
) : null}

<DetailModal
    open={modal?.mode === 'detail'}
    onOpenChange={(o) => !o && setModal(null)}
    title={detail.data?.name ?? 'Detail Jurusan'}
    loading={detail.loading}
    error={detail.error}
    footer={
        can('departments.update') && detail.data ? (
            <Button
                onClick={() =>
                    setModal({ mode: 'edit', id: detail.data!.id })
                }
            >
                Ubah
            </Button>
        ) : undefined
    }
>
    {detail.data ? <DepartmentDetail record={detail.data} /> : null}
</DetailModal>
```

> Catatan: `FormModal` edit di-mount hanya saat `edit.data` siap agar `initialData` terisi. `key` tidak wajib karena mount ditunda oleh guard `edit.data`.

- [ ] **Step 10: Hapus halaman lama**

```bash
git rm resources/js/pages/master/departments/create.tsx resources/js/pages/master/departments/edit.tsx resources/js/pages/master/departments/show.tsx
```

- [ ] **Step 11: Typecheck + build**

Run: `npx tsc --noEmit && npm run build`
Expected: sukses (semua referensi `RowActions` lama untuk departments sudah diperbarui; entity lain menyusul — jika build gagal karena entity lain, itu diselesaikan di task masing-masing; namun `departments/index.tsx` harus bersih).

- [ ] **Step 12: Verifikasi manual (skill verify)**

Jalankan skill `verify`: login admin → buka Jurusan → test create, edit, detail, hapus via modal + konfirmasi. Ambil screenshot.

- [ ] **Step 13: Commit**

```bash
git add -A
git commit -m "feat: departments create/edit/view as modals"
```

---

## Task 7: Kelas (classes)

**Files:**
- Modify: `app/Http/Controllers/Master/SchoolClassController.php`, `routes/master.php:22-24`
- Modify: `resources/js/components/master/school-class-form.tsx`
- Create: `resources/js/components/master/school-class-detail.tsx`
- Modify: `resources/js/pages/master/classes/index.tsx`
- Delete: `resources/js/pages/master/classes/{create,edit,show}.tsx`
- Test: file test kelas

**Entity specifics:**
- Route param: `class` (bukan `classes`). URL modal: detail `/master/classes/${id}`, edit `/master/classes/${id}/edit`.
- Form fields: `code`? cek `school-class-form.tsx` untuk daftar field pasti. Field select **Jurusan** (`department_id`) — opsi dari props `departments` (sudah dikirim index). `FormModal` initialData create: `{ name: '', department_id: '' }` (+ field lain sesuai form).
- Detail (`show`): `department:id,code,name` + `students_count`. `DetailModal` record type `{ id, name, department: {code,name}, students_count }`.
- Opsi `departments` (props index) diteruskan ke `SchoolClassFields` sebagai prop tambahan → perluas `FieldsProps` usage: `SchoolClassFields` menerima `{ ...fields, departments }`.

- [ ] **Step 1:** Baca `school-class-form.tsx` + file test kelas untuk daftar field & helper test persis.
- [ ] **Step 2:** Tulis test gagal (show/edit JSON, store redirect index, create 404) — pola Task 6 Step 2, ganti nama route `master.classes.*`, param `class`.
- [ ] **Step 3:** `php artisan test --filter=SchoolClass` → FAIL.
- [ ] **Step 4:** Controller (Recipe C): `show`/`edit`→JSON (pertahankan `load('department:id,code,name')`, `loadCount('students')` di show; `departmentOptions()` tak lagi dipakai `create` tapi masih dipakai `index` → biarkan). Hapus `create()`. `store`/`update` redirect `master.classes.index`.
- [ ] **Step 5:** Route: `->except('create')` sebelum `->middleware(...)` (pertahankan `->parameters(['classes' => 'class'])`).
- [ ] **Step 6:** `php artisan test --filter=SchoolClass` → PASS. Pint.
- [ ] **Step 7:** `SchoolClassFields` (Recipe A) + prop `departments: SelectOption[]`; select Jurusan pakai `Select` (`value={data.department_id}`, `onValueChange`).
- [ ] **Step 8:** `school-class-detail.tsx` (Recipe B) dari `show.tsx`.
- [ ] **Step 9:** `index.tsx` (Recipe E). Teruskan `departments` ke `SchoolClassFields`: `{(f) => <SchoolClassFields {...f} departments={departments} />}`.
- [ ] **Step 10:** `git rm` 3 halaman.
- [ ] **Step 11:** `npx tsc --noEmit && npm run build` → sukses.
- [ ] **Step 12:** Commit `feat: classes create/edit/view as modals`.

---

## Task 8: Guru (teachers)

**Files:** `TeacherController.php`, `routes/master.php:26-27`, `resources/js/components/master/teacher-form.tsx`, create `teacher-detail.tsx`, `resources/js/pages/master/teachers/index.tsx`, hapus 3 halaman, file test guru.

**Entity specifics:**
- Tak ada opsi select eksternal (form guru mandiri; cek `teacher-form.tsx` untuk field: name, nip, phone?, email, dll — email hanya saat create; `edit` memuat `user:id,email`).
- `show` JSON: `load('user:id,email,is_active,last_login_at')` → detail type `{ id, name, nip, ..., user: { email, is_active, last_login_at } }`.
- `edit` JSON: `load('user:id,email')`.
- **Perhatian:** field `email`/`password` biasanya hanya di create (akun dibuat saat store). `TeacherFields` terima prop `mode: 'create' | 'edit'` untuk menyembunyikan field yang tak relevan saat edit — cek `teacher-form.tsx` existing bagaimana ia bedakan (mungkin via `values` presence). Replikasikan logika itu memakai prop `mode`.

- [ ] **Step 1:** Baca `teacher-form.tsx` + test guru untuk daftar field pasti & aturan create-vs-edit.
- [ ] **Step 2–6:** Backend seperti Recipe C/D + test (route `master.teachers.*`). `store`/`update`→index.
- [ ] **Step 7:** `TeacherFields` (Recipe A) + prop `mode`. initialData create menyertakan `email`/`password`; edit tidak.
- [ ] **Step 8:** `teacher-detail.tsx` dari `show.tsx` (info guru + akun: email, status aktif, last_login_at).
- [ ] **Step 9:** `index.tsx` (Recipe E). Create `FormModal` pakai `mode="create"`; edit pakai `mode="edit"` + `initialData` dari `edit.data`.
- [ ] **Step 10–12:** `git rm`, build, commit `feat: teachers create/edit/view as modals`.

---

## Task 9: Perusahaan (companies)

**Files:** `CompanyController.php`, `routes/master.php:29-30`, `company-form.tsx`, create `company-detail.tsx`, `resources/js/pages/master/companies/index.tsx`, hapus 3 halaman, test perusahaan.

**Entity specifics:**
- Tak ada opsi select eksternal. `show`/`edit` tak ada `load` khusus (record apa adanya) — detail type = kolom Company (name, business_field, address, pic_name, pic_phone, quota, is_active, dll; cek model/`show.tsx`).
- `is_active` kemungkinan `Switch`/checkbox di form → `setData('is_active', checked)` (boolean).

- [ ] **Step 1:** Baca `company-form.tsx` + `companies/show.tsx` + test.
- [ ] **Step 2–6:** Backend Recipe C/D + test (`master.companies.*`).
- [ ] **Step 7:** `CompanyFields` (Recipe A); tangani boolean `is_active`.
- [ ] **Step 8:** `company-detail.tsx`.
- [ ] **Step 9:** `index.tsx` (Recipe E).
- [ ] **Step 10–12:** `git rm`, build, commit `feat: companies create/edit/view as modals`.

---

## Task 10: Pembimbing (supervisors)

**Files:** `CompanySupervisorController.php`, `routes/master.php:35-37`, `supervisor-form.tsx`, create `supervisor-detail.tsx`, `resources/js/pages/master/supervisors/index.tsx`, hapus 3 halaman, test pembimbing.

**Entity specifics:**
- Route param `supervisor`. Opsi `companies` dikirim di props index → teruskan ke `SupervisorFields`.
- `show` JSON: `load(['user:id,email,is_active,last_login_at','company:id,name,business_field'])`.
- `edit` JSON: `load('user:id,email')`. Field email/password create-only (akun dibuat saat store) → prop `mode` seperti Task 8.

- [ ] **Step 1:** Baca `supervisor-form.tsx` + `supervisors/show.tsx` + test.
- [ ] **Step 2–6:** Backend Recipe C/D + test (`master.supervisors.*`, param `supervisor`). Route pertahankan `->parameters(['supervisors' => 'supervisor'])` + tambah `->except('create')`.
- [ ] **Step 7:** `SupervisorFields` + prop `companies` + prop `mode`.
- [ ] **Step 8:** `supervisor-detail.tsx` (info + akun + perusahaan).
- [ ] **Step 9:** `index.tsx` (Recipe E), teruskan `companies`.
- [ ] **Step 10–12:** `git rm`, build, commit `feat: supervisors create/edit/view as modals`.

---

## Task 11: Siswa (students)

**Files:** `StudentController.php`, `routes/master.php:32-33`, `student-form.tsx`, create `student-detail.tsx`, `resources/js/pages/master/students/index.tsx`, hapus 3 halaman, test siswa.

**Entity specifics:**
- Opsi `classes` dikirim index (juga `departments` untuk filter). Teruskan `classes` ke `StudentFields`.
- `show` JSON: `load(['user:id,email,is_active,last_login_at','schoolClass:id,name,department_id','schoolClass.department:id,code,name'])`.
- `edit` JSON: `load('user:id,email')`. Field email/password create-only → prop `mode`.
- initialData create: `{ nis, nisn, name, phone, class_id, email, password }` (samakan dgn `student-form.tsx`).

- [ ] **Step 1:** Baca `student-form.tsx` + `students/show.tsx` + test siswa.
- [ ] **Step 2–6:** Backend Recipe C/D + test (`master.students.*`).
- [ ] **Step 7:** `StudentFields` + prop `classes` + prop `mode`.
- [ ] **Step 8:** `student-detail.tsx` (biodata + kelas/jurusan + akun).
- [ ] **Step 9:** `index.tsx` (Recipe E), teruskan `classes`.
- [ ] **Step 10–12:** `git rm`, build, commit `feat: students create/edit/view as modals`.

---

## Task 12: Penempatan (placements) — kompleks

**Files:** `app/Http/Controllers/Pkl/PlacementController.php`, `routes/pkl.php:26`, `placement-form.tsx`, `placement-actions.tsx` (reuse), create `placement-detail.tsx`, `resources/js/pages/pkl/placements/index.tsx`, hapus 3 halaman, test penempatan.

**Entity specifics / kehati-hatian:**
- `create` memuat opsi `students`, `companies(withQuota)`, `teachers` yang **tidak** dikirim ke index (index hanya kirim `companies`, `teachers`, `statuses` versi filter). Dua pilihan:
  1. **Rekomendasi:** tambahkan opsi `students` + `companies(withQuota:true)` versi form ke props `index` (mirip pola master lain), lalu reuse di FormModal. Perbarui `PlacementController::index` menyertakan `formOptions` (students, companiesWithQuota, teachers).
  2. Alternatif: endpoint JSON `create`/`edit` yang mengembalikan opsi — lebih banyak kerja. Pilih opsi 1.
- `show` mengembalikan `placement` + `remainingQuota` + `can` (transisi status). **Detail modal harus memuat blok aksi status** (`placement-actions.tsx`) yang memakai `can` + route `activate/complete/cancel`. Karena `show` kini JSON, sertakan `remainingQuota` & `can` dalam `data`: `{ placement, remainingQuota, can }`.
- `edit` memuat opsi form (students, companiesWithQuota, teachers) + `placement`. Kembalikan JSON `{ placement, options }`, atau reuse `formOptions` dari props index dan `edit` JSON cukup kirim `placement` saja. **Rekomendasi:** `edit` JSON kirim `placement` saja; opsi dari `formOptions` props index.

- [ ] **Step 1:** Baca `placement-form.tsx`, `placement-actions.tsx`, `pkl/placements/show.tsx`, `pkl/placements/index.tsx`, test penempatan.
- [ ] **Step 2:** Tulis test gagal: `show` JSON berisi `data.placement.id`, `data.can`, `data.remainingQuota`; `edit` JSON; `store`/`update` redirect `pkl.placements.index`; `create` 404.
- [ ] **Step 3:** `php artisan test --filter=Placement` → FAIL.
- [ ] **Step 4:** Controller:
  - `index`: tambah `'formOptions' => ['students' => $this->studentOptions(), 'companies' => $this->companyOptions(withQuota: true), 'teachers' => $this->teacherOptions()]`.
  - `show`→JSON `{ success, message, data: { placement, remainingQuota, can } }` (pertahankan `load([...])` + `remainingQuota` + `can` yang sudah ada).
  - `edit`→JSON `{ data: $placement }` (buang muatan opsi; opsi via `formOptions`).
  - hapus `create()`. `store`/`update` redirect `pkl.placements.index`.
- [ ] **Step 5:** Route `routes/pkl.php`: `Route::resource('placements', PlacementController::class)->except('create')...` (pertahankan route transisi status di bawahnya).
- [ ] **Step 6:** `php artisan test --filter=Placement` → PASS. Pint.
- [ ] **Step 7:** `PlacementFields` (Recipe A) + props `{ students, companies, teachers }` dari `formOptions`.
- [ ] **Step 8:** `placement-detail.tsx`: konten dari `show.tsx` (data siswa/perusahaan/guru + status badge) + render `<PlacementActions placement={...} can={...} remainingQuota={...} />` di dalam detail (atau di `footer` DetailModal). Karena aksi ubah status = navigasi/PUT terpisah, tetap pakai komponen `placement-actions.tsx` existing; sesuaikan sumber prop dari `detail.data`.
- [ ] **Step 9:** `index.tsx` (Recipe E) + teruskan `formOptions` ke `PlacementFields`. Detail modal pakai `useResource<{ placement, remainingQuota, can }>`.
- [ ] **Step 10:** `git rm` 3 halaman.
- [ ] **Step 11:** `npx tsc --noEmit && npm run build` → sukses.
- [ ] **Step 12:** Verifikasi skill `verify`: create draft, edit, detail + coba 1 transisi status, hapus draft.
- [ ] **Step 13:** Commit `feat: placements create/edit/view as modals`.

---

## Task 13: Pengguna (users) — kompleks

**Files:** `app/Http/Controllers/System/UserController.php`, `routes/system.php:25-37`, `resources/js/components/system/user-form.tsx`, create `resources/js/components/system/user-detail.tsx`, `resources/js/pages/system/users/index.tsx`, hapus `system/users/{create,edit}.tsx` (tak ada `show.tsx` — `show` sudah `except`), test pengguna.

**Entity specifics / kehati-hatian:**
- Route sudah `->except('show')`. Ubah jadi `->except(['show','create'])`. `edit` tetap ada (fetch JSON).
- `edit` mengembalikan `user` (row + phone) + `roles` + `hasProfile` + `can` (delete/toggleStatus/resetPassword/impersonate). `roles` juga dikirim index (`RoleName::cases()` untuk filter) tapi form butuh `UserService::ASSIGNABLE` → **teruskan `assignableRoles` lewat props index** (tambah di `index`) untuk create/edit form.
- `edit` JSON: `{ data: { user, hasProfile, can } }` (roles dari props index `assignableRoles`).
- `store` saat ini redirect ke `edit` (agar admin lanjut isi profil). Dgn modal: redirect ke `index` + flash; jika `hasProfile` false, admin bisa buka edit modal lagi. Ubah `store` redirect `system.users.index`.
- `update` sudah `back()` → aman (kembali ke index dgn modal tertutup). Boleh dipertahankan atau ganti ke `redirect()->route('system.users.index')`. **Pilih** `redirect()->route('system.users.index')` untuk konsisten.
- **Moderation actions** (toggle status, reset password, impersonate) tampil di edit page lama. Di modal: tempatkan di bagian bawah `FormModal` edit atau di `DetailModal`. Karena tak ada `show`, gunakan **edit modal** sebagai tempat aksi moderasi (section terpisah di bawah field), dikontrol `can`. Reuse komponen aksi existing bila ada (cek `system/users/edit.tsx`).
- Detail via klik nama: buat `DetailModal` read-only ringkas dari data row (name, email, role_label, status, last_login_at) — data sudah ada di row index, jadi **detail tak perlu fetch** (boleh `useResource` ke `edit` untuk phone/hasProfile jika mau lengkap). Rekomendasi: detail pakai data row langsung (tanpa fetch) untuk kesederhanaan.

- [ ] **Step 1:** Baca `user-form.tsx`, `system/users/edit.tsx` (untuk aksi moderasi + `can`), `system/users/create.tsx`, `system/users/index.tsx`, test pengguna.
- [ ] **Step 2:** Tulis test gagal: `edit` JSON `data.user.id` + `data.can`; `store` redirect `system.users.index`; `update` redirect index; `create` 404.
- [ ] **Step 3:** `php artisan test --filter=User` → FAIL (hati-hati filter juga match test lain; pakai nama kelas test spesifik).
- [ ] **Step 4:** Controller: `index` tambah `'assignableRoles' => $this->roleOptions(UserService::ASSIGNABLE)`. `edit`→JSON `{ data: { user: {...row, phone}, hasProfile, can } }`. hapus `create()`. `store`/`update` redirect `system.users.index`.
- [ ] **Step 5:** Route: `Route::resource('users', UserController::class)->except(['show', 'create']);`. Route moderasi tetap.
- [ ] **Step 6:** `php artisan test --filter=UserControllerTest` (nama tepat) → PASS. Pint.
- [ ] **Step 7:** `UserFields` (Recipe A) + props `roles` (dari `assignableRoles`) + `mode`. Create: name,email,phone,role,is_active,password. Edit: tanpa password (reset password lewat aksi moderasi).
- [ ] **Step 8:** `user-detail.tsx` read-only dari row (tanpa fetch).
- [ ] **Step 9:** `index.tsx` (Recipe E): create/edit `FormModal` + `assignableRoles`; edit modal sertakan section aksi moderasi (reuse dari `edit.tsx`) dikontrol `edit.data.can`. Detail modal pakai data row.
- [ ] **Step 10:** `git rm resources/js/pages/system/users/create.tsx resources/js/pages/system/users/edit.tsx`.
- [ ] **Step 11:** `npx tsc --noEmit && npm run build` → sukses.
- [ ] **Step 12:** Verifikasi skill `verify`: create user, edit, toggle status/reset password, detail, hapus.
- [ ] **Step 13:** Commit `feat: users create/edit/view as modals`.

---

## Task 14: Sapu bersih + verifikasi menyeluruh

**Files:** seluruh entity.

- [ ] **Step 1:** Grep sisa referensi halaman lama:

Run: `grep -rn "/create\|/edit\">" resources/js/pages/{master,pkl,system} ; grep -rln "Inertia::render.*\(create\|edit\|show\)" app/Http/Controllers/{Master,Pkl,System}`
Expected: kosong untuk 8 entity (selain modul lain di luar scope).

- [ ] **Step 2:** Cari komponen/rute yatim:

Run: `grep -rn "departments/create\|classes/create\|teachers/create\|companies/create\|students/create\|supervisors/create\|placements/create\|users/create" resources/js`
Expected: kosong.

- [ ] **Step 3:** Full test suite:

Run: `php artisan test`
Expected: semua PASS. Jika ada test lama yang assert Inertia page create/edit/show untuk 8 entity, perbaiki di sini.

- [ ] **Step 4:** Build + typecheck akhir:

Run: `npx tsc --noEmit && npm run build && vendor/bin/pint --test`
Expected: sukses.

- [ ] **Step 5:** Commit:

```bash
git add -A
git commit -m "chore: remove orphaned page routes and finalize modal refactor"
```

---

## Catatan Eksekusi

- Task 1–5 (komponen reusable) wajib selesai dan hijau sebelum Task 6.
- Task 6 (Jurusan) adalah template; setelah lolos verify, Task 7–11 mekanis mengikuti pola sama.
- Task 12 (Placements) & 13 (Users) paling berisiko — kerjakan setelah pola stabil; alokasikan review ekstra.
- Setiap entity: backend test dulu (TDD), lalu frontend, lalu verify.
