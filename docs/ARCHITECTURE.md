# PRAKTIKA — Architecture Design Document (ADD)

**Version:** 1.0
**Status:** Approved baseline — acuan implementasi seluruh project
**Sumber:** `CLAUDE.md`, `PRD.md`
**Tanggal:** 2026-07-15

Dokumen ini adalah **acuan tunggal** untuk implementasi. Jika ada konflik antara ADD, PRD, dan CLAUDE.md, urutan otoritas: **ADD → CLAUDE.md → PRD**. Perubahan arsitektur wajib mengubah dokumen ini lebih dulu.

---

## 0. Ringkasan Keputusan Arsitektur (ADR)

| # | Keputusan | Alasan |
|---|-----------|--------|
| ADR-01 | **Nama tabel & kolom English** (`students.name`, `students.class_id`) | `CLAUDE.md` § Database Naming Convention adalah otoritas skema. Field Indonesian di PRD diterjemahkan. Istilah domain tanpa padanan (`nis`, `nisn`, `nip`) tetap dipertahankan. |
| ADR-02 | **Jurnal 2-stage dengan kolom review terpisah** | Workflow PRD (Industri → Guru → Approved) butuh jejak per-stage. `status` = state final, `industry_status` + `teacher_status` = state per-stage. |
| ADR-03 | **Nilai akhir weighted 60/40, konfigurabel via `settings`** | Industri melihat kerja harian → bobot lebih besar. Bobot di DB agar sekolah bisa ubah tanpa deploy. |
| ADR-04 | **`company_supervisors` sebagai tabel tersendiri** | Pembimbing Industri adalah role login, satu perusahaan bisa punya >1 pembimbing. `pic_name`/`pic_phone` di `companies` tetap ada sebagai kontak non-login. |
| ADR-05 | **UUIDv7 primary key** (bukan UUIDv4) | CLAUDE.md mewajibkan UUID PK. UUIDv7 time-ordered → index locality jauh lebih baik di PostgreSQL B-tree dibanding v4 random. Laravel: `HasUuids` trait sudah default v7 di Laravel 12+. |
| ADR-06 | **`attachments` polymorphic** untuk dokumentasi jurnal & monitoring | PRD punya dokumentasi di dua modul. Satu tabel + satu policy + satu upload service. |
| ADR-07 | **Verifikasi absensi = kolom pada `attendances`**, bukan tabel approvals | Absensi hanya 1 stage verifikasi (pembimbing), tidak butuh generalisasi. |
| ADR-08 | **Export laporan async via queue** (`report_exports`) | Rekap PDF/Excel se-angkatan bisa ribuan baris → request timeout. Database queue sudah di stack. |
| ADR-09 | **Inertia-first, tanpa REST API publik di v1** | Semua konsumsi lewat Inertia. Kontrak JSON di CLAUDE.md § API Standards diterapkan pada endpoint XHR/aksi non-Inertia saja. Sanctum disiapkan untuk roadmap Mobile App. |
| ADR-10 | **Tidak ada `Gate::before` bypass untuk Super Admin** (mengoreksi § 4.1) | `RolePermissionSeeder` memberi Super Admin seluruh permission secara eksplisit, jadi bypass redundan. Lebih buruk: bypass mengembalikan `true` **sebelum** `UserPolicy` jalan, sehingga penjaga "tidak boleh hapus akun sendiri" dan "super admin terakhir tidak boleh dihapus" mati justru untuk role yang paling perlu dijaga. Diuji di `tests/Feature/UserPolicyTest.php`. |
| ADR-11 | **Tidak ada registrasi publik** | PRD § 3: akun dibuat Admin. Route `register` bawaan Breeze dihapus — kalau dibiarkan, siapa pun bisa membuat akun login. |
| ADR-12 | **Geofence ditegakkan di Fase 4**, koordinat perusahaan opsional | Keputusan disetujui, menarik maju PRD § 7 (Future Roadmap). Perusahaan tanpa koordinat = tanpa geofence: check-in diterima, jarak dicatat `null`. Memblokir akan menghentikan siswa bekerja hanya karena admin lupa mengisi field. |
| ADR-13 | **Foto wajib di check-in dan check-out** | Keputusan disetujui. Konsekuensi diketahui: siswa tidak bisa check-out bila kamera bermasalah. |
| ADR-14 | **Absensi tidak memakai SoftDeletes** | Absensi adalah basis bukti penilaian guru. Koreksi lewat alur verifikasi (`rejected`), bukan penghapusan. Baris `rejected` tetap tersimpan tapi tidak dihitung sebagai kehadiran. |
| ADR-15 | **`present` dan `absent` tidak dapat diklaim siswa** | `present` hanya dari check-in nyata (berfoto + berkoordinat, dijaga CHECK constraint); `absent` hanya dari `MarkAbsentStudentsJob`. Siswa hanya boleh mengajukan `izin`/`sakit`, dan itu pun `pending` sampai pembimbing memutuskan. Tanpa ini, siswa bisa menghindari alpha kapan saja. |

### Deviasi dari dokumen sumber — wajib diketahui

| Temuan | Dampak |
|---|---|
| `composer.json` saat ini **Laravel 13.17**, bukan Laravel 12 seperti klaim CLAUDE.md | Tidak ada blocker; semua API yang dipakai ADD ini stabil di 12 & 13. Update CLAUDE.md agar tidak menyesatkan. |
| `require.php` = `^8.3`, CLAUDE.md minta **8.4+** | Naikkan ke `^8.4` saat implementasi Fase 0. |
| Paket **belum terpasang**: `spatie/laravel-permission`, `laravel/sanctum`, `spatie/laravel-activitylog`, driver PDF/Excel | Fase 0 wajib install sebelum modul apa pun. |
| `.env` default starter kit = SQLite, target = **PostgreSQL** | Fase 0 pindah connection. Beberapa tipe di ADD ini (`jsonb`, `enum` via check constraint) PostgreSQL-specific. |
| PRD status placement hanya `draft/aktif/selesai` — tidak ada jalur pembatalan | ADD menambah `cancelled`. Tanpa itu, penempatan yang batal harus dihapus → merusak riwayat absensi/jurnal. |
| PRD tidak mendefinisikan tahun ajaran | ADD menambah `academic_years`. Tanpa itu, rekap lintas angkatan mustahil dan `school_classes` harus dihapus tiap tahun. |

---

## 1. ERD

### 1.1 Diagram

