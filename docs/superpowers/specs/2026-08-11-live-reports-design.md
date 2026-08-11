# Laporan Live — Filter, Bukan Generate

**Tanggal:** 2026-08-11
**Status:** Disetujui, siap direncanakan

## Masalah

Modul Laporan sekarang berkonsep *generate*: pengguna memilih tipe, format, dan
filter, menekan tombol, lalu sistem membangun file, menyimpannya ke disk, dan
mencatat satu baris di `report_exports`. Hasilnya baru bisa dilihat setelah
diunduh. Ini menambah langkah, menumpuk file di storage, dan menyimpan riwayat
yang tidak dipakai siapa pun.

Laporan di sistem ini seluruhnya berupa rekap dari data yang sudah ada. Tidak ada
alasan menunggu proses: rekap bisa dihitung saat halaman dibuka.

## Tujuan

Laporan langsung tersedia. Pengguna memilih tipe lewat sidebar, menyetel filter,
dan tabel hasilnya tampil di layar. Unduhan menjadi tindakan opsional yang
menstreaming file sesuai filter aktif, tanpa menyimpan apa pun.

## Bukan Tujuan

- Menambah tipe laporan baru. Kelima tipe yang ada tetap.
- Menjadikan kolom agregat bisa diurutkan. Urutan tetap nama siswa A–Z.
- Menjadwalkan atau mengirim laporan otomatis.

## Keputusan

| Pertanyaan | Keputusan |
| --- | --- |
| Yang tampil setelah filter | Tabel data langsung, ter-paginate 15/halaman |
| Riwayat & file tersimpan | Dibuang seluruhnya; tabel `report_exports` di-drop |
| Rekap Siswa | Kartu ringkasan di layar, tombol unduh PDF |
| Routing | Satu route per tipe, filter sebagai query string |
| Filter | Periode, jurusan, perusahaan, siswa, **kelas**, **guru pembimbing**, **cari nama/NIS** |
| Default tanpa filter | Rentang tahun ajaran aktif |
| Sorting | Nama siswa saja |

## Arsitektur

### Routing

`routes/reports.php` menjadi read-only:

```php
Route::middleware('auth')->name('reports.')->prefix('reports')->group(function (): void {
    Route::get('/', [ReportController::class, 'index'])->name('index');
    Route::get('{type}', [ReportController::class, 'show'])->name('show');
    Route::get('{type}/download', [ReportController::class, 'download'])->name('download');
});
```

`{type}` terikat implisit ke enum `ReportType`, sehingga nilai di luar enum
menghasilkan 404 tanpa kode tambahan. `index` hanya me-redirect ke tipe pertama
yang boleh diakses pengguna; kalau tidak ada satu pun, 403.

### Otorisasi

`ReportPolicy` menggantikan `ReportExportPolicy`, didaftarkan lewat
`Gate::policy(ReportType::class, ReportPolicy::class)`. Laravel meresolusi policy
dari kelas objek, jadi enum bisa menjadi target policy dan controller memanggil
`$this->authorize('view', $type)` seperti biasa.

- `viewAny(User)` — punya minimal satu izin `reports.*`. Dipakai `index`.
- `view(User, ReportType)` — punya `reports.{type}`. Dipakai `show` dan `download`.

Katalog izin (`PermissionCatalog`) tidak berubah. Kepemilikan per-baris hilang
karena tidak ada lagi artefak yang dimiliki siapa pun; pembatasan data sekarang
sepenuhnya lewat `Placement::visibleTo($user)`, yang memang sudah jadi mekanisme
pembatas sebenarnya.

### Filter

`App\DataTransferObjects\ReportFilters` — readonly, dibangun oleh
`fromRequest(Request $request, ReportType $type)`:

| Properti | Sumber query | Berlaku untuk |
| --- | --- | --- |
| `dateFrom`, `dateTo` | `date_from`, `date_to` | semua tipe |
| `departmentId` | `department_id` | semua tipe |
| `companyId` | `company_id` | semua tipe |
| `schoolClassId` | `school_class_id` | semua tipe |
| `teacherId` | `teacher_id` | semua tipe |
| `search` | `search` | empat tipe tabel |
| `studentId` | `student_id` | `student_summary` |
| `page` | `page` | empat tipe tabel |

Kalau `dateFrom` dan `dateTo` kosong, keduanya diisi dari rentang tahun ajaran
aktif (`AcademicYearService`). Kalau tidak ada tahun ajaran aktif, rentang
dibiarkan kosong dan laporan mencakup seluruh periode.

