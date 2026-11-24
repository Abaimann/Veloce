<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Zona Waktu Bisnis Resmi
    |--------------------------------------------------------------------------
    | Seluruh pencatatan work_date dan timestamp absensi dievaluasi dalam
    | zona waktu Asia/Jakarta (WIB) menggunakan waktu server.
    */
    'timezone' => env('APP_TIMEZONE', 'Asia/Jakarta'),

    /*
    |--------------------------------------------------------------------------
    | Tautan Undangan Aktivasi Akun
    |--------------------------------------------------------------------------
    */
    'invitation_expiry_hours' => (int) env('ATTENDANCE_INVITATION_EXPIRY_HOURS', 72),

    /*
    |--------------------------------------------------------------------------
    | Batas Anomali & Geofence Absensi
    |--------------------------------------------------------------------------
    | Nilai-nilai di bawah ini menentukan kapan sistem menandai flag
    | mencurigakan untuk tinjauan HR (tidak menolak absensi).
    */
    'max_acceptable_accuracy_meters' => (int) env('ATTENDANCE_MAX_ACCURACY_METERS', 100),
    'max_client_clock_skew_seconds' => (int) env('ATTENDANCE_MAX_CLOCK_SKEW_SECONDS', 300),
    'enforce_geofence_on_checkout' => (bool) env('ATTENDANCE_GEOFENCE_ON_CHECKOUT', true),
    'allow_non_workday_attendance' => (bool) env('ATTENDANCE_ALLOW_NON_WORKDAY', false),

    /*
    |--------------------------------------------------------------------------
    | Media Selfie & Lampiran Privat
    |--------------------------------------------------------------------------
    | Menggunakan disk privat ('local' -> storage/app/private) agar file tidak
    | pernah dapat diakses langsung via URL publik.
    */
    'private_disk' => env('ATTENDANCE_PRIVATE_DISK', 'local'),
    'selfie_max_width' => 720,
    'selfie_jpeg_quality' => 75,
    'selfie_retention_days' => (int) env('ATTENDANCE_SELFIE_RETENTION_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | Kebijakan Cuti & Izin
    |--------------------------------------------------------------------------
    */
    'require_same_year_leave_request' => true,
    'default_annual_leave_days' => [
        'staff' => 12,
        'executive' => 12,
        'intern' => 0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Aturan Perhitungan Gaji Sistem (Payroll)
    |--------------------------------------------------------------------------
    */
    'payroll' => [
        'late_deduction_per_incident' => (int) env('PAYROLL_LATE_DEDUCTION', 50000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Konfigurasi Seeder Demo (Dari Environment)
    |--------------------------------------------------------------------------
    */
    'demo' => [
        'default_password' => env('DEMO_DEFAULT_PASSWORD'),
        'super_admin_email' => env('DEMO_SUPER_ADMIN_EMAIL', 'admin@veloce.test'),
        'hr_manager_email' => env('DEMO_HR_MANAGER_EMAIL', 'hr@veloce.test'),
        'payroll_email' => env('DEMO_PAYROLL_EMAIL', 'payroll@veloce.test'),
        'office_name' => env('DEMO_OFFICE_NAME', 'Veloce HQ - Cikarang'),
        'office_lat' => (float) env('DEMO_OFFICE_LAT', -6.3105800),
        'office_lng' => (float) env('DEMO_OFFICE_LNG', 107.1436200),
        'office_radius_meters' => (int) env('DEMO_OFFICE_RADIUS_METERS', 150),
    ],
];