```mermaid
erDiagram
    users ||--o| students : "has profile"
    users ||--o| teachers : "has profile"
    users ||--o{ company_supervisors : "has profile"
    users ||--o{ model_has_roles : "assigned"
    roles ||--o{ model_has_roles : "assigned to"
    roles ||--o{ role_has_permissions : "grants"
    permissions ||--o{ role_has_permissions : "granted by"
    permissions ||--o{ model_has_permissions : "direct grant"

    academic_years ||--o{ school_classes : "scopes"
    academic_years ||--o{ placements : "scopes"
    departments ||--o{ school_classes : "has"
    school_classes ||--o{ students : "contains"
    school_classes }o--|| teachers : "homeroom"

    companies ||--o{ company_supervisors : "employs"
    companies ||--o{ placements : "hosts"

    students ||--o{ placements : "assigned to"
    teachers ||--o{ placements : "supervises"
    company_supervisors ||--o{ placements : "mentors"

    placements ||--o{ attendances : "records"
    placements ||--o{ journals : "records"
    placements ||--o{ monitorings : "visited in"
    placements ||--o{ assessments : "scored by"
    placements ||--o| final_scores : "results in"

    teachers ||--o{ monitorings : "conducts"
    users ||--o{ assessments : "assesses"
    assessments ||--o{ assessment_scores : "detailed by"

    journals ||--o{ attachments : "documented by"
    monitorings ||--o{ attachments : "documented by"

    users ||--o{ report_exports : "requests"
    users ||--o{ activity_log : "causes"

    users {
        uuid id PK
        string name
        string email UK
        string password
        string phone
        string avatar_path
        boolean is_active
        timestamptz email_verified_at
        timestamptz last_login_at
        timestamptz deleted_at
    }

    academic_years {
        uuid id PK
        string name UK "2026/2027"
        date start_date
        date end_date
        boolean is_active
    }

    departments {
        uuid id PK
        string code UK
        string name
        timestamptz deleted_at
    }

    school_classes {
        uuid id PK
        uuid department_id FK
        uuid academic_year_id FK
        uuid homeroom_teacher_id FK "nullable"
        string name
        smallint grade_level
        timestamptz deleted_at
    }

    students {
        uuid id PK
        uuid user_id FK UK
        uuid class_id FK
        string nis UK
        string nisn UK
        string name
        string phone
        string gender
        text address
        string photo_path
        timestamptz deleted_at
    }

    teachers {
        uuid id PK
        uuid user_id FK UK
        string nip UK
        string name
        string phone
        timestamptz deleted_at
    }

    companies {
        uuid id PK
        string name
        string business_field
        text address
        decimal latitude
        decimal longitude
        smallint geofence_radius_m
        string pic_name
        string pic_phone
        smallint quota
        boolean is_active
        timestamptz deleted_at
    }

    company_supervisors {
        uuid id PK
        uuid user_id FK
        uuid company_id FK
        string position
        boolean is_active
        timestamptz deleted_at
    }

    placements {
        uuid id PK
        uuid student_id FK
        uuid company_id FK
        uuid teacher_id FK
        uuid supervisor_id FK "nullable"
        uuid academic_year_id FK
        date start_date
        date end_date
        string status "draft|active|completed|cancelled"
        timestamptz deleted_at
    }

    attendances {
        uuid id PK
        uuid placement_id FK
        date date
        string status "present|permit|sick|absent"
        timestamptz check_in_at
        decimal check_in_latitude
        decimal check_in_longitude
        string check_in_photo_path
        timestamptz check_out_at
        decimal check_out_latitude
        decimal check_out_longitude
        string check_out_photo_path
        text notes
        string verification_status "pending|verified|rejected"
        uuid verified_by FK
        timestamptz verified_at
    }

    journals {
        uuid id PK
        uuid placement_id FK
        date date
        text activity
        text work_result
        text obstacle
        string status "pending|approved|rejected"
        string industry_status "pending|approved|rejected"
        uuid industry_reviewed_by FK
        timestamptz industry_reviewed_at
        text industry_note
        string teacher_status "pending|approved|rejected"
        uuid teacher_reviewed_by FK
        timestamptz teacher_reviewed_at
        text teacher_note
        timestamptz deleted_at
    }

    monitorings {
        uuid id PK
        uuid placement_id FK
        uuid teacher_id FK
        date visit_date
        text note
        text recommendation
        decimal latitude
        decimal longitude
        timestamptz deleted_at
    }

    assessments {
        uuid id PK
        uuid placement_id FK
        string type "industry|teacher"
        uuid assessor_id FK
        decimal average_score
        text note
        timestamptz submitted_at
        timestamptz deleted_at
    }

    assessment_scores {
        uuid id PK
        uuid assessment_id FK
        string criteria_code
        decimal score
    }

    final_scores {
        uuid id PK
        uuid placement_id FK UK
        decimal industry_score
        decimal teacher_score
        decimal industry_weight
        decimal teacher_weight
        decimal final_score
        string grade
        timestamptz published_at
        uuid published_by FK
    }

    attachments {
        uuid id PK
        string attachable_type
        uuid attachable_id
        uuid uploaded_by FK
        string disk
        string path
        string original_name
        string mime_type
        bigint size
        string caption
    }

    settings {
        uuid id PK
        string key UK
        jsonb value
        string group
    }

    report_exports {
        uuid id PK
        uuid user_id FK
        string type
        string format "pdf|xlsx"
        jsonb filters
        string status "queued|processing|completed|failed"
        string file_path
        text error_message
        timestamptz completed_at
    }
```

### 1.2 Entity anchor — `placements`

`placements` adalah **pusat graf**. Semua transaksi PKL (absensi, jurnal, monitoring, penilaian, nilai akhir) menggantung ke `placement_id`, bukan ke `student_id`. Konsekuensi:

- Autorisasi seluruh modul transaksi = satu pertanyaan: *"apakah user ini terkait dengan placement ini?"* → satu helper, dipakai semua policy.
- Siswa yang mengulang / pindah perusahaan dapat placement baru; riwayat lama utuh.
- Rekap per perusahaan, per guru, per kelas semuanya join lewat `placements`.

---

## 2. Daftar Tabel Database

### 2.1 Tabel Aplikasi (26)

| # | Tabel | Grup | Soft Delete | Keterangan |
|---|-------|------|:---:|---|
| 1 | `users` | Auth | ✅ | Akun login semua role. Profil domain terpisah. |
| 2 | `password_reset_tokens` | Auth | — | Bawaan Laravel. |
| 3 | `sessions` | Auth | — | Driver session database. |
| 4 | `personal_access_tokens` | Auth | — | Sanctum. Disiapkan untuk mobile app. |
| 5 | `academic_years` | Master | — | Tahun ajaran. Scope untuk kelas & placement. |
| 6 | `departments` | Master | ✅ | Jurusan (`code`, `name`). |
| 7 | `school_classes` | Master | ✅ | Kelas. Nama `classes` dihindari — `class` reserved word PHP dan bikin nama model canggung. |
| 8 | `students` | Master | ✅ | Profil siswa, 1:1 ke `users`. |
| 9 | `teachers` | Master | ✅ | Profil guru pembimbing, 1:1 ke `users`. |
| 10 | `companies` | Master | ✅ | Perusahaan/DUDI + kuota + koordinat. |
| 11 | `company_supervisors` | Master | ✅ | Pembimbing industri, 1:1 ke `users`, N:1 ke `companies`. |
| 12 | `placements` | Transaksi | ✅ | Penempatan PKL. Entity anchor. |
| 13 | `attendances` | Transaksi | — | Absensi harian + geolokasi + foto. |
| 14 | `journals` | Transaksi | ✅ | Jurnal harian + 2-stage review. |
| 15 | `monitorings` | Transaksi | ✅ | Laporan kunjungan guru. |
| 16 | `assessments` | Transaksi | ✅ | Header penilaian (industri / guru). |
| 17 | `assessment_scores` | Transaksi | — | Detail skor per kriteria. |
| 18 | `final_scores` | Transaksi | — | Hasil hitung + publikasi. |
| 19 | `attachments` | Support | — | Polymorphic: dokumentasi jurnal & monitoring. |
| 20 | `settings` | Support | — | Key-value `jsonb`. Bobot nilai, jam absensi, dsb. |
| 21 | `report_exports` | Support | — | Job export PDF/Excel async. |
| 22 | `notifications` | Support | — | Bawaan Laravel, database channel. |
| 23 | `activity_log` | Audit | — | `spatie/laravel-activitylog`. Definition of Done § audit log. |
| 24 | `jobs` | Infra | — | Database queue. |
| 25 | `job_batches` | Infra | — | Batch export. |
| 26 | `failed_jobs` | Infra | — | |
| + | `cache`, `cache_locks` | Infra | — | Bawaan. |

### 2.2 Tabel Spatie Permission (5)

`roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`.

> **Wajib:** set `config/permission.php` → `column_names.model_morph_key = 'model_uuid'` dan `'team_foreign_key' => null`. Default Spatie memakai `model_id` bertipe `bigint` → **akan gagal** dengan UUID PK. Ini penyebab error paling umum saat memasang Spatie di project UUID.

