# Veloce — Sistem Absensi, Kinerja & Payroll Perusahaan

**Presensi akurat, target terukur, dan gaji transparan dalam satu sistem.**  
Aplikasi web *mobile-first* manajemen kehadiran karyawan, target berjenjang, bukti kerja Google Drive PT, audit log, dan perhitungan gaji otomatis berbasis **Laravel 13**, **PHP 8.3+**, **PostgreSQL**, **Blade + Tailwind CSS v4 + Alpine.js**, serta **Pest**.

![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20)
![PHP](https://img.shields.io/badge/PHP-8.3%2B-777BB4)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-15%2B-4169E1)
![Tailwind](https://img.shields.io/badge/Tailwind%20CSS-v4-06B6D4)
![Tests](https://img.shields.io/badge/Tests-72%20passed-brightgreen)
![Pint](https://img.shields.io/badge/Code%20Style-Pint%20Passed-0B8E43)

---

## Fitur Utama

- **Semua Peran Wajib Absen (Selfie + Geofence + Watermark)**: Setiap karyawan, anak magang, ketua divisi, manajer departemen, HR, Payroll, hingga Super Admin melakukan presensi kamera depan langsung (tanpa unggah galeri) dengan validasi jarak `HaversineCalculator`, timestamp server WIB, dan *watermark* otomatis via `intervention/image` v4.
- **Storage Privat & Deteksi Anomali**: Foto selfie dan surat sakit disimpan di disk privat (`storage/app/private`) dan dilayani lewat endpoint terotorisasi (`Cache-Control: private, no-store`). Anomali (`accuracy > 100m`, selisih `client_time > 5 menit`, duplikat SHA-256) dicatat pada `flags` (`jsonb` + indeks GIN).
- **Persetujuan Absensi/Izin/Cuti/WFH Khusus HR**: Seluruh pengajuan karyawan, ketua divisi, manajer departemen, payroll, dan admin diproses oleh **HR Manager** (khusus pengajuan `hr_manager` disetujui oleh **Super Admin**), lengkap dengan penguncian saldo (`lockForUpdate()`) dan sinkronisasi otomatis ke tabel `attendances`.
- **Target Berjenjang Berbobot (`Bulanan ← Mingguan`) & Bukti Harian Google Drive PT**:
  - **Manager Departemen** menetapkan target bulanan departemen dan memantau progresnya.
  - **Ketua Divisi** membuat target mingguan lengkap dengan **Bobot (%)** terhadap target bulanan, sehingga progres target bulanan otomatis terakumulasi secara berbobot.
  - **Karyawan & Anak Magang** mengunggah bukti kerja harian yang fleksibel langsung ke **Google Drive PT** melalui integrasi Service Account REST v3 (`<Departemen>/<Divisi>/<YYYY-MM>`). Setelah **Ketua Divisi mencentang cocok**, poin target mingguan & progres bulanan naik seketika.
- **Timeline Tugas & Log Harian (`/timeline`)**: Peta visual jadwal **Bulanan → Mingguan (Pekan 1–5) → Bukti Kerja Harian (Drive PT)** beserta ringkasan log kronologis presensi harian.
- **Perhitungan Gaji Otomatis Sistem (`/gaji`)**: Kalkulasi gaji bulanan otomatis (`Gaji Pokok + Tunjangan Tetap - Potongan Telat - Potongan Alpha/Unpaid`) yang dapat dilihat, dihitung ulang, dan difinalisasi oleh **Payroll**, **HR Manager**, dan **Super Admin**.
- **Manajemen Karyawan, Pencarian, Filter & Masa Kontrak (`/manajemen/karyawan`)**: Pencarian teks bebas (nama, NIK, email, jabatan), filter multi-kriteria (departemen, divisi, peran, tipe kerja, status akun), tampilan sisa masa kontrak (`PKWTT` vs `Contract`/`Intern`), serta ubah profil/kontrak/gaji dan undangan aktivasi akun.
- **Log Aktivitas CRUD Admin (`/manajemen/log-aktivitas`)**: Jejak audit perubahan tambah, ubah, dan hapus data oleh admin maupun pengguna sistem (`spatie/laravel-activitylog`) lengkap dengan filter aksi, modul, pelaku, dan rincian *before/after*.
- **Antarmuka Precision Command (Desktop & Mobile-First + PWA)**: Sidebar *deep-ink* dengan mode *collapse*, topbar kaca *frosted*, drawer navigasi slide-over mobile untuk seluruh role, kartu mobile adaptif bebas scroll samping, dan dukungan PWA (`manifest.webmanifest` + Service Worker).

---

## Perintah Cepat

| Perintah | Fungsi |
| :--- | :--- |
| `composer install && npm install` | Memasang seluruh dependensi PHP dan frontend |
| `php artisan migrate --seed` | Menjalankan migrasi skema & mengisi 7 departemen, 43 karyawan, dan data operasional |
| `composer run dev` | Menjalankan server Laravel, queue, log, dan Vite dev server bersamaan |
| `npm run build` | Mengompilasi aset produksi (`resources/css/theme.css` + Tailwind v4 + JS) |
| `php artisan test` | Menjalankan **72 pengujian otomatis Pest** (865 assertions) |
| `./vendor/bin/pint --test` | Memeriksa kepatuhan standar format kode Laravel Pint |
| `php artisan attendance:prune-selfies` | Membersihkan foto selfie lebih lama dari retensi (default `90` hari) |

---

## Struktur Direktori Domain

```text
app/
├── Actions/          # Business actions atomik (Auth, Employee, Attendance, Leave, Payroll)
├── Console/Commands/ # Scheduled command (attendance:prune-selfies)
├── Enums/            # Backed Enum (RoleName, EmploymentType, AttendanceStatus, WorkSubmissionStatus, dll.)
├── Http/
│   ├── Controllers/  # Controller per domain (Attendance, Leave, Approval, Payroll, Work, Timeline, Management)
│   ├── Middleware/   # EnsureUserIsActive
│   └── Requests/     # FormRequest validasi & otorisasi
├── Models/           # Eloquent models, casts, relasi, & scopes (scopeVisibleTo, scopePendingForApprover)
├── Notifications/    # Notifikasi in-app (database channel)
├── Policies/         # EmployeePolicy, MasterDataPolicy, AttendancePolicy, LeaveRequestPolicy
└── Support/
    ├── Attendance/   # WorkdayCalculator, AnomalyFlagDetector, SelfieWatermarker
    ├── Drive/        # GoogleDriveService (OAuth2 Service Account JWT + Drive v3 Multipart Upload)
    └── Geo/          # HaversineCalculator, GeofenceEvaluator, GeofenceResult
```

---

## Prasyarat & Setup Lokal

1. **Prasyarat**: PHP **8.3+** (`pdo_pgsql`, `pgsql`, `gd`, `exif`, `intl`, `mbstring`, `fileinfo`, `openssl`), **PostgreSQL 15+**, **Composer 2.x**, dan **Node.js 20+**.
2. **Konfigurasi Environment**:
   ```bash
   cp .env.example .env
   cp .env.example .env.testing
   php artisan key:generate
   ```
   Siapkan database PostgreSQL (`veloce` & `veloce_testing`), sesuaikan kredensial di `.env` dan `.env.testing`, lalu jalankan:
   ```bash
   php artisan migrate --seed
   npm run build
   ```

### Konfigurasi Variabel Environment (`.env`)

| Variabel | Default | Keterangan |
| :--- | :--- | :--- |
| `APP_NAME` | `Veloce` | Nama aplikasi pada judul, topbar, dan halaman masuk |
| `APP_LOCALE` | `id` | Bahasa antarmuka & validasi (`lang/id/`) |
| `APP_TIMEZONE` | `Asia/Jakarta` | Zona waktu resmi server WIB |
| `DB_CONNECTION` | `pgsql` | Driver PostgreSQL |
| `ATTENDANCE_INVITATION_EXPIRY_HOURS` | `72` | Masa berlaku tautan undangan aktivasi akun (jam) |
| `ATTENDANCE_MAX_ACCURACY_METERS` | `100` | Batas akurasi GPS sebelum flag `low_gps_accuracy` |
| `PAYROLL_LATE_DEDUCTION_PER_INCIDENT` | `50000` | Potongan keterlambatan per kejadian (Rupiah) |
| `GOOGLE_DRIVE_ROOT_FOLDER_ID` | *(opsional)* | ID Folder root Google Drive PT untuk unggah kerjaan harian |
| `GOOGLE_DRIVE_CREDENTIALS_PATH` | *(opsional)* | Lokasi file JSON Google Service Account (atau pakai `GOOGLE_DRIVE_CREDENTIALS_JSON`) |
| `DEMO_DEFAULT_PASSWORD` | *(wajib saat seed)* | Kata sandi akun demo `DatabaseSeeder` |

---

## Matriks Role & Akses

| Role | Cakupan & Tanggung Jawab |
| :--- | :--- |
| `employee` (`staff` / `intern`) | Absensi selfie, riwayat presensi, ajukan/batalkan izin/cuti/WFH, lihat timeline tugas, dan upload bukti kerja harian ke Google Drive PT |
| `division_head` | Hak `employee` + buat target mingguan divisi dengan **bobot (%)**, pantau absensi tim divisi, dan **centang konfirmasi** bukti kerja harian anggota divisi |
| `dept_manager` | Hak `employee` + buat target bulanan departemen, pantau progres divisi, pantau absensi & daftar karyawan departemennya, tambah akun di departemennya |
| `hr_manager` | Hak `employee` + **satu-satunya approver absensi/izin/cuti/WFH** seluruh karyawan/manager, pantau anomali absensi, lihat gaji sistem, kelola karyawan lintas departemen |
| `payroll` | Hak `employee` + akses penuh modul perhitungan, hitung ulang, dan finalisasi gaji sistem (`/gaji`) serta rekap kehadiran perusahaan |
| `super_admin` | Hak `employee` + Master Data, semua akun & kontrak, approver khusus pengajuan `hr_manager`, akses gaji sistem, serta **Log Aktivitas CRUD** |

---

## Tema & Kepatuhan Desain

Seluruh token warna aplikasi berada di satu file [`resources/css/theme.css`](resources/css/theme.css) dan dijamin oleh `ThemeTokenComplianceTest` agar tidak ada warna hardcode (`#hex`, `rgb`, `hsl`, atau kelas palet default Tailwind) di view Blade. Logo dan ikon PWA dapat disesuaikan langsung melalui [`public/brand/`](public/brand/) dan [`public/icons/`](public/icons/).

---

## Lisensi

Hak cipta &copy; Veloce. Dikembangkan sebagai sistem manajemen kehadiran, target kinerja, dan penggajian internal perusahaan.