DTO ini menggantikan `$export->filters` di seluruh `ReportService`.

`App\Http\Requests\Report\ReportFilterRequest` memvalidasi query string:
tanggal berformat sah, `date_to` tidak mendahului `date_from`, setiap id
berupa UUID yang ada di tabelnya, `search` maksimal 100 karakter. Menggantikan
`StoreReportRequest`.

### Service

`ReportService` mendapat tiga entry point publik yang berbagi satu row-builder:

```php
public function paginate(ReportType $type, ReportFilters $filters, User $user): LengthAwarePaginator;
public function dataset(ReportType $type, ReportFilters $filters, User $user): ReportDataset;
public function studentSummary(ReportFilters $filters, User $user): ?StudentSummaryDataset;
```

- `paginate()` mem-paginate `scopedPlacements()` 15 per halaman, lalu menjalankan
  agregasi hanya untuk placement di halaman itu. Query agregat yang ada
  (`countByStatus`, `monitoringRows`) sudah membatasi diri dengan
  `whereIn('placement_id', …)`, jadi tinggal diberi koleksi yang lebih kecil.
- `dataset()` mengambil seluruh placement dalam scope untuk unduhan.
- `studentSummary()` mengembalikan `null` bila siswa belum dipilih atau tidak
  punya penempatan aktif/selesai dalam scope pengguna — bukan melempar exception,
  karena keduanya adalah keadaan normal di layar.

Semua method privat yang sekarang menerima `ReportExport $export` diubah menerima
`ReportFilters $filters` dan `User $user`: `scopedPlacements`, `applyDateRange`,
`filter`, `describeScope`, `countByStatus`, dan seluruh `*Rows`.

`scopedPlacements()` menambah tiga kondisi baru: `school_class_id` pada siswa,
`teacher_id` pada penempatan, dan `search` sebagai `ILIKE` terhadap nama siswa
atau NIS.

`ReportDataset` dan `StudentSummaryDataset` tidak berubah bentuknya, sehingga
Blade PDF dan `ReportSpreadsheet` tetap dipakai apa adanya.

### Unduhan

`download()` memvalidasi filter dengan request yang sama, mengotorisasi tipe,
lalu menstreaming hasil `dataset()` (atau `studentSummary()`) dengan
`response()->streamDownload()`. Tidak ada file yang ditulis ke disk. Format
diambil dari `?format=pdf|xlsx`; `student_summary` selalu PDF. Nama berkas tetap
`{slug-tipe}-{Ymd-His}.{ext}`.

## Alur Data

```
GET /reports/attendance?date_from=…&department_id=…
  → ReportController::show
  → authorize('view', $type)
  → ReportFilters::fromRequest
  → ReportService::paginate
  → Inertia::render('reports/show', [type, filters, filterOptions, columns, rows, meta])

GET /reports/attendance/download?<query yang sama>&format=xlsx
  → ReportController::download
  → authorize('view', $type)
  → ReportService::dataset
  → activity()->log('report.download')
  → streamDownload(Excel::raw(...))
```

## UI

`resources/js/pages/reports/index.tsx` (468 baris; berisi form generate dan tabel
riwayat) diganti `pages/reports/show.tsx` yang tipis, ditopang tiga komponen di
`resources/js/components/reports/`:

**`report-filter-bar.tsx`** — periode, jurusan, kelas, perusahaan, guru, dan
kotak cari (debounce 300 ms); picker siswa muncul khusus `student_summary`.
Field yang ditampilkan ditentukan per tipe. Setiap perubahan memanggil
`router.get(route('reports.show', type), params, { preserveState: true,
preserveScroll: true, replace: true })`, sehingga URL selalu mencerminkan filter
dan bisa di-bookmark. Tombol Reset mengembalikan default tahun ajaran aktif.

**`report-table.tsx`** — tabel generik: header dari `ReportType::columns()`,
baris berupa array string. Pagination 15/halaman memakai `TableRequest` yang
sudah ada. Kosong → "Tidak ada data untuk filter ini." Di layar sempit tabel
menggulir horizontal di dalam pembungkus `overflow-x-auto`.

**`student-summary-card.tsx`** — header identitas (nama, NIS, kelas, perusahaan,
guru, periode), tiga panel grid (Absensi, Jurnal, Nilai), lalu daftar kunjungan
monitoring. Sebelum siswa dipilih: "Pilih siswa untuk melihat rekap."