### 2.3 Constraint & Index Kritis

| Tabel | Constraint | Kenapa |
|---|---|---|
| `attendances` | `UNIQUE (placement_id, date)` | Cegah double check-in. Satu-satunya pertahanan andal terhadap double-submit dari mobile. |
| `journals` | `UNIQUE (placement_id, date)` | Satu jurnal per hari. |
| `assessments` | `UNIQUE (placement_id, type)` | Satu penilaian industri + satu penilaian guru per placement. |
| `final_scores` | `UNIQUE (placement_id)` | Satu hasil per placement. |
| `assessment_scores` | `UNIQUE (assessment_id, criteria_code)` | Cegah skor ganda per kriteria. |
| `placements` | Partial unique: `UNIQUE (student_id) WHERE status = 'active' AND deleted_at IS NULL` | Siswa tidak boleh punya 2 PKL aktif bersamaan. PostgreSQL partial index — tidak bisa ditiru di MySQL. |
| `students` | `UNIQUE (user_id)`, `UNIQUE (nis)`, `UNIQUE (nisn)` | |
| `teachers` | `UNIQUE (user_id)`, `UNIQUE (nip)` | |
| `company_supervisors` | `UNIQUE (user_id)` | Satu user = satu pembimbing industri. |
| `attendances` | `INDEX (placement_id, date DESC)`, `INDEX (verification_status)` | Query rekap & antrian verifikasi. |
| `journals` | `INDEX (status)`, `INDEX (industry_status)`, `INDEX (teacher_status)` | Widget "jurnal pending" per role. |
| `attachments` | `INDEX (attachable_type, attachable_id)` | Polymorphic lookup. |
| Semua FK | `ON DELETE RESTRICT` default; `CASCADE` hanya `assessment_scores → assessments` dan `attachments → parent` | Data PKL bersifat legal record. Cascade delete pada placement = kehilangan bukti absensi. |

> **Enum:** PostgreSQL native `ENUM` tidak reversible dengan mudah dan menyakitkan saat menambah nilai. Gunakan `string` + `CHECK` constraint + PHP `enum` class (`App\Enums\PlacementStatus`). Migration tetap reversible sesuai CLAUDE.md § Database.

---

## 3. Relasi Antar Tabel

### 3.1 Tabel relasi Eloquent

| Model | Relasi | Tipe | Target |
|---|---|---|---|
| `User` | `student` | hasOne | `Student` |
| | `teacher` | hasOne | `Teacher` |
| | `companySupervisor` | hasOne | `CompanySupervisor` |
| | `assessments` | hasMany | `Assessment` (`assessor_id`) |
| | `reportExports` | hasMany | `ReportExport` |
| `AcademicYear` | `schoolClasses` | hasMany | `SchoolClass` |
| | `placements` | hasMany | `Placement` |
| `Department` | `schoolClasses` | hasMany | `SchoolClass` |
| | `students` | hasManyThrough | `Student` via `SchoolClass` |
| `SchoolClass` | `department` | belongsTo | `Department` |
| | `academicYear` | belongsTo | `AcademicYear` |
| | `homeroomTeacher` | belongsTo | `Teacher` |
| | `students` | hasMany | `Student` |
| `Student` | `user` | belongsTo | `User` |
| | `schoolClass` | belongsTo | `SchoolClass` (`class_id`) |
| | `placements` | hasMany | `Placement` |
| | `activePlacement` | hasOne | `Placement` (`->where('status','active')`) |
| `Teacher` | `user` | belongsTo | `User` |
| | `placements` | hasMany | `Placement` |
| | `students` | hasManyThrough | `Student` via `Placement` |
| | `monitorings` | hasMany | `Monitoring` |
| `Company` | `supervisors` | hasMany | `CompanySupervisor` |
| | `placements` | hasMany | `Placement` |
| | `activePlacements` | hasMany | `Placement` (status active) — dasar hitung sisa kuota |
| `CompanySupervisor` | `user` | belongsTo | `User` |
| | `company` | belongsTo | `Company` |
| | `placements` | hasMany | `Placement` |
| `Placement` | `student` | belongsTo | `Student` |
| | `company` | belongsTo | `Company` |
| | `teacher` | belongsTo | `Teacher` |
| | `supervisor` | belongsTo | `CompanySupervisor` |
| | `academicYear` | belongsTo | `AcademicYear` |
| | `attendances` | hasMany | `Attendance` |
| | `journals` | hasMany | `Journal` |
| | `monitorings` | hasMany | `Monitoring` |
| | `assessments` | hasMany | `Assessment` |
| | `industryAssessment` | hasOne | `Assessment` (type industry) |
| | `teacherAssessment` | hasOne | `Assessment` (type teacher) |
| | `finalScore` | hasOne | `FinalScore` |
| `Attendance` | `placement` | belongsTo | `Placement` |
| | `verifier` | belongsTo | `User` (`verified_by`) |
| `Journal` | `placement` | belongsTo | `Placement` |
| | `industryReviewer` | belongsTo | `User` |
| | `teacherReviewer` | belongsTo | `User` |
| | `attachments` | morphMany | `Attachment` |
| `Monitoring` | `placement` | belongsTo | `Placement` |
| | `teacher` | belongsTo | `Teacher` |
| | `attachments` | morphMany | `Attachment` |
| `Assessment` | `placement` | belongsTo | `Placement` |
| | `assessor` | belongsTo | `User` |
| | `scores` | hasMany | `AssessmentScore` |
| `AssessmentScore` | `assessment` | belongsTo | `Assessment` |
| `FinalScore` | `placement` | belongsTo | `Placement` |
| | `publisher` | belongsTo | `User` (`published_by`) |
| `Attachment` | `attachable` | morphTo | `Journal` \| `Monitoring` |
| | `uploader` | belongsTo | `User` |

### 3.2 Jalur autorisasi (dipakai semua Policy)

Semua policy transaksi bermuara ke satu pertanyaan. Implementasi tunggal di `Placement::scopeVisibleTo(User $user)`:

```
Super Admin / Admin PKL  → semua placement
Guru Pembimbing          → placements.teacher_id   = user.teacher.id
Pembimbing Industri      → placements.company_id   = user.companySupervisor.company_id
Siswa                    → placements.student_id   = user.student.id
```

> Pembimbing Industri di-scope ke **company**, bukan ke `supervisor_id`. Alasan: `placements.supervisor_id` nullable (penempatan bisa dibuat sebelum pembimbing ditunjuk); scope per-supervisor akan membuat siswa tidak terlihat oleh siapa pun sampai field itu diisi.

---

## 4. Role & Permission

### 4.1 Role (5)

| Role | Slug | Cakupan data |
|---|---|---|
| Super Admin | `super-admin` | Global. Memegang seluruh permission secara eksplisit dari seeder — **bukan** lewat bypass. |
| Admin PKL | `admin-pkl` | Global data PKL. Tidak bisa kelola user/role/settings sistem. |
| Guru Pembimbing | `guru-pembimbing` | Hanya placement dengan `teacher_id` miliknya. |
| Pembimbing Industri | `pembimbing-industri` | Hanya placement di `company_id` miliknya. |
| Siswa | `siswa` | Hanya placement miliknya. |

> **Dikoreksi saat implementasi (ADR-10):** rencana awal memakai `Gate::before` untuk mem-bypass Super Admin. Itu dibatalkan. Karena seeder sudah memberi Super Admin seluruh permission, bypass tidak menambah apa pun — tapi merusak: ia menjawab `true` sebelum `UserPolicy::delete()` sempat menolak Super Admin menghapus akunnya sendiri atau menghapus Super Admin terakhir. Otorisasi kini lewat policy untuk semua role tanpa kecuali.

### 4.2 Daftar Permission

Format: `{resource}.{action}`. Guard: `web`.

**Master Data**

