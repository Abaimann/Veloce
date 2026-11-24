# Veloce — Sistem Absensi & Manajemen Kinerja (Fase 1)

Aplikasi absensi selfie berbasis geolokasi dan pengajuan izin/cuti/sakit/WFH berjenjang untuk **Veloce**, dibangun dengan pendekatan *mobile-first* menggunakan **Laravel 13**, **PHP 8.3+**, **PostgreSQL**, **Blade + Tailwind CSS v4 + Alpine.js**, serta **Pest** untuk pengujian otomatis.

> Dokumen ini mencakup ruang lingkup **Fase 1** (Fondasi Organisasi, Autentikasi Undangan, Absensi Selfie + Geofence + Watermark, Pengajuan & Persetujuan Izin/Cuti/Sakit/WFH, Notifikasi, dan PWA). Fitur di luar Fase 1 (potongan gaji, penandaan alpha otomatis, koreksi absen, target/tugas Fase 2, payroll/slip gaji Fase 4) sengaja tidak disertakan pada rilis ini.

---

## Fitur Utama Fase 1

- **Autentikasi & Aktivasi Undangan**: Login berbasis session bawaan Laravel (tanpa Breeze/Jetstream) dengan *rate limiter*, serta tautan undangan bertanda tangan (*signed URL* + token SHA-256 kedaluwarsa) agar karyawan baru mengatur kata sandinya sendiri tanpa pernah diketahui pembuat akun.
- **Matriks Role & Isolasi Data**: 6 role (`super_admin`, `hr_manager`, `dept_manager`, `division_head`, `employee`, `payroll`) yang dijaga berlapis melalui **Policy** dan **Eloquent Query Scope** (`scopeVisibleTo`, `scopePendingForApprover`).
- **Absensi Selfie Kamera Langsung**: Menggunakan `getUserMedia` kamera depan saja (tanpa unggah galeri), *resize* di klien maks lebar `720px` JPEG, verifikasi geofence berbasis `HaversineCalculator` terhadap `office_locations` aktif, pencatatan waktu server resmi (`Asia/Jakarta`), status `late` otomatis, serta *watermark* nama, tanggal/jam WIB, dan koordinat melalui `intervention/image` v4.
- **Storage Privat & Deteksi Anomali**: Foto selfie dan lampiran surat sakit disimpan di disk privat (`storage/app/private`) per tahun/bulan dan disajikan lewat endpoint terotorisasi (`Cache-Control: private, no-store`). Anomali (`accuracy > 100m`, selisih `client_time > 5 menit`, duplikat SHA-256 foto sebelum watermark) disimpan pada kolom `flags` (`jsonb` + indeks GIN) untuk tinjauan HR tanpa menolak absensi.
- **Pengajuan Izin/Cuti/Sakit/WFH Berjenjang**: Alur persetujuan dinamis (`employee` → `division_head` → `dept_manager` → `hr_manager`; pemohon `division_head`/`dept_manager`/`hr_manager` naik ke atasan berikutnya hingga `super_admin` khusus pemohon `hr_manager`), pemotongan saldo cuti dengan `DB::transaction` + `lockForUpdate()`, auto-generate record `attendances` pada hari kerja efektif (melewati akhir pekan dan `holidays`), dan pembukaan akses check-in luar kantor untuk tanggal `WFH` yang disetujui.
- **Desain Mobile-First & PWA**: Bottom tab bar mobile (`Beranda`, `Izin`, tombol menonjol `Absen`, `Persetujuan` ber-badge untuk approver, `Riwayat`, `Profil`), sidebar desktop untuk pengelola, font lokal `Plus Jakarta Sans` (`@fontsource/plus-jakarta-sans`), serta `manifest.webmanifest` + ikon `192px` & `512px` (tanpa mode offline absen agar selalu mengacu ke waktu server).

---

## Struktur Direktori Domain

```text
app/
├── Actions/          # Business actions atomik per domain (Auth, Employee, Attendance, Leave)
├── Console/Commands/ # Scheduled command (attendance:prune-selfies)
├── Enums/            # PHP Backed Enum (RoleName, EmploymentType, AttendanceStatus, LeaveRequestStatus, ApprovalStatus)
├── Http/
│   ├── Controllers/  # Controller tipis per domain (Auth, Attendance, Leave, Approval, Management, Media)
│   ├── Middleware/   # EnsureUserIsActive
│   └── Requests/     # FormRequest validasi & otorisasi input
├── Models/           # Eloquent models, casts, relasi, dan query scopes (scopeVisibleTo, scopePendingForApprover)
├── Notifications/    # Notifikasi in-app (database channel) untuk approver dan pemohon
├── Policies/         # EmployeePolicy, MasterDataPolicy, AttendancePolicy, LeaveRequestPolicy
└── Support/
    ├── Attendance/   # WorkdayCalculator, AnomalyFlagDetector, SelfieWatermarker
    └── Geo/          # HaversineCalculator, GeofenceEvaluator, GeofenceResult
```

