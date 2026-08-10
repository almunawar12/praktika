# PRAKTIKA

## Sistem Monitoring PKL SMK

Version: 1.0

---

# 1. Ringkasan Produk

Praktika adalah aplikasi berbasis web yang digunakan untuk mengelola dan memonitor kegiatan Praktik Kerja Lapangan (PKL) siswa SMK.

Aplikasi membantu sekolah, guru pembimbing, pembimbing industri, dan siswa dalam mengelola seluruh proses PKL mulai dari penempatan siswa, absensi, jurnal harian, monitoring, penilaian hingga laporan akhir.

---

# 2. Tujuan Produk

### Tujuan Bisnis

* Digitalisasi proses PKL sekolah.
* Mengurangi penggunaan buku jurnal fisik.
* Memudahkan monitoring siswa selama PKL.
* Mempermudah proses penilaian.
* Menyediakan laporan PKL secara real-time.

### Tujuan Pengguna

* Siswa mudah mengisi jurnal dan absensi.
* Guru mudah memonitor perkembangan siswa.
* Industri mudah melakukan evaluasi siswa.
* Sekolah memiliki data PKL yang terpusat.

---

# 3. Role Pengguna

## Super Admin

Hak akses:

* Kelola seluruh data sistem
* Kelola pengguna
* Kelola pengaturan aplikasi

---

## Admin PKL

Hak akses:

* Kelola siswa
* Kelola guru pembimbing
* Kelola perusahaan
* Kelola penempatan PKL
* Monitoring seluruh siswa
* Generate laporan

---

## Guru Pembimbing

Hak akses:

* Melihat siswa bimbingan
* Verifikasi jurnal
* Monitoring absensi
* Input hasil kunjungan
* Input penilaian

---

## Pembimbing Industri

Hak akses:

* Melihat siswa PKL
* Verifikasi jurnal
* Verifikasi absensi
* Memberikan penilaian

---

## Siswa

Hak akses:

* Absensi masuk
* Absensi pulang
* Input jurnal harian
* Upload dokumentasi
* Melihat progres PKL
* Melihat nilai

---

# 4. Modul Sistem

## Modul Authentication

Fitur:

* Login
* Logout
* Reset Password
* Role Based Access Control

---

## Modul Dashboard

Menampilkan:

* Total siswa PKL
* Total perusahaan
* PKL aktif
* PKL selesai
* Jurnal pending
* Kehadiran hari ini
* Grafik aktivitas

---

## Modul Master Data

### Jurusan

Field:

* nama
* kode

### Kelas

Field:

* nama
* jurusan_id

### Siswa

Field:

* nis
* nisn
* nama
* email
* no_hp
* kelas_id

### Guru

Field:

* nip
* nama
* email
* no_hp

### Perusahaan

Field:

* nama
* bidang_usaha
* alamat
* pic_nama
* pic_hp
* kuota

---

## Modul Tahun Ajaran

Menaungi (scope) kelas dan penempatan PKL per periode akademik.

Field:

* nama
* tanggal_mulai
* tanggal_selesai
* status (aktif / nonaktif)

---

## Modul Penempatan PKL

Field:

* siswa_id
* perusahaan_id
* guru_id
* academic_year_id
* tanggal_mulai
* tanggal_selesai
* status
* cancel_reason (diisi saat status dibatalkan)

Status:

* draft
* aktif
* selesai
* dibatalkan

Penempatan yang telah melewati tanggal_selesai otomatis berstatus selesai lewat scheduled job (`CompleteExpiredPlacementsJob`), tanpa aksi manual admin.

---

## Modul Absensi

Field:

* placement_id
* tanggal
* check_in
* check_out
* latitude
* longitude
* foto
* status
* verification_status (pending / terverifikasi / ditolak)
* verified_by
* verified_at
* verification_note

Status:

* hadir
* izin
* sakit
* alpha

Validasi GPS Radius: setiap perusahaan memiliki titik koordinat dan radius geofence. Check in/out hanya sah bila jarak siswa (`check_in_distance_m` / `check_out_distance_m`) berada dalam radius tersebut; status hadir mewajibkan foto dan lokasi.

---

## Modul Jurnal Harian

Field:

* placement_id
* tanggal
* aktivitas
* hasil_pekerjaan
* kendala
* dokumentasi

Status:

* pending
* approved
* rejected

---

## Modul Monitoring Guru

Field:

* placement_id
* tanggal_kunjungan
* catatan
* rekomendasi
* dokumentasi

---

## Modul Penilaian

### Penilaian Industri

Kriteria:

* Disiplin
* Tanggung Jawab
* Kerjasama
* Komunikasi
* Kompetensi Teknis

Skala:

0 - 100

### Penilaian Guru

Kriteria:

* Kehadiran
* Kelengkapan Jurnal
* Sikap
* Laporan Akhir

Skala:

0 - 100

### Nilai Akhir

Dihitung sistem dari nilai industri dan nilai guru terboboti (weighted), lalu dipublikasikan.

Field tambahan:

* grade (huruf A–E, diturunkan dari nilai akhir)
* published_at
* published_by

---

## Modul Laporan

Output:

* Rekap Absensi
* Rekap Jurnal
* Nilai PKL
* Monitoring Guru

Format:

* PDF
* Excel

---

# 5. Workflow Utama

## Absensi

Siswa Login
→ Check In
→ Sistem Simpan Lokasi
→ Pembimbing Verifikasi

Siswa Login
→ Check Out
→ Sistem Simpan Lokasi
→ Pembimbing Verifikasi

---

## Jurnal

Siswa Input Jurnal
→ Status Pending
→ Pembimbing Industri Review
→ Guru Review
→ Approved

---

## Penilaian

Pembimbing Industri Input Nilai
→ Guru Input Nilai
→ Sistem Hitung Nilai Akhir
→ Nilai Dipublikasikan

---

# 6. Non Functional Requirements

* Responsive Mobile First
* Laravel 13
* PHP 8.3+
* PostgreSQL
* Inertia React
* TypeScript
* Tailwind CSS
* Shadcn UI
* Spatie Permission
* Queue Support
* Activity Log
* Audit Trail

---

# 7. Future Roadmap

* Mobile App Android
* QR Absensi
* WhatsApp Notification
* E-Signature
* AI Journal Summary
* Alumni Tracking