| Permission | SA | Admin | Guru | Industri | Siswa |
|---|:-:|:-:|:-:|:-:|:-:|
| `academic-years.viewAny` `.view` `.create` `.update` `.delete` | ✅ | 👁️ | — | — | — |
| `departments.viewAny` `.view` `.create` `.update` `.delete` | ✅ | ✅ | 👁️ | — | — |
| `school-classes.viewAny` `.view` `.create` `.update` `.delete` | ✅ | ✅ | 👁️ | — | — |
| `students.viewAny` `.view` `.create` `.update` `.delete` `.import` `.export` | ✅ | ✅ | 👁️ | 👁️ | — |
| `teachers.viewAny` `.view` `.create` `.update` `.delete` `.import` | ✅ | ✅ | 👁️ | — | — |
| `companies.viewAny` `.view` `.create` `.update` `.delete` | ✅ | ✅ | 👁️ | 👁️ | 👁️ |
| `company-supervisors.viewAny` `.view` `.create` `.update` `.delete` | ✅ | ✅ | — | — | — |

**Transaksi**

| Permission | SA | Admin | Guru | Industri | Siswa |
|---|:-:|:-:|:-:|:-:|:-:|
| `placements.viewAny` `.view` | ✅ | ✅ | 👁️ | 👁️ | 👁️ |
| `placements.create` `.update` `.delete` `.activate` `.complete` `.cancel` | ✅ | ✅ | — | — | — |
| `attendances.viewAny` `.view` | ✅ | ✅ | 👁️ | 👁️ | 👁️ |
| `attendances.checkIn` `.checkOut` | — | — | — | — | ✅ |
| `attendances.verify` | ✅ | ✅ | ✅ | ✅ | — |
| `attendances.update` `.delete` | ✅ | ✅ | — | — | — |
| `journals.viewAny` `.view` | ✅ | ✅ | 👁️ | 👁️ | 👁️ |
| `journals.create` `.update` `.delete` | — | — | — | — | ✅ |
| `journals.reviewAsIndustry` | ✅ | — | — | ✅ | — |
| `journals.reviewAsTeacher` | ✅ | — | ✅ | — | — |
| `monitorings.viewAny` `.view` | ✅ | ✅ | 👁️ | — | 👁️ |
| `monitorings.create` `.update` `.delete` | ✅ | — | ✅ | — | — |
| `assessments.viewAny` `.view` | ✅ | ✅ | 👁️ | 👁️ | — |
| `assessments.submitAsIndustry` | ✅ | — | — | ✅ | — |
| `assessments.submitAsTeacher` | ✅ | — | ✅ | — | — |
| `final-scores.view` | ✅ | ✅ | 👁️ | 👁️ | 👁️ published saja |
| `final-scores.calculate` | ✅ | ✅ | — | — | — |
| `final-scores.publish` `.unpublish` | ✅ | ✅ | — | — | — |
| `attachments.create` | ✅ | ✅ | ✅ | — | ✅ |
| `attachments.delete` | ✅ | ✅ | ✅ | — | ✅ pemilik |

**Sistem**

| Permission | SA | Admin | Guru | Industri | Siswa |
|---|:-:|:-:|:-:|:-:|:-:|
| `users.viewAny` `.view` `.create` `.update` `.delete` `.impersonate` | ✅ | — | — | — | — |
| `users.resetPassword` | ✅ | ✅ | — | — | — |
| `roles.viewAny` `.view` `.create` `.update` `.delete` `.assign` | ✅ | — | — | — | — |
| `settings.view` `.update` | ✅ | 👁️ | — | — | — |
| `activity-log.viewAny` `.view` | ✅ | 👁️ | — | — | — |
| `reports.attendance` `.journal` `.assessment` `.monitoring` | ✅ | ✅ | ✅ scoped | ✅ scoped | — |
| `dashboard.view` | ✅ | ✅ | ✅ | ✅ | ✅ |

Legenda: ✅ penuh · 👁️ hanya `viewAny`/`view` · — tidak ada

> **Permission ≠ akses baris.** Permission menjawab *"boleh akses fitur ini?"*; Policy menjawab *"boleh akses baris ini?"*. Keduanya wajib. `journals.reviewAsTeacher` mengizinkan guru membuka fitur review — `JournalPolicy::reviewAsTeacher()` yang memastikan jurnal itu milik siswa bimbingannya. Melewatkan lapis kedua = guru mana pun bisa approve jurnal siswa mana pun.

### 4.3 Seeder

`RolePermissionSeeder` — idempoten (`firstOrCreate`), aman dijalankan ulang di production. Dijalankan sebelum `UserSeeder`.

---

## 5. Struktur Folder Laravel