---

## Prasyarat & Cara Setup Lokal

### 1. Prasyarat
- **PHP 8.3+** dengan ekstensi `pdo_pgsql`, `pgsql`, `gd`, `exif`, `intl`, `mbstring`, `fileinfo`, `zip`
- **PostgreSQL 15+** (terverifikasi berjalan pada PostgreSQL 18.3)
- **Composer 2.x**
- **Node.js 20+ & NPM**

### 2. Instalasi & Konfigurasi
```bash
# 1. Pasang dependensi backend & frontend
composer install
npm install

# 2. Salin konfigurasi environment
cp .env.example .env
cp .env.example .env.testing

# 3. Generate application key
php artisan key:generate
```

Buat dua database di PostgreSQL (misalnya `veloce` untuk aplikasi dan `veloce_testing` untuk pengujian Pest), lalu isi kredensial database serta variabel demo pada `.env` dan `.env.testing`.

### 3. Migrasi, Seeder & Build Aset
```bash
# Jalankan migrasi & seeder demo pada database utama
php artisan migrate --seed

# Kompilasi CSS (theme tokens + Tailwind v4) & JS (Alpine + kamera)
npm run build
```

Untuk pengembangan lokal:
```bash
# Jalankan server Laravel & Vite secara bersamaan
composer run dev
```

Untuk penjadwalan penghapusan foto selfie otomatis (setiap pukul `02:00 WIB`):
```bash
php artisan schedule:work
# atau jalankan manual:
php artisan attendance:prune-selfies
php artisan attendance:prune-selfies --days=90
```

---

## Konfigurasi Environment (`.env`)

| Variabel | Default | Keterangan |
| :--- | :--- | :--- |
| `APP_NAME` | `Veloce` | Nama aplikasi yang ditampilkan pada header, title, dan halaman login |
| `APP_LOCALE` | `id` | Bahasa antarmuka & validasi (`lang/id/`) |
| `APP_TIMEZONE` | `Asia/Jakarta` | Zona waktu resmi server untuk `work_date` dan timestamp WIB |
| `DB_CONNECTION` | `pgsql` | Driver database PostgreSQL |
| `DB_DATABASE` | `veloce` | Nama database PostgreSQL (`veloce_testing` pada `.env.testing`) |
| `ATTENDANCE_INVITATION_EXPIRY_HOURS` | `72` | Masa berlaku tautan undangan aktivasi akun (jam) |
| `ATTENDANCE_MAX_ACCURACY_METERS` | `100` | Batas akurasi GPS sebelum ditandai flag `low_gps_accuracy` |
| `ATTENDANCE_MAX_CLOCK_SKEW_SECONDS` | `300` | Batas selisih waktu klien vs server (detik) sebelum flag `client_clock_skew` |
| `ATTENDANCE_SELFIE_RETENTION_DAYS` | `90` | Umur retensi foto selfie sebelum dihapus oleh `attendance:prune-selfies` |
| `ATTENDANCE_ALLOW_NON_WORKDAY` | `false` | Mengizinkan/memblokir absensi pada akhir pekan & hari libur |
| `ATTENDANCE_GEOFENCE_ON_CHECKOUT` | `true` | Mewajibkan validasi radius kantor saat absen pulang (kecuali WFH) |
| `DEMO_DEFAULT_PASSWORD` | *(wajib diisi)* | Kata sandi akun demo yang digunakan oleh `DatabaseSeeder` |
| `DEMO_SUPER_ADMIN_EMAIL` | `admin@veloce.test` | Email akun `super_admin` hasil seeder |
| `DEMO_HR_MANAGER_EMAIL` | `hr@veloce.test` | Email akun `hr_manager` hasil seeder |
| `DEMO_PAYROLL_EMAIL` | `payroll@veloce.test` | Email akun `payroll` hasil seeder |
| `DEMO_OFFICE_NAME` | `Veloce HQ - Cikarang` | Nama lokasi kantor awal |
| `DEMO_OFFICE_LAT` | `-6.3105800` | Latitude lokasi kantor dummy (mudah diubah lewat `.env` atau Master Data) |
| `DEMO_OFFICE_LNG` | `107.1436200` | Longitude lokasi kantor dummy |
| `DEMO_OFFICE_RADIUS_METERS` | `150` | Radius geofence dalam meter |

> **Catatan Seeder Demo**: `DatabaseSeeder` membuat 1 `super_admin`, 1 `hr_manager`, 1 `payroll`, 2 departemen operasional (`ENG` & `QAL`) yang masing-masing memiliki 2 divisi (`ENG-PRD`, `ENG-MNT`, `QAL-QC`, `QAL-LOG`), `dept_manager`, `division_head`, karyawan staff (termasuk 1 karyawan langsung di bawah departemen tanpa divisi), serta 1 anak magang (`EmploymentType::INTERN`).

---

## Mengganti Tema Warna & Logo Perusahaan

