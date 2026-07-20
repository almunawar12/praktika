# Design: Page → Modal Refactor untuk Menu Master & Sistem

**Tanggal:** 2026-07-20
**Status:** Disetujui (design), menunggu review spec

## Ringkasan

Ubah alur create / edit / view pada 8 menu dari halaman terpisah menjadi modal
di atas halaman index. Hapus halaman lama. Sediakan komponen reusable dan modal
konfirmasi untuk aksi hapus, create, dan edit.

**Menu terdampak:**

1. Jurusan — `master/departments`
2. Kelas — `master/classes`
3. Siswa — `master/students`
4. Guru — `master/teachers`
5. Perusahaan — `master/companies`
6. Pembimbing — `master/supervisors`
7. Penempatan — `pkl/placements`
8. Pengguna — `system/users`

## Keputusan Kunci

- **Data loading:** Fetch JSON saat modal dibuka. Endpoint `show`/`edit` tiap
  controller diubah mengembalikan JSON, bukan `Inertia::render`. Payload index
  tetap ringan; data detail/edit selalu fresh.
- **Deep link:** Tidak dipertahankan. Route + halaman `create`/`edit`/`show`
  dihapus. Semua akses lewat modal dari index.
- **Opsi select** (classes, departments, companies, dll) sudah dikirim ke props
  index untuk filter — dipakai ulang untuk form create/edit tanpa fetch tambahan.

## Arsitektur Frontend

### Komponen reusable baru — `resources/js/components/shared/`

**`ConfirmDialog`** (generalisasi `delete-dialog.tsx` yang ada)

Modal konfirmasi generik untuk semua aksi. Props:

- `open: boolean`
- `onOpenChange: (open: boolean) => void`
- `title: string`
- `description: string`
- `confirmLabel?: string` (default "Konfirmasi")
- `variant?: 'default' | 'destructive'` (default `'default'`)
- `processing?: boolean`
- `onConfirm: () => void`

Berbasis `AlertDialog`. Menjaga dialog tetap terbuka selama request berjalan.
`DeleteDialog` yang ada di-refactor menjadi pemakai tipis `ConfirmDialog`
(`variant="destructive"`) agar `RowActions` tidak berubah antarmukanya.

**`FormModal`** — shell Dialog untuk create/edit

- Props: `open`, `onOpenChange`, `title`, `description?`, `action` (URL),
  `method: 'post' | 'put'`, `initialData`, `submitLabel`, `requireConfirm?`
  (default `true`), `confirmDescription?`, `children` (render-prop).
- Memakai Inertia `useForm(initialData)`.
- `children` adalah render-prop menerima `{ data, setData, errors, processing }`
  sehingga field per-entity hanya fokus pada input.
- Alur submit: klik Simpan → buka `ConfirmDialog` ("Yakin simpan data ini?") →
  `onConfirm` menjalankan `form.submit`/`form.post`/`form.put` ke `action`.
- Sukses (redirect index) → modal tutup, form reset. Error validasi → modal
  tetap terbuka, `errors` tampil pada field.
- Body scrollable (`max-h` + overflow) untuk form panjang; mobile-first.

**`DetailModal`** — shell Dialog untuk view

- Props: `open`, `onOpenChange`, `title`, `description?`, `children`.
- Menampilkan skeleton/loading saat data belum tersedia, lalu render `children`.

**`useResource<T>(url: string | null)`** hook — `resources/js/hooks/`

- Fetch JSON (`fetch` dengan header `Accept: application/json`,
  `X-Requested-With`) ketika `url` non-null (mis. saat modal dibuka).
- Return `{ data: T | null, loading: boolean, error: string | null }`.
- Reset saat `url` berubah ke `null` (modal ditutup).

### Perubahan per-entity (frontend)

Untuk setiap dari 8 entity:

- `*-form.tsx` diubah dari komponen ber-`<Form>` sendiri menjadi **fields-only**
  yang membaca `data/setData/errors` dari render-prop `FormModal`.
- Isi `show.tsx` dipindah menjadi **detail body** yang dirender dalam
  `DetailModal` (menerima record hasil fetch).