```
app/
├── Actions/                     # Operasi tunggal, side-effectful, dipanggil controller
│   ├── Attendance/
│   │   ├── CheckInAction.php
│   │   ├── CheckOutAction.php
│   │   └── VerifyAttendanceAction.php
│   ├── Journal/
│   │   ├── SubmitJournalAction.php
│   │   ├── ReviewJournalAsIndustryAction.php
│   │   └── ReviewJournalAsTeacherAction.php
│   ├── Placement/
│   │   ├── CreatePlacementAction.php
│   │   ├── ActivatePlacementAction.php
│   │   ├── CompletePlacementAction.php
│   │   └── CancelPlacementAction.php
│   ├── Assessment/
│   │   ├── SubmitIndustryAssessmentAction.php
│   │   ├── SubmitTeacherAssessmentAction.php
│   │   ├── CalculateFinalScoreAction.php
│   │   └── PublishFinalScoreAction.php
│   ├── Monitoring/
│   │   └── RecordVisitAction.php
│   ├── Attachment/
│   │   ├── StoreAttachmentAction.php
│   │   └── DeleteAttachmentAction.php
│   └── User/
│       ├── CreateUserWithProfileAction.php
│       └── ToggleUserStatusAction.php
├── Casts/
│   └── EncryptedJson.php
├── DataTransferObjects/         # readonly, dari FormRequest → Action
│   ├── CheckInData.php
│   ├── CheckOutData.php
│   ├── JournalData.php
│   ├── PlacementData.php
│   ├── AssessmentData.php
│   ├── MonitoringData.php
│   └── ReportFilterData.php
├── Enums/
│   ├── RoleName.php
│   ├── PlacementStatus.php
│   ├── AttendanceStatus.php
│   ├── VerificationStatus.php
│   ├── ReviewStatus.php
│   ├── AssessmentType.php
│   ├── IndustryCriteria.php
│   ├── TeacherCriteria.php
│   ├── ExportFormat.php
│   └── ExportStatus.php
├── Exceptions/
│   ├── AttendanceAlreadyRecordedException.php
│   ├── OutsideGeofenceException.php
│   ├── CompanyQuotaExceededException.php
│   ├── StudentAlreadyPlacedException.php
│   ├── AssessmentIncompleteException.php
│   └── PlacementNotActiveException.php
├── Exports/                     # maatwebsite/excel
│   ├── AttendanceRecapExport.php
│   ├── JournalRecapExport.php
│   ├── FinalScoreExport.php
│   └── MonitoringExport.php
├── Http/
│   ├── Controllers/
│   │   ├── Auth/
│   │   │   ├── AuthenticatedSessionController.php
│   │   │   ├── PasswordResetLinkController.php
│   │   │   └── NewPasswordController.php
│   │   ├── DashboardController.php
│   │   ├── Master/
│   │   │   ├── AcademicYearController.php
│   │   │   ├── DepartmentController.php
│   │   │   ├── SchoolClassController.php
│   │   │   ├── StudentController.php
│   │   │   ├── TeacherController.php
│   │   │   ├── CompanyController.php
│   │   │   └── CompanySupervisorController.php
│   │   ├── PlacementController.php
│   │   ├── PlacementStatusController.php
│   │   ├── AttendanceController.php
│   │   ├── AttendanceCheckInController.php
│   │   ├── AttendanceCheckOutController.php
│   │   ├── AttendanceVerificationController.php
│   │   ├── JournalController.php
│   │   ├── JournalIndustryReviewController.php
│   │   ├── JournalTeacherReviewController.php
│   │   ├── MonitoringController.php
│   │   ├── AssessmentController.php
│   │   ├── FinalScoreController.php
│   │   ├── ReportController.php
│   │   ├── ReportExportController.php
│   │   ├── AttachmentController.php
│   │   ├── ProfileController.php
│   │   └── System/
│   │       ├── UserController.php
│   │       ├── RoleController.php
│   │       ├── SettingController.php
│   │       └── ActivityLogController.php
│   ├── Middleware/
│   │   ├── HandleInertiaRequests.php   # share auth.user, roles, permissions, sidebar
│   │   ├── EnsureUserIsActive.php
│   │   └── SetLocale.php
│   ├── Requests/
│   │   ├── Auth/LoginRequest.php
│   │   ├── Master/{Store,Update}{AcademicYear,Department,SchoolClass,Student,Teacher,Company,CompanySupervisor}Request.php
│   │   ├── Placement/{Store,Update}PlacementRequest.php
│   │   ├── Attendance/{CheckIn,CheckOut,VerifyAttendance}Request.php
│   │   ├── Journal/{Store,Update,Review}JournalRequest.php
│   │   ├── Monitoring/{Store,Update}MonitoringRequest.php
│   │   ├── Assessment/StoreAssessmentRequest.php
│   │   ├── Report/GenerateReportRequest.php
│   │   └── System/{Store,Update}UserRequest.php, UpdateSettingRequest.php
│   └── Resources/               # untuk endpoint JSON non-Inertia
│       └── ...
├── Jobs/
│   ├── GenerateReportExportJob.php
│   ├── MarkAbsentStudentsJob.php
│   ├── CompleteExpiredPlacementsJob.php
│   └── SendJournalReminderJob.php
├── Models/
│   ├── Concerns/
│   │   ├── HasUuidPrimaryKey.php
│   │   └── LogsActivity.php
│   ├── User.php
│   ├── AcademicYear.php · Department.php · SchoolClass.php
│   ├── Student.php · Teacher.php · Company.php · CompanySupervisor.php
│   ├── Placement.php · Attendance.php · Journal.php · Monitoring.php
│   ├── Assessment.php · AssessmentScore.php · FinalScore.php
│   ├── Attachment.php · Setting.php · ReportExport.php
├── Notifications/
│   ├── JournalReviewedNotification.php
│   ├── JournalPendingReviewNotification.php
│   ├── PlacementActivatedNotification.php
│   ├── FinalScorePublishedNotification.php
│   └── ReportExportReadyNotification.php
├── Observers/
│   ├── PlacementObserver.php
│   └── AssessmentObserver.php    # trigger recalculate final score
├── Policies/                     # § 9
├── Providers/
│   ├── AppServiceProvider.php
│   └── AuthServiceProvider.php
├── Repositories/
│   ├── Contracts/
│   │   ├── PlacementRepositoryInterface.php
│   │   ├── AttendanceRepositoryInterface.php
│   │   ├── JournalRepositoryInterface.php
│   │   └── DashboardRepositoryInterface.php
│   └── Eloquent/
│       ├── PlacementRepository.php
│       ├── AttendanceRepository.php
│       ├── JournalRepository.php
│       └── DashboardRepository.php
├── Rules/
│   ├── WithinGeofence.php
│   ├── WithinPlacementPeriod.php
│   ├── NotFutureDate.php
│   └── CompanyHasQuota.php
├── Services/                     # § 8
└── Support/
    ├── ApiResponse.php           # kontrak JSON CLAUDE.md § API Standards
    └── SidebarBuilder.php

database/
├── factories/                    # satu per model — wajib, dipakai semua test
├── migrations/
└── seeders/
    ├── DatabaseSeeder.php
    ├── RolePermissionSeeder.php
    ├── SettingSeeder.php
    ├── AcademicYearSeeder.php
    ├── DepartmentSeeder.php
    ├── UserSeeder.php
    └── DemoDataSeeder.php        # local only — guard dengan app()->isProduction()

resources/js/
├── app.tsx
├── components/
│   ├── ui/                       # shadcn — jangan diedit manual, pakai CLI
│   ├── data-table/               # DataTable, Pagination, Filters, ColumnHeader
│   ├── dashboard/                # widget per § 10
│   ├── attendance/               # CheckInCard, GeoCapture, PhotoCapture, MapPreview
│   ├── journal/                  # JournalForm, ReviewPanel, StatusBadge, Timeline
│   ├── assessment/               # ScoreInput, CriteriaGrid, FinalScoreCard
│   ├── shared/                   # PageHeader, EmptyState, ConfirmDialog, FileUploader
│   └── layout/                   # Sidebar, Topbar, NavItem, RoleBadge, MobileNav
├── hooks/
│   ├── use-permission.ts         # can('journals.reviewAsTeacher')
│   ├── use-role.ts
│   ├── use-geolocation.ts
│   ├── use-camera.ts
│   ├── use-debounce.ts
│   └── use-data-table.ts
├── layouts/
│   ├── app-layout.tsx
│   ├── auth-layout.tsx
│   └── settings-layout.tsx
├── lib/
│   ├── utils.ts
│   ├── format.ts                 # tanggal, nilai, terbilang
│   └── nav.ts                    # sidebar config § 7
├── pages/                        # cermin route map § 6
│   ├── auth/  dashboard/  master/  placements/  attendances/
│   ├── journals/  monitorings/  assessments/  reports/  system/  profile/
└── types/
    ├── index.ts  auth.ts  models.ts  enums.ts  inertia.d.ts

routes/
├── web.php          # hanya require partial di bawah
├── auth.php
├── master.php
├── pkl.php
├── system.php
└── console.php      # schedule

tests/
├── Feature/  Unit/  Pest.php
```

---

## 6. Route Map

Semua route web + Inertia. Middleware global: `['web', 'auth', 'verified', EnsureUserIsActive::class]` kecuali grup Guest. Otorisasi: `can:` middleware + `authorize()` di controller.

### 6.1 Guest

| Method | URI | Controller | Name |
|---|---|---|---|
| GET/POST | `/login` | `AuthenticatedSessionController@create/store` | `login` |
| GET/POST | `/forgot-password` | `PasswordResetLinkController@create/store` | `password.request` / `password.email` |
| GET/POST | `/reset-password/{token}` | `NewPasswordController@create/store` | `password.reset` / `password.store` |

### 6.2 Authenticated — umum

| Method | URI | Name | Akses |
|---|---|---|---|
| POST | `/logout` | `logout` | semua |
| GET | `/dashboard` | `dashboard` | semua (payload beda per role, § 10) |
| GET/PATCH | `/profile` | `profile.edit` / `profile.update` | semua |
| PUT | `/profile/password` | `profile.password.update` | semua |
| POST | `/attachments` | `attachments.store` | `can:attachments.create` |
| DELETE | `/attachments/{attachment}` | `attachments.destroy` | policy |

### 6.3 Master Data — `/master`

Resource penuh (`index create store show edit update destroy`), middleware `can:{resource}.viewAny` di level grup, `authorize()` per aksi.

| URI | Name prefix | Tambahan |
|---|---|---|
| `/master/academic-years` | `master.academic-years.*` | `PUT /{id}/activate` → `.activate` |
| `/master/departments` | `master.departments.*` | |
| `/master/classes` | `master.classes.*` | |
| `/master/students` | `master.students.*` | `POST /import` → `.import`, `GET /export` → `.export` |
| `/master/teachers` | `master.teachers.*` | `POST /import` → `.import` |
| `/master/companies` | `master.companies.*` | |
| `/master/supervisors` | `master.supervisors.*` | |