### 1. Mengganti Tema Warna (`resources/css/theme.css`)
Seluruh palet warna aplikasi didefinisikan **di satu file**: [`resources/css/theme.css`](resources/css/theme.css). Tidak ada kode hex warna atau kelas warna bawaan Tailwind yang di-hardcode di file Blade (dijamin oleh `ThemeTokenComplianceTest`).

Untuk mengganti warna identitas, cukup ubah variabel CSS di `:root` pada `resources/css/theme.css`:
- `--brand-navy`, `--brand-ink`, `--brand-navy-soft` (warna utama header, tombol sekunder, tab aktif)
- `--brand-green`, `--brand-green-strong`, `--brand-green-soft` (aksen, indikator aktif, tombol `Absen Masuk`)
- `--surface`, `--card`, `--text`, `--muted`, `--border`, `--on-brand`
- Token status (`--status-success*`, `--status-warning*`, `--status-info*`, `--status-danger*`, `--status-violet*`)

Setelah mengubah `resources/css/theme.css`, jalankan:
```bash
npm run build
```

### 2. Mengganti Logo & Ikon PWA
- Logo header & halaman login membaca `public/brand/logo.svg` (atau `public/brand/logo.png` jika SVG tidak ada). Ganti file di [`public/brand/`](public/brand/) tanpa perlu mengubah view Blade.
- Ikon PWA berada di `public/icons/icon-192.png` dan `public/icons/icon-512.png`, dengan konfigurasi di `public/manifest.webmanifest`.

---

## Matriks Role & Alur Persetujuan

| Role | Cakupan Data | Aksi Utama |
| :--- | :--- | :--- |
| `employee` (`staff` / `intern`) | Hanya data sendiri | Absen selfie, riwayat, ajukan & batalkan izin/cuti/sakit/WFH |
| `division_head` | Anggota divisinya + diri sendiri | Semua hak `employee` + approver tahap 1 untuk anggota divisinya |
| `dept_manager` | Seluruh karyawan di departemennya | Semua hak `employee` + approver tahap 2 (atau tahap 1 jika tanpa ketua divisi) + buat akun baru hanya di departemennya (`employee`/`division_head`) |
| `hr_manager` | Semua departemen & divisi | Semua hak `employee` + pantau anomali `flags` absensi + buat akun lintas departemen (non-`super_admin`) + approver tahap HR & approver untuk `dept_manager` |
| `payroll` | Ringkasan agregat read-only | Hanya login & melihat dashboard ringkas Fase 1; ditolak mengubah data absen/izin/master |
| `super_admin` | Master data & seluruh akun | Kelola departemen, divisi, jadwal kerja, lokasi kantor, hari libur, akun; hanya menjadi approver jika pemohonnya `hr_manager` |

---

## Catatan Keputusan Teknis & Bisnis (`config/attendance.php`)

1. **Enum PHP Backed String (bukan Enum PostgreSQL)**: Kolom status (`attendances.status`, `leave_requests.status`, `leave_request_approvals.status`, `employees.employment_type`) disimpan sebagai `varchar(32)` dengan PHP Backed Enum di `app/Enums/` agar mudah dikembangkan di Fase 2–4 tanpa migrasi tipe enum PostgreSQL.
2. **Saldo Cuti Awal (`default_annual_leave_days`)**: Secara default pada `config/attendance.php`, `staff` dan `executive` mendapat `12` hari/tahun sedangkan `intern` (anak magang) mendapat `0` hari/tahun (tetap dapat mengajukan Sakit, Izin, Cuti Tanpa Gaji, dan WFH).
3. **Ambang Persetujuan HR (`hr_approval_after_days`)**: Pada `DatabaseSeeder`, Cuti Tahunan & Cuti Tanpa Gaji mewajibkan langkah HR jika `> 3` hari kerja; Sakit (wajib lampiran) & Izin jika `> 2` hari kerja; dan WFH `null` (cukup sampai Manager Departemen). Seluruh aturan per jenis dapat diubah di tabel `leave_types`.
4. **Absensi Hari Libur / Akhir Pekan**: Default `allow_non_workday_attendance = false` (dapat diubah via `.env` bila sewaktu-waktu kebijakan lembur/shift diaktifkan pada fase berikutnya).
5. **Pengajuan Lintas Tahun**: `require_same_year_leave_request = true` mencegah ambiguitas pemotongan kuota `leave_balances` per tahun kalender; pengajuan lintas tahun diminta dipisah per tahun.
6. **Concurrency Control**: Finalisasi cuti tahunan menjalankan `lockForUpdate()` pada baris `leave_requests`, `leave_request_approvals`, dan `leave_balances` di dalam `DB::transaction` sehingga persetujuan ganda tidak mungkin memotong saldo dua kali atau membuat saldo negatif.

---

## Pengujian & Kualitas Kode

```bash
# Jalankan seluruh test Pest terhadap database veloce_testing
php artisan test

# Periksa standar format kode Laravel Pint
./vendor/bin/pint --test
```
