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
  - **Data Operasional Lengkap**: Ditambahkan `database/seeders/OperationalDataSeeder.php` yang mengisi data operasional realistis (219 baris presensi 3 minggu terakhir, presensi hari ini, 8 pengajuan cuti/izin/sakit/WFH dengan status approved, pending, dan rejected, serta notifikasi in-app untuk menguji alur persetujuan lintas role).

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

## 3. Akun Demo & Struktur Organisasi Perusahaan (`DatabaseSeeder`)
Password seluruh akun demo membaca variabel `DEMO_DEFAULT_PASSWORD` di `.env` lokal Anda (default demo: `VeloceDemo!2026`).

**Struktur 7 Departemen & 14 Divisi (Total 41 Karyawan + 2 Akun Sistem)**:
1. **HR & GA (`HRGA`)**:
   - HR & GA Manager: `hr@veloce.test` (Ratna Dewi)
   - Divisi Rekrutmen (`HRGA-REC`): Head `head.rec@veloce.test`, Staff `staff.rec@veloce.test`
   - Divisi General Affairs (`HRGA-GA`): Head `head.ga@veloce.test`, Staff `staff.ga@veloce.test`
2. **Engineering & Produksi (`ENG`)**:
   - Engineering & Production Manager: `manager.eng@veloce.test` (Budi Santoso)
   - Divisi Produksi Kabel (`ENG-PRD`): Head `head.prd@veloce.test`, Operator `operator.prd@veloce.test`, Magang `magang.prd@veloce.test`
   - Divisi Maintenance (`ENG-MNT`): Head `head.mnt@veloce.test`, Teknisi `teknisi.mnt@veloce.test`
   - Staff Departemen Langsung: Admin `admin.eng@veloce.test`
3. **Quality & Logistik (`QAL`)**:
   - Quality & Logistics Manager: `manager.qal@veloce.test` (Andi Kurniawan)
   - Divisi Quality Control (`QAL-QC`): Head `head.qc@veloce.test`, Inspector `inspector.qc@veloce.test`
   - Divisi Logistik & Gudang (`QAL-LOG`): Head `head.log@veloce.test`, Staff `staff.log@veloce.test`
4. **Teknologi Informasi & Sistem Digital (`IT`)**:
   - IT Manager: `manager.it@veloce.test` (Irfan Hakim)
   - Divisi Infrastruktur & Jaringan (`IT-INF`): Head `head.itinf@veloce.test`, Network `network.it@veloce.test`, Support `support.it@veloce.test`
   - Divisi Software & ERP (`IT-DEV`): Head `head.itdev@veloce.test`, Developer `programmer.it@veloce.test`, Analyst `analyst.it@veloce.test`
5. **Finance, Accounting & Pajak (`FIN`)**:
   - Finance & Accounting Manager: `manager.fin@veloce.test` (Sri Wahyuni)
   - Divisi Akuntansi (`FIN-ACC`): Head `head.acc@veloce.test`, Staff `staff.acc@veloce.test`, Kasir `staff.fin@veloce.test`
   - Divisi Perpajakan & Audit (`FIN-TAX`): Head `head.tax@veloce.test`, Staff `staff.tax@veloce.test`
6. **Supply Chain & Pengadaan Material (`SCM`)**:
   - SCM & Procurement Manager: `manager.scm@veloce.test` (Bambang Irawan)
   - Divisi Pengadaan Material (`SCM-PRC`): Head `head.prc@veloce.test`, Buyer Logam `buyer.scm@veloce.test`, Buyer Polimer `buyer2.scm@veloce.test`
   - Divisi PPIC (`SCM-PLN`): Head `head.ppic@veloce.test`, Staff PPIC `staff.ppic@veloce.test`
7. **Penjualan & Pemasaran Komersial (`MKT`)**:
   - Sales & Marketing Commercial Manager: `manager.mkt@veloce.test` (Surya Pratama)
   - Divisi OEM Otomotif (`MKT-OEM`): Head `head.oem@veloce.test`, Sales OEM `sales.oem@veloce.test`
   - Divisi Aftermarket & Retail (`MKT-AFT`): Head `head.aft@veloce.test`, Sales Aftermarket `sales.aft@veloce.test`
8. **Akun Sistem Khusus**:
   - Super Admin: `admin@veloce.test` (Administrator Sistem)
   - Payroll: `payroll@veloce.test` (Tim Payroll & Kompensasi)

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