Toolbar atas memuat judul tipe dan tombol unduh: `Unduh PDF` + `Unduh Excel`
untuk empat tipe tabel, `Unduh PDF` saja untuk Rekap Siswa (nonaktif sampai siswa
dipilih). Tombol unduh berupa anchor biasa ke route download dengan query filter
aktif, bukan kunjungan Inertia, agar browser yang menangani berkas.

`SidebarBuilder` berubah dari `route: 'reports.index', query: ['type' => …]`
menjadi `route: 'reports.show', params: ['type' => …]`. Item dan izinnya tetap.

## Yang Dihapus

- `app/Models/ReportExport.php`
- `app/Policies/ReportExportPolicy.php`
- `app/Http/Requests/Report/StoreReportRequest.php`
- `app/Enums/ReportStatus.php`
- `ReportController::store()` dan `destroy()` beserta route-nya
- Form generate dan tabel riwayat di UI

Tetap dipakai: `ReportFormat` (parameter unduh, `extension()`, `contentType()`),
`ReportType`, `ReportDataset`, `StudentSummaryDataset`, `ReportSpreadsheet`, dan
kedua Blade di `resources/views/reports/`.

## Migrasi

Migrasi baru `drop_report_exports_table`:

- `up()` — drop tabel `report_exports`, lalu
  `Storage::disk('local')->deleteDirectory('reports')`.
- `down()` — bangun ulang tabel persis skema lama, termasuk keempat CHECK
  constraint, dengan `student_summary` sudah termasuk dalam daftar tipe.

Migrasi reversibel dari sisi skema. File PDF/Excel lama hilang permanen dan tidak
dikembalikan oleh `down()`; ini konsekuensi yang diterima, karena setiap laporan
kini bisa dibangkitkan ulang dari filternya kapan saja. Migrasi
`2026_08_11_000001_add_student_summary_to_report_exports_type_check` dibiarkan
apa adanya; urutan rollback tetap konsisten karena `down()` di atas berjalan
lebih dulu.

## Audit

Tidak ada model yang bisa memakai `LogsActivity`, jadi unduhan dicatat manual di
`download()`:

```php
activity()
    ->causedBy($user)
    ->withProperties(['type' => $type->value, 'format' => $format->value, 'filters' => $filters->toArray()])
    ->log('report.download');
```

Menampilkan tabel di layar tidak dicatat — itu operasi baca biasa, sama seperti
halaman daftar lain di aplikasi.

## Penanganan Kesalahan

Status `failed` dan kolom `error` hilang bersama tabel. Penggantinya validasi di
depan: `ReportFilterRequest` menolak filter tidak sah dengan pesan per-field yang
biasa. Rekap Siswa tanpa `student_id`, atau dengan siswa yang tidak punya
penempatan aktif/selesai dalam scope pengguna, menghasilkan empty state di layar,
bukan exception.

## Testing

`tests/Feature/Report/ReportTest.php` dan `StudentSummaryReportTest.php` ditulis
ulang:

- `index` me-redirect ke tipe pertama yang diizinkan; siswa (tanpa izin report) mendapat 403
- setiap tipe menolak pengguna tanpa izin tipe itu dengan 403; tipe di luar enum menghasilkan 404
- scoping: guru hanya melihat siswa bimbingannya, pembimbing industri hanya perusahaannya
- setiap filter (periode, jurusan, kelas, perusahaan, guru, cari) benar-benar mempersempit baris
- tanpa query, periode default sama dengan rentang tahun ajaran aktif
- pagination 15/halaman, dan agregat di halaman kedua milik baris halaman kedua
- unduh PDF dan Excel: 200, `Content-Type` sesuai, mencakup seluruh baris (bukan hanya halaman aktif), dan tercatat di activity log
- Rekap Siswa: tanpa `student_id` menghasilkan empty state; siswa di luar scope tidak ditemukan; PDF terunduh

## Risiko

**Beban query per halaman.** Empat tipe tabel menjalankan satu query placement
plus satu-dua query agregat per pemuatan halaman. Karena agregat dibatasi ke 15
placement halaman aktif, bebannya sebanding dengan halaman daftar lain di
aplikasi.

**Unduhan pada scope lebar.** `dataset()` memuat seluruh placement dalam scope,
sama seperti perilaku generate sekarang, hanya saja kini berjalan dalam siklus
request. Rentang default tahun ajaran aktif membatasinya. Kalau suatu saat
unduhan melewati batas waktu request, jalur itu bisa dikembalikan ke queue tanpa
mengubah tampilan layar.
