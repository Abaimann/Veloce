# Handoff & Ringkasan Proyek — Veloce (Fase 1)

Dokumen ini dibuat agar sesi/akun Antigravity baru dapat langsung memahami kondisi terkini, arsitektur, environment lokal, dan konvensi proyek **Veloce** tanpa perlu menelusuri ulang dari awal.

---

## 1. Status Terakhir Proyek
- **Lingkup Selesai**: Seluruh milestone Fase 1 (**M1–M6**) sudah selesai dibangun dan terverifikasi penuh:
  - **M1**: Fondasi skema PostgreSQL, enum, design tokens single-source (`resources/css/theme.css`), dan komponen UI Blade (`resources/views/components/`).
  - **M2**: Autentikasi berbasis session + rate limiter, tautan undangan aktivasi bertanda tangan (`AcceptInvitationController`), 6 role & matrix isolasi data (`RoleDataScopeMatrix`), manajemen karyawan, dan Master Data (`departemen`, `divisi`, `jadwal kerja`, `lokasi kantor`, `hari libur`).
  - **M3**: Absensi selfie kamera depan (`getUserMedia` tanpa upload galeri), kalkulator jarak Haversine + Geofence (`HaversineCalculator`, `GeofenceEvaluator`), deteksi anomali (`flags` `jsonb` + indeks GIN: `low_gps_accuracy`, `client_clock_skew`, `duplicate_selfie_hash`), watermark nama/waktu WIB/koordinat (`intervention/image` v4), penyimpanan privat (`storage/app/private/selfies/YYYY/MM`), endpoint media terotorisasi (`/media/absensi/...`), dan perintah pruning `attendance:prune-selfies`.
  - **M4**: Pengajuan & persetujuan berjenjang Izin / Cuti / Sakit / WFH (`LeaveApprovalMatrix`, `WorkdayCalculator`, pemotongan saldo transaksi + `lockForUpdate()`, auto-generate baris `attendances` saat disetujui, serta izin check-in luar kantor untuk tanggal WFH disetujui).
  - **M5**: Dashboard khusus per role (`admin`, `employee`, `payroll`), inbox persetujuan (`/persetujuan`), monitoring absensi tim (`/manajemen/absensi`), notifikasi in-app (`/notifikasi`), profil & ubah kata sandi (`/profil`), serta aset PWA (`public/manifest.webmanifest`, `public/sw.js`, `public/icons/icon-192.png`, `public/icons/icon-512.png`).
  - **M6**: Verifikasi end-to-end Laravel Pint, Pest suite, theme token compliance, dan seeder demo idempoten.
- **Penyempurnaan UI Terakhir**:
  - Layout desktop dibuat *full-width* (menghapus `max-w-5xl` pada `<header>` & `<main>` di `resources/views/layouts/app.blade.php`) dengan sidebar desktop sticky (`256px`).
  - Branding penuh diperbarui ke **Veloce** (logo SVG baru di `public/brand/logo.svg` dengan cache-busting `?v=filemtime(...)` di `resources/views/components/brand-logo.blade.php`, `lang/id/ui.php`, `public/manifest.webmanifest`, `config/attendance.php`, serta `OfficeLocation` di PostgreSQL).
  - Sidebar desktop kini memiliki tombol toggle (`Kecilkan sidebar` / `Perluas sidebar`) dengan animasi halus (`300ms cubic-bezier(0.22, 1, 0.36, 1)`) ke mode *icon-only* (`80px`) yang tersimpan di `localStorage` (`veloce_sidebar_collapsed`).

---

## 2. Environment Lokal & Cara Menjalankan
- **Stack**: Laravel 13, PHP 8.3+, PostgreSQL (`pgsql`), Tailwind CSS v4 + `@fontsource/plus-jakarta-sans`, Alpine.js, Pest, Laravel Pint.
- **Database PostgreSQL**:
  - Database utama: `veloce` (konfigurasi di `.env`, ter-ignore Git).
  - Database testing Pest: `veloce_testing` (konfigurasi di `.env.testing`, ter-ignore Git).