### 6.4 PKL — transaksi

| Method | URI | Name | Akses |
|---|---|---|---|
| GET | `/placements` | `placements.index` | semua role (di-scope `visibleTo`) |
| GET | `/placements/create` | `placements.create` | Admin |
| POST | `/placements` | `placements.store` | Admin |
| GET | `/placements/{placement}` | `placements.show` | policy |
| GET | `/placements/{placement}/edit` | `placements.edit` | Admin |
| PUT | `/placements/{placement}` | `placements.update` | Admin |
| DELETE | `/placements/{placement}` | `placements.destroy` | Admin |
| PUT | `/placements/{placement}/activate` | `placements.activate` | Admin |
| PUT | `/placements/{placement}/complete` | `placements.complete` | Admin |
| PUT | `/placements/{placement}/cancel` | `placements.cancel` | Admin |
| — | | | |
| GET | `/attendances` | `attendances.index` | semua (scoped) |
| GET | `/attendances/{attendance}` | `attendances.show` | policy |
| GET | `/attendances/check-in` | `attendances.check-in.create` | Siswa |
| POST | `/attendances/check-in` | `attendances.check-in.store` | Siswa |
| POST | `/attendances/check-out` | `attendances.check-out.store` | Siswa |
| PUT | `/attendances/{attendance}/verify` | `attendances.verify` | Guru, Industri |
| PUT | `/attendances/{attendance}` | `attendances.update` | Admin (koreksi izin/sakit) |
| — | | | |
| GET | `/journals` | `journals.index` | semua (scoped) |
| GET | `/journals/create` | `journals.create` | Siswa |
| POST | `/journals` | `journals.store` | Siswa |
| GET | `/journals/{journal}` | `journals.show` | policy |
| GET | `/journals/{journal}/edit` | `journals.edit` | Siswa (hanya jika belum direview) |
| PUT | `/journals/{journal}` | `journals.update` | Siswa |
| DELETE | `/journals/{journal}` | `journals.destroy` | Siswa |
| PUT | `/journals/{journal}/review/industry` | `journals.review.industry` | Industri |
| PUT | `/journals/{journal}/review/teacher` | `journals.review.teacher` | Guru |
| — | | | |
| GET | `/monitorings` | `monitorings.index` | Admin, Guru, Siswa (scoped) |
| GET | `/monitorings/create` | `monitorings.create` | Guru |
| POST | `/monitorings` | `monitorings.store` | Guru |
| GET | `/monitorings/{monitoring}` | `monitorings.show` | policy |
| GET/PUT/DELETE | `/monitorings/{monitoring}` | `.edit` `.update` `.destroy` | Guru pemilik |
| — | | | |
| GET | `/assessments` | `assessments.index` | Admin, Guru, Industri |
| GET | `/placements/{placement}/assessments/industry` | `assessments.industry.edit` | Industri |
| PUT | `/placements/{placement}/assessments/industry` | `assessments.industry.update` | Industri |
| GET | `/placements/{placement}/assessments/teacher` | `assessments.teacher.edit` | Guru |
| PUT | `/placements/{placement}/assessments/teacher` | `assessments.teacher.update` | Guru |
| — | | | |
| GET | `/final-scores` | `final-scores.index` | Admin, Guru, Industri |
| GET | `/final-scores/{placement}` | `final-scores.show` | policy; Siswa hanya jika `published_at` terisi |
| POST | `/final-scores/{placement}/calculate` | `final-scores.calculate` | Admin |
| POST | `/final-scores/{placement}/publish` | `final-scores.publish` | Admin |
| DELETE | `/final-scores/{placement}/publish` | `final-scores.unpublish` | Admin |
| POST | `/final-scores/publish-batch` | `final-scores.publish-batch` | Admin |

### 6.5 Laporan — `/reports`

| Method | URI | Name |
|---|---|---|
| GET | `/reports/attendance` | `reports.attendance` |
| GET | `/reports/journal` | `reports.journal` |
| GET | `/reports/assessment` | `reports.assessment` |
| GET | `/reports/monitoring` | `reports.monitoring` |
| POST | `/reports/export` | `reports.export.store` — dispatch `GenerateReportExportJob`, redirect + toast |
| GET | `/reports/exports` | `reports.export.index` |
| GET | `/reports/exports/{export}/download` | `reports.export.download` — policy: hanya pemilik |

### 6.6 Sistem — `/system` (Super Admin, kecuali disebutkan)

| URI | Name | Akses |
|---|---|---|
| `/system/users` (resource) | `system.users.*` | Super Admin |
| `POST /system/users/{user}/reset-password` | `system.users.reset-password` | SA + Admin PKL |
| `POST /system/users/{user}/toggle-status` | `system.users.toggle-status` | Super Admin |
| `POST /system/users/{user}/impersonate` | `system.users.impersonate` | Super Admin |
| `DELETE /system/impersonate` | `system.impersonate.leave` | — |
| `/system/roles` (resource) | `system.roles.*` | Super Admin |
| `GET/PUT /system/settings` | `system.settings.edit` / `.update` | Super Admin |
| `GET /system/activity-log` | `system.activity-log.index` | SA + Admin PKL (view) |

### 6.7 Scheduled (`routes/console.php`)

| Job | Jadwal | Fungsi |
|---|---|---|
| `MarkAbsentStudentsJob` | harian 18:00 | Placement aktif tanpa absensi hari ini pada hari kerja → status `absent`. |
| `CompleteExpiredPlacementsJob` | harian 00:30 | `status=active` & `end_date < today` → `completed`. |
| `SendJournalReminderJob` | harian 16:00 | Notifikasi siswa yang belum isi jurnal hari ini. |

> Ketiganya menulis data atas nama sistem. Isi `causer` activity log dengan `null` + `log_name = 'system'` agar audit trail tidak salah menuduh user.

---

## 7. Menu Sidebar per Role

Dibangun server-side oleh `App\Support\SidebarBuilder`, di-share lewat `HandleInertiaRequests`, dirender `components/layout/sidebar.tsx`. Filter berdasarkan permission — **bukan** role — supaya perubahan permission tidak perlu ubah frontend.

> Sidebar hanya kosmetik. Menyembunyikan item **bukan** kontrol akses; route tetap wajib dijaga `can:` + Policy.

### Super Admin

```
Dashboard
Master Data
  ├─ Tahun Ajaran      ├─ Jurusan        ├─ Kelas
  ├─ Siswa             ├─ Guru           ├─ Perusahaan
  └─ Pembimbing Industri
PKL
  ├─ Penempatan        ├─ Absensi        ├─ Jurnal
  ├─ Monitoring        ├─ Penilaian      └─ Nilai Akhir
Laporan
  ├─ Rekap Absensi     ├─ Rekap Jurnal
  ├─ Nilai PKL         ├─ Monitoring     └─ Riwayat Export
Sistem
  ├─ Pengguna          ├─ Role & Permission
  ├─ Pengaturan        └─ Log Aktivitas
```

### Admin PKL

```
Dashboard
Master Data
  ├─ Jurusan · Kelas · Siswa · Guru · Perusahaan · Pembimbing Industri
PKL
  ├─ Penempatan · Absensi · Jurnal · Monitoring · Penilaian · Nilai Akhir
Laporan
  ├─ Rekap Absensi · Rekap Jurnal · Nilai PKL · Monitoring · Riwayat Export
Log Aktivitas   (read-only)
```

### Guru Pembimbing

```
Dashboard
Siswa Bimbingan          → /placements (auto-filter teacher_id)
Absensi                  → antrian verifikasi
Jurnal                   → badge: jumlah menunggu review guru
Monitoring
  ├─ Kunjungan Saya      └─ Catat Kunjungan
Penilaian                → badge: penilaian belum diisi
Laporan                  → scoped ke bimbingan
```