- `index.tsx` memegang state modal: `mode: 'create' | 'edit' | 'detail' | null`
  dan `activeId`. Tombol "Tambah" dan `RowActions` memanggil callback pembuka
  modal, bukan `Link` navigasi. Kolom nama yang tadinya `Link` ke `show`
  menjadi tombol pembuka `DetailModal`.
- **File dihapus:** `create.tsx`, `edit.tsx`, `show.tsx` tiap entity.

### Perubahan `RowActions` — `resources/js/components/shared/row-actions.tsx`

Antarmuka `*Href` diganti callback:

- `onShow: () => void`, `onEdit: () => void`, dan aksi hapus tetap memakai
  `ConfirmDialog` internal (via `deleteHref` + `deleteDescription`).
- `canUpdate` / `canDelete` tetap.

## Arsitektur Backend

Untuk setiap dari 8 controller:

- `show()` → return `JsonResponse` berisi record + relasi yang dibutuhkan detail
  (data yang tadinya di-`load` tetap sama), mengikuti standar JSON proyek
  (`{ success, message, data }`).
- `edit()` → return `JsonResponse` berisi record + relasi yang dibutuhkan form
  (mis. `user:id,email` pada Student).
- `create()` GET → **dihapus** (opsi sudah tersedia di props index).
- `store()` / `update()` → redirect ke **`.index`** (bukan `.show`) dengan flash
  `success`. Inertia reload index → tabel terbarui, modal tutup, toast tampil.
- `destroy()` → tetap (sudah redirect index).

### Route

Untuk tiap entity (`routes/` — resource routes):

- Hapus route `create`.
- `show` & `edit` tetap nama & URL-nya, tetapi kini melayani JSON.
- `store`, `update`, `destroy`, `index` tetap.

Cek eksplisit tiap definisi route saat implementasi — beberapa mungkin memakai
`Route::resource(...)->except([...])`.

## Data Flow

**Create:** klik Tambah → `FormModal` (initialData kosong, opsi dari props index)
→ Simpan → `ConfirmDialog` → POST `store` → redirect index → reload + toast.

**Edit:** klik Ubah di `RowActions` → `FormModal` buka → `useResource('/…/{id}/edit')`
fetch record → isi form → Simpan → `ConfirmDialog` → PUT `update` → redirect index.

**Detail:** klik nama / "Detail" → `DetailModal` buka →
`useResource('/…/{id}')` fetch record + relasi → render detail body.

**Hapus:** `RowActions` → `ConfirmDialog` (`variant="destructive"`) →
`router.delete` → redirect index + toast.

## Error Handling

- Fetch gagal (`useResource`): tampilkan pesan error dalam modal + tombol tutup.
- Validasi gagal: `useForm` mengisi `errors`, modal tetap terbuka.
- Hapus gagal karena resource in-use: flash error dari backend tampil sebagai
  toast (perilaku existing dipertahankan).
- Request in-flight: tombol disabled + label "Menyimpan…/Menghapus…".

## Testing

- **Feature test** yang meng-hit route `create` sebagai Inertia page → dihapus.
- **Feature test** `show`/`edit` → assert respons JSON (`assertJson`) alih-alih
  `Inertia` page. Assert struktur data yang dibutuhkan modal.
- **Feature test** `store`/`update` → assert redirect ke `index` (bukan `show`).
- **Frontend**: bila ada test komponen, tambah/ubah untuk `ConfirmDialog`,
  `FormModal`, `DetailModal` sesuai infrastruktur test yang ada.
- Verifikasi manual via skill `verify` untuk minimal 1 entity (Jurusan) sebagai
  smoke test alur create/edit/detail/hapus di app nyata.

## Yang Tidak Termasuk (YAGNI)

- Tidak menambah library modal Inertia (momentum-modal dsb).
- Tidak ada URL deep-link / state modal di query string.
- Tidak mengubah logika bisnis Service/Action/Repository.
- Tidak refactor menu di luar 8 yang disebut.

## Urutan Implementasi

Kerjakan **Jurusan (departments)** dulu end-to-end sebagai template (komponen
reusable + backend + test), verifikasi, lalu terapkan pola sama ke 7 entity
sisanya. Komponen reusable ditulis sekali di langkah pertama.