- **Port Server Lokal**:
  - Port `8000` dipakai proyek lain di mesin user (`Simulasi-ERP`); server Veloce dijalankan di **`http://127.0.0.1:8010`**:
    ```bash
    php artisan serve --host=127.0.0.1 --port=8010
    ```
- **Perintah Build & Verifikasi**:
  ```bash
  php artisan optimize:clear
  npm run build
  .\vendor\bin\pint --test
  php artisan test
  ```
- **Hasil Verifikasi Terakhir (sebelum commit ini)**:
  - `npm run build`: sukses (`app-HY7o0nai.css`, `app-BW0E9E6r.js`).
  - `.\vendor\bin\pint --test`: `passed`.
  - `php artisan test`: **67 passed (774 assertions)**.

---

## 3. Akun Demo (`DatabaseSeeder`)
Password akun demo membaca variabel `DEMO_DEFAULT_PASSWORD` di `.env` lokal Anda:
- `super_admin`: `admin@veloce.test`
- `hr_manager`: `hr@veloce.test`
- `payroll`: `payroll@veloce.test`
- `dept_manager`: `manager.eng@veloce.test`, `manager.qal@veloce.test`
- `division_head`: `head.prd@veloce.test`, `head.mnt@veloce.test`, `head.qc@veloce.test`, `head.log@veloce.test`
- `employee` (Staff): `operator.prd@veloce.test`, `teknisi.mnt@veloce.test`, `admin.eng@veloce.test`, `inspector.qc@veloce.test`, `staff.log@veloce.test`
- `employee` (Magang / `EmploymentType::INTERN`): `magang.prd@veloce.test`

---

## 4. Aturan & Konvensi Penting Kode
1. **Theme Token Compliance (`ThemeTokenComplianceTest`)**:
   - Seluruh palet warna didefinisikan di satu file: `resources/css/theme.css` dan dipetakan di `resources/css/app.css`.
   - **Dilarang** memakai `#hex`, `rgb(`, `hsl(`, atau class warna default Tailwind (`slate-*`, `gray-*`, `blue-*`, dll.) di file `.blade.php` — wajib gunakan token semantik (`bg-surface`, `bg-card`, `bg-brand-navy`, `text-text`, `text-muted`, `border-border`, `text-on-brand`, dll.).
2. **Standar Kode & Verifikasi**:
   - Selalu jalankan `.\vendor\bin\pint --test` dan `php artisan test` setelah mengubah kode PHP/Blade.
   - Setiap perubahan CSS/JS atau Blade layout memerlukan `npm run build` dan `php artisan view:clear`.
   - Bahasa antarmuka pengguna menggunakan Bahasa Indonesia (`lang/id/`).

---

## 5. Peta Direktori Inti
- `app/Enums/` — `RoleName`, `EmploymentType`, `AttendanceStatus`, `LeaveRequestType`, `LeaveRequestStatus`, `ApprovalStatus`
- `app/Actions/` — Aksi bisnis atomik (`Auth/`, `Employee/`, `Attendance/`, `Leave/`)
- `app/Policies/` & `app/Models/` — Kontrol akses dan query scope (`scopeVisibleTo`, `scopePendingForApprover`)
- `app/Support/` — `Attendance/` (`WorkdayCalculator`, `AnomalyFlagDetector`, `SelfieWatermarker`) & `Geo/` (`HaversineCalculator`, `GeofenceEvaluator`)
- `routes/web.php` — Seluruh rute aplikasi web
- `resources/views/layouts/` — `app.blade.php` (shell + collapsible desktop sidebar + mobile bottom nav) dan `guest.blade.php`
- `resources/css/theme.css` & `resources/css/app.css` — Token tema & utilitas CSS termasuk transisi sidebar
- `resources/js/app.js` & `resources/js/camera-attendance.js` — Komponen Alpine (`sidebarShell`, `serverClock`, `attendanceCamera`)
- `tests/` — Feature & Unit test Pest (67 test case)