### Pembimbing Industri

```
Dashboard
Siswa PKL                → /placements (auto-filter company_id)
Absensi                  → antrian verifikasi
Jurnal                   → badge: jumlah menunggu review industri
Penilaian                → badge: penilaian belum diisi
```

### Siswa

```
Dashboard
Absensi
  ├─ Check In / Check Out   (aksi utama, sticky di mobile)
  └─ Riwayat Absensi
Jurnal
  ├─ Isi Jurnal Hari Ini    └─ Riwayat Jurnal
Penempatan Saya
Monitoring                  (read-only)
Nilai Saya                  (muncul hanya jika final_scores.published_at terisi)
```

Item bertanda badge memakai count dari `SidebarBuilder`. **Cache per user 60 detik** — tanpa cache, tiap page load memicu 4 count query di seluruh aplikasi.

---

## 8. Daftar Service Class

**Aturan pembagian:** *Service* = orkestrasi + query domain, boleh dipakai banyak controller. *Action* = satu operasi write bertransaksi, satu `handle()`. *Repository* = query kompleks yang tidak boleh bocor ke service.

| Service | Tanggung jawab | Method utama |
|---|---|---|
| `AuthService` | Login, throttle, catat `last_login_at`, tolak user non-aktif | `attempt()`, `logout()`, `sendResetLink()` |
| `DashboardService` | Rakit payload widget per role (§ 10). Delegasi ke `DashboardRepository`. | `forUser(User): array` |
| `PlacementService` | Validasi kuota, cegah placement aktif ganda, transisi status | `assign()`, `changeStatus()`, `remainingQuota(Company)`, `validateAssignable()` |
| `AttendanceService` | Aturan absensi: sekali/hari, dalam periode PKL, geofence, hitung durasi | `todayFor(Placement)`, `canCheckIn()`, `canCheckOut()`, `recap(filters)` |
| `GeolocationService` | Haversine, cek radius perusahaan | `distanceMeters()`, `isWithinRadius()` |
| `JournalService` | State machine 2-stage; hitung `status` final dari kedua stage | `resolveFinalStatus()`, `pendingForIndustry()`, `pendingForTeacher()`, `completionRate(Placement)` |
| `MonitoringService` | Riwayat kunjungan, cek placement aktif | `record()`, `historyFor(Placement)` |
| `AssessmentService` | Simpan header + detail skor, hitung rata-rata, cek kelengkapan | `submit()`, `averageOf()`, `isComplete(Placement)` |
| `ScoreCalculatorService` | **Satu-satunya** tempat rumus nilai akhir. Baca bobot dari `SettingService`. | `calculate(Placement): FinalScore`, `toGrade(float): string` |
| `SettingService` | Baca/tulis settings + cache | `get(key, default)`, `set()`, `weights(): array` |
| `AttachmentService` | Upload, validasi mime/size, kompres foto, hapus file saat record dihapus | `store()`, `delete()`, `urlFor()` |
| `ReportService` | Bangun dataset rekap dari filter, dipakai halaman & export | `attendanceRecap()`, `journalRecap()`, `assessmentRecap()`, `monitoringRecap()` |
| `ReportExportService` | Buat record `report_exports`, dispatch job, resolve file | `queue()`, `markCompleted()`, `markFailed()` |
| `NotificationService` | Pusat notifikasi domain | `journalReviewed()`, `scorePublished()`, `placementActivated()` |
| `UserAccountService` | Buat user + profil + role dalam satu transaksi | `createWithProfile()`, `syncRole()`, `toggleStatus()` |
| `ActivityLogService` | Wrapper activitylog, format deskripsi Indonesia | `log()`, `forSubject()` |

### 8.1 Rumus nilai akhir — kanonikal

```
industry_avg = avg(disiplin, tanggung_jawab, kerjasama, komunikasi, kompetensi_teknis)
teacher_avg  = avg(kehadiran, kelengkapan_jurnal, sikap, laporan_akhir)

final_score  = round(industry_avg * W_industry + teacher_avg * W_teacher, 2)

W_industry = settings['assessment.weight.industry']  default 0.60
W_teacher  = settings['assessment.weight.teacher']   default 0.40

Grade: A ≥ 90 · B ≥ 80 · C ≥ 70 · D ≥ 60 · E < 60
```

Aturan:
- `W_industry + W_teacher` harus `= 1.00` — divalidasi `UpdateSettingRequest`.
- `calculate()` **menolak** jika salah satu `assessments` belum `submitted_at` → `AssessmentIncompleteException`.
- Bobot yang dipakai **disalin ke** `final_scores.industry_weight` / `teacher_weight` saat hitung. Tanpa snapshot ini, mengubah bobot untuk angkatan berikutnya diam-diam mengubah arti nilai angkatan lama.
- `AssessmentObserver` memanggil recalculate saat `Assessment` disimpan — hanya jika `final_scores.published_at` masih `null`. Nilai yang sudah dipublikasi tidak berubah sendiri.

---

## 9. Daftar Policy

Registrasi eksplisit di `AuthServiceProvider::$policies`. `Gate::before` → bypass super admin.

| Policy | Method | Catatan aturan |
|---|---|---|
| `UserPolicy` | viewAny view create update delete resetPassword impersonate toggleStatus | User tidak bisa hapus / nonaktifkan dirinya sendiri. Super admin terakhir tidak bisa dihapus. |
| `RolePolicy` | viewAny view create update delete assign | Role `super-admin` tidak bisa diubah/dihapus. |
| `AcademicYearPolicy` | viewAny view create update delete activate | Tidak bisa hapus jika punya placement. |
| `DepartmentPolicy` | viewAny view create update delete | Tidak bisa hapus jika punya kelas. |
| `SchoolClassPolicy` | viewAny view create update delete | Tidak bisa hapus jika punya siswa. |
| `StudentPolicy` | viewAny view create update delete import export | Guru/Industri: `view` hanya siswa dalam scope placement-nya. Siswa: hanya dirinya. |
| `TeacherPolicy` | viewAny view create update delete import | Guru boleh `view` profilnya sendiri. |
| `CompanyPolicy` | viewAny view create update delete | Siswa: hanya perusahaan tempat PKL-nya. Industri: hanya perusahaannya. Tidak bisa hapus jika punya placement aktif. |
| `CompanySupervisorPolicy` | viewAny view create update delete | Admin only. |
| `PlacementPolicy` | viewAny view create update delete activate complete cancel | `view` = jalur autorisasi § 3.2. Transisi status hanya Admin; `activate` menolak jika kuota perusahaan penuh atau siswa sudah punya placement aktif. |
| `AttendancePolicy` | viewAny view checkIn checkOut verify update delete | `checkIn`: siswa pemilik + placement `active` + dalam periode + belum absen hari ini. `verify`: guru/industri dalam scope + `verification_status = pending`. Tidak bisa verifikasi absensi sendiri. |
| `JournalPolicy` | viewAny view create update delete reviewAsIndustry reviewAsTeacher | `update`/`delete`: siswa pemilik **dan** `industry_status = pending` (jurnal yang sudah direview terkunci). `reviewAsTeacher`: guru dalam scope **dan** `industry_status = approved` — memaksa urutan Industri → Guru sesuai PRD. |
| `MonitoringPolicy` | viewAny view create update delete | `create`: guru pembimbing placement tsb. `update`/`delete`: hanya guru pembuatnya. Siswa: `view` saja. |
| `AssessmentPolicy` | viewAny view submitAsIndustry submitAsTeacher update | `submitAsIndustry`: industri dalam scope + placement `active`/`completed`. Setelah `submitted_at` terisi, hanya Admin yang bisa buka kunci. Siswa **tidak** boleh melihat assessment mentah — hanya `final_scores` yang dipublikasi. |
| `FinalScorePolicy` | view calculate publish unpublish | Siswa: `view` hanya jika `published_at !== null` **dan** placement miliknya. `unpublish` mencatat alasan ke activity log. |
| `AttachmentPolicy` | view create delete | `view` mengikuti policy parent (`attachable`). `delete`: uploader atau admin. |
| `SettingPolicy` | view update | Super admin update; Admin PKL view. |
| `ActivityLogPolicy` | viewAny view | SA + Admin PKL. Read-only — tidak ada method write. |
| `ReportExportPolicy` | viewAny view download delete | Hanya pemilik export atau super admin. |

### 9.1 Trait bersama

`App\Policies\Concerns\ChecksPlacementAccess` — satu implementasi § 3.2, dipakai `Attendance/Journal/Monitoring/Assessment/FinalScorePolicy`:

```
protected function relatedToPlacement(User $user, Placement $placement): bool
```

Tanpa trait ini, aturan scope tersebar di 5 policy dan pasti menyimpang seiring waktu.

---

## 10. Dashboard Widget per Role

`GET /dashboard` → `DashboardController` → `DashboardService::forUser()`. Payload beda per role; komponen React dipilih dari `role` yang di-share.

Semua widget agregat lewat `DashboardRepository` dengan query teragregasi (`selectRaw` + `groupBy`) dan **cache 5 menit** per role+user. Melakukan count lewat koleksi Eloquent di dashboard = N+1 paling mahal di aplikasi ini.

### Super Admin

| Widget | Tipe | Isi |
|---|---|---|
| Total Pengguna | stat | per role, breakdown |
| Total Siswa PKL | stat | + delta vs periode lalu |
| Total Perusahaan | stat | aktif / nonaktif |
| PKL Aktif / Selesai | stat pair | |
| Kesehatan Sistem | stat | queue pending, failed jobs, ukuran storage |
| Aktivitas Terbaru | list | 10 activity log terakhir |
| Grafik Aktivitas | area chart | absensi & jurnal 30 hari |
| Distribusi Role | donut | |

### Admin PKL

| Widget | Tipe | Isi |
|---|---|---|
| Total Siswa PKL | stat | |
| PKL Aktif | stat | |
| PKL Selesai | stat | |
| Total Perusahaan | stat | + total kuota vs terpakai |
| Kehadiran Hari Ini | stat + progress | hadir / izin / sakit / alpha dari placement aktif |
| Jurnal Pending | stat | perlu tindakan |
| Penilaian Belum Lengkap | stat | placement `completed` tanpa `final_scores` |
| Grafik Aktivitas | line chart | absensi & jurnal 30 hari |
| Kehadiran per Jurusan | bar chart | |
| Kuota Perusahaan | table | perusahaan, kuota, terisi, sisa — sorted sisa terkecil |
| Siswa Bermasalah | table | alpha ≥ 3 atau jurnal < 60% |
| PKL Akan Berakhir | list | `end_date` ≤ 14 hari |
| Aktivitas Terbaru | list | |

### Guru Pembimbing

| Widget | Tipe | Isi |
|---|---|---|
| Siswa Bimbingan | stat | total + aktif |
| Jurnal Menunggu Review | stat + CTA | `teacher_status = pending` **dan** `industry_status = approved` |
| Absensi Perlu Verifikasi | stat + CTA | |
| Kunjungan Bulan Ini | stat | + target |
| Penilaian Belum Diisi | stat + CTA | |
| Kehadiran Siswa Bimbingan | bar chart | 7 hari |
| Siswa Perlu Perhatian | table | alpha tinggi / jurnal telat |
| Jadwal Kunjungan | list | placement belum dikunjungi 30 hari |

### Pembimbing Industri

| Widget | Tipe | Isi |
|---|---|---|
| Siswa PKL di Perusahaan | stat | aktif |
| Kehadiran Hari Ini | stat + list | siapa sudah check-in |
| Jurnal Menunggu Review | stat + CTA | `industry_status = pending` |
| Absensi Perlu Verifikasi | stat + CTA | |
| Penilaian Belum Diisi | stat + CTA | placement `active`/`completed` tanpa assessment industri |
| Aktivitas Siswa | line chart | jurnal terkirim 14 hari |
| Daftar Siswa | table | nama, kelas, kehadiran %, jurnal % |

### Siswa

| Widget | Tipe | Isi |
|---|---|---|
| Status Absensi Hari Ini | action card | tombol Check In / Check Out — **widget utama**, paling atas di mobile |
| Info Penempatan | card | perusahaan, pembimbing, periode |
| Progres PKL | progress | hari berjalan / total hari |
| Rekap Kehadiran | stat group | hadir / izin / sakit / alpha |
| Jurnal Hari Ini | action card | status isi + CTA |
| Kelengkapan Jurnal | progress | terisi / hari kerja berlalu |
| Status Jurnal | donut | pending / approved / rejected |
| Jurnal Ditolak | alert list | perlu revisi — muncul hanya jika ada |
| Nilai Akhir | card | hanya jika `published_at` terisi; jika belum → "Nilai belum dipublikasikan" |
| Riwayat Terbaru | timeline | 5 aktivitas terakhir |

---

## 11. Urutan Implementasi

| Fase | Isi | Selesai jika |
|---|---|---|
| **0 — Fondasi** | PHP `^8.4`; PostgreSQL; install Spatie Permission (+ config UUID morph key), Sanctum, activitylog, excel/pdf; trait `HasUuidPrimaryKey`; `ApiResponse`; enum; layout+sidebar shell; CI hijau | `composer test` lulus di CI |
| **1 — Auth & RBAC** | users, roles, permissions, seeder, login/logout/reset, `EnsureUserIsActive`, share permission ke Inertia | Feature test tiap role login & lihat sidebar benar |
| **2 — Master Data** | academic_years, departments, school_classes, students, teachers, companies, company_supervisors + CRUD + policy + UI | CRUD test tiap resource, akses lintas-role ditolak |
| **3 — Penempatan** | placements + transisi status + kuota + partial unique index | Test kuota penuh & double-active ditolak |
| **4 — Absensi** ✅ | attendances, check-in/out, foto, geolokasi, verifikasi, `MarkAbsentStudentsJob` | Test double check-in, luar periode, geofence |
| **5 — Jurnal** | journals 2-stage, attachments, kunci setelah review | Test urutan review dipaksa (Guru ditolak sebelum Industri) |
| **6 — Monitoring** | monitorings + attachments | |
| **7 — Penilaian** | assessments, assessment_scores, final_scores, `ScoreCalculatorService`, publikasi | Unit test rumus + snapshot bobot; test siswa tak lihat nilai belum publish |
| **8 — Dashboard** | widget seluruh role + cache | Test payload per role; assert jumlah query |
| **9 — Laporan** | ReportService, export async, riwayat | Test download hanya oleh pemilik |
| **10 — Sistem** | users, roles, settings, activity log, impersonate | |

Urutan mengikuti prioritas coverage CLAUDE.md § Testing: Authentication → Attendance → Journal → Assessment → Placement. Fase 3 mendahului 4–5 karena `placements` adalah FK-nya.

## 12. Definition of Done (per fitur)

Dari CLAUDE.md, tanpa pengecualian:

- [ ] Migration ada **dan reversible** (`migrate:rollback` diuji)
- [ ] Model + factory + relasi + cast
- [ ] Form Request validation
- [ ] Policy terdaftar + diuji
- [ ] Controller tipis (delegasi ke Service/Action)
- [ ] UI responsif mobile-first, Shadcn, tanpa custom CSS
- [ ] Feature test + Unit test
- [ ] Permission terdaftar di seeder
- [ ] Audit log tercatat
- [ ] `composer ci:check` hijau (Pint + PHPStan + ESLint + tsc + Pest)
