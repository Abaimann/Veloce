<?php

declare(strict_types=1);

use App\Actions\Payroll\CalculateMonthlyPayrollAction;
use App\Enums\AttendanceStatus;
use App\Enums\RoleName;
use App\Enums\TargetPeriodType;
use App\Enums\WorkSubmissionStatus;
use App\Models\Attendance;
use App\Models\DailyWorkSubmission;
use App\Models\DepartmentTarget;
use App\Models\DivisionTarget;
use App\Models\OfficeLocation;
use App\Models\PayrollItem;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->seed();
});

afterEach(function (): void {
    Carbon::setTestNow();
    CarbonImmutable::setTestNow();
});

test('hr manager dan admin dapat melihat serta mengubah masa kontrak karyawan', function (): void {
    $hrManager = User::role(RoleName::HR_MANAGER->value)->firstOrFail();
    $staff = User::query()->where('email', 'operator.prd@veloce.test')->firstOrFail();

    $this->actingAs($hrManager)
        ->get(route('management.employees.index'))
        ->assertOk()
        ->assertSee('Masa Kontrak');

    $this->actingAs($hrManager)
        ->get(route('management.employees.edit', $staff->employee))
        ->assertOk()
        ->assertSee('Masa Kontrak');

    $this->actingAs($hrManager)
        ->put(route('management.employees.update', $staff->employee), [
            'name' => $staff->name,
            'employee_number' => $staff->employee->employee_number,
            'department_id' => $staff->employee->department_id,
            'division_id' => $staff->employee->division_id,
            'supervisor_id' => $staff->employee->supervisor_id,
            'work_schedule_id' => $staff->employee->work_schedule_id,
            'employment_type' => $staff->employee->employment_type->value,
            'position_title' => 'Senior Operator Produksi',
            'join_date' => '2025-01-06',
            'contract_start_date' => '2026-01-01',
            'contract_end_date' => '2027-01-01',
            'base_salary' => 8500000,
            'fixed_allowance' => 1500000,
            'is_active' => 1,
        ])
        ->assertRedirect(route('management.employees.index'));

    $updated = $staff->employee->fresh();
    expect($updated->position_title)->toBe('Senior Operator Produksi')
        ->and($updated->contract_start_date?->toDateString())->toBe('2026-01-01')
        ->and($updated->contract_end_date?->toDateString())->toBe('2027-01-01')
        ->and($updated->base_salary)->toBe(8500000);
});

test('admin melihat log aktivitas crud sedangkan peran non admin ditolak 403', function (): void {
    $superAdmin = User::role(RoleName::SUPER_ADMIN->value)->firstOrFail();
    $hrManager = User::role(RoleName::HR_MANAGER->value)->firstOrFail();
    $staff = User::query()->where('email', 'operator.prd@veloce.test')->firstOrFail();

    $this->actingAs($hrManager)->get(route('management.activity-logs.index'))->assertForbidden();
    $this->actingAs($staff)->get(route('management.activity-logs.index'))->assertForbidden();

    // Lakukan perubahan data agar terekam log aktivitas CRUD.
    $this->actingAs($superAdmin)->post(route('management.master.departments.store'), [
        'code' => 'RND',
        'name' => 'Research & Development',
    ])->assertRedirect();

    $this->actingAs($superAdmin)
        ->get(route('management.activity-logs.index', ['event' => 'created']))
        ->assertOk()
        ->assertSee('Log Aktivitas CRUD')
        ->assertSee('Department');
});

test('hr dan payroll dapat melihat gaji yang dihitung sistem sementara manager non hr dan employee ditolak 403', function (): void {
    $now = CarbonImmutable::parse('2026-11-05 09:00:00', 'Asia/Jakarta');
    Carbon::setTestNow($now);
    CarbonImmutable::setTestNow($now);

    $hrManager = User::role(RoleName::HR_MANAGER->value)->firstOrFail();
    $payroll = User::role(RoleName::PAYROLL->value)->firstOrFail();
    $superAdmin = User::role(RoleName::SUPER_ADMIN->value)->firstOrFail();
    $deptManager = User::query()->where('email', 'manager.eng@veloce.test')->firstOrFail();
    $staff = User::query()->where('email', 'operator.prd@veloce.test')->firstOrFail();
    $office = OfficeLocation::query()->active()->firstOrFail();

    // Isi presensi tanggal 2-5 Nov (4 hari kerja: 3 tepat waktu, 1 terlambat).
    foreach (['2026-11-02', '2026-11-03', '2026-11-04'] as $presentDate) {
        Attendance::query()->create([
            'employee_id' => $staff->employee->id,
            'work_date' => $presentDate,
            'check_in_at' => CarbonImmutable::parse("{$presentDate} 07:55:00", 'Asia/Jakarta'),
            'check_out_at' => CarbonImmutable::parse("{$presentDate} 17:05:00", 'Asia/Jakarta'),
            'office_location_id' => $office->id,
            'status' => AttendanceStatus::PRESENT,
            'flags' => [],
        ]);
    }
    Attendance::query()->create([
        'employee_id' => $staff->employee->id,
        'work_date' => '2026-11-05',
        'check_in_at' => CarbonImmutable::parse('2026-11-05 08:25:00', 'Asia/Jakarta'),
        'office_location_id' => $office->id,
        'status' => AttendanceStatus::LATE,
        'flags' => [],
    ]);

    $period = app(CalculateMonthlyPayrollAction::class)->execute(2026, 11, $payroll);
    $staffItem = PayrollItem::query()
        ->where('payroll_period_id', $period->id)
        ->where('employee_id', $staff->employee->id)
        ->firstOrFail();

    $lateRate = (int) config('attendance.payroll.late_deduction_per_incident', 50000);
    expect($staffItem->present_days)->toBe(4)
        ->and($staffItem->late_days)->toBe(1)
        ->and($staffItem->absent_days)->toBe(0)
        ->and($staffItem->late_deduction)->toBe($lateRate)
        ->and($staffItem->net_salary)->toBe(($staffItem->base_salary + $staffItem->fixed_allowance) - $lateRate);

    foreach ([$hrManager, $payroll, $superAdmin] as $allowed) {
        $this->actingAs($allowed)
            ->get(route('payroll.index', ['month' => '2026-11']))
            ->assertOk()
            ->assertSee('Perhitungan Gaji Sistem')
            ->assertSee($staff->name);
    }

    $this->actingAs($deptManager)->get(route('payroll.index'))->assertForbidden();
    $this->actingAs($staff)->get(route('payroll.index'))->assertForbidden();
});

test('target departemen dan divisi, upload kerjaan harian ke Google Drive PT, konfirmasi ketua divisi, dan timeline berjalan utuh', function (): void {
    $now = CarbonImmutable::parse('2026-11-24 14:30:00', 'Asia/Jakarta');
    Carbon::setTestNow($now);
    CarbonImmutable::setTestNow($now);

    $deptManager = User::query()->where('email', 'manager.eng@veloce.test')->firstOrFail();
    $divisionHead = User::query()->where('email', 'head.prd@veloce.test')->firstOrFail();
    $staff = User::query()->where('email', 'operator.prd@veloce.test')->firstOrFail();
    $intern = User::query()->where('email', 'magang.prd@veloce.test')->firstOrFail();
    $otherDivHead = User::query()->where('email', 'head.mnt@veloce.test')->firstOrFail();
    $office = OfficeLocation::query()->active()->firstOrFail();

    // 1. Manager Departemen membuat target bulanan departemen.
    $this->actingAs($deptManager)
        ->post(route('targets.department.store'), [
            'department_id' => $deptManager->employee->department_id,
            'month' => '2026-11',
            'title' => 'Target Kualitas Lini November',
            'target_value' => 10,
            'unit_label' => 'laporan',
        ])
        ->assertRedirect();

    $deptTarget = DepartmentTarget::query()->firstOrFail();

    // Karyawan biasa ditolak membuat target departemen/divisi.
    $this->actingAs($staff)->post(route('targets.department.store'), [])->assertForbidden();
    $this->actingAs($staff)->post(route('targets.division.store'), [])->assertForbidden();

    // 2. Ketua Divisi membuat target MINGGUAN yang menginduk ke Target Bulanan dengan bobot 50%.
    $this->actingAs($divisionHead)
        ->post(route('targets.division.store'), [
            'division_id' => $divisionHead->employee->division_id,
            'department_target_id' => $deptTarget->id,
            'period_type' => TargetPeriodType::WEEKLY->value,
            'title' => 'Audit Mingguan Kabel Pekan 4',
            'start_date' => '2026-11-23',
            'end_date' => '2026-11-27',
            'weight_percent' => 50,
            'target_value' => 4,
            'unit_label' => 'laporan',
        ])
        ->assertRedirect();

    $divTarget = DivisionTarget::query()->where('period_type', TargetPeriodType::WEEKLY->value)->firstOrFail();
    expect((float) $divTarget->progress_percent)->toBe(0.0)
        ->and($divTarget->department_target_id)->toBe($deptTarget->id)
        ->and((float) $divTarget->weight_percent)->toBe(50.0);

    // 3. Siapkan kunci RSA sementara & mock Google Drive API v3 untuk menguji upload kerjaan ke Drive PT.
    $opensslConfigPath = tempnam(sys_get_temp_dir(), 'veloce_ssl_');
    file_put_contents($opensslConfigPath, "[req]\ndistinguished_name=dn\n[dn]\n");
    try {
        $opensslArgs = [
            'config' => $opensslConfigPath,
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];
        $res = openssl_pkey_new($opensslArgs);
        expect($res)->not->toBeFalse();
        openssl_pkey_export($res, $privateKeyPem, null, $opensslArgs);
    } finally {
        @unlink($opensslConfigPath);
    }
    config()->set('services.google_drive.credentials_json', json_encode([
        'client_email' => 'veloce-drive@test.iam.gserviceaccount.com',
        'private_key' => $privateKeyPem,
    ], JSON_THROW_ON_ERROR));
    config()->set('services.google_drive.root_folder_id', 'root-folder-pt-123');

    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'mock-access-token'], 200),
        'https://www.googleapis.com/drive/v3/files*' => Http::response(['id' => 'folder-123', 'files' => [['id' => 'folder-123']]], 200),
        'https://www.googleapis.com/upload/drive/v3/files*' => Http::response([
            'id' => 'drive-file-999',
            'name' => 'DCI-ENG-003_2026-11-24_Laporan-Shift.pdf',
            'webViewLink' => 'https://drive.google.com/file/d/drive-file-999/view',
            'mimeType' => 'application/pdf',
            'size' => 10240,
        ], 200),
    ]);

    // 4. Karyawan & anak magang mengupload bukti kerja harian fleksibel langsung ke Target Mingguan di Google Drive PT.
    $this->actingAs($staff)
        ->post(route('work.store'), [
            'division_target_id' => $divTarget->id,
            'work_date' => '2026-11-24',
            'title' => 'Laporan Output Shift Pagi',
            'description' => 'Selesai 50 roll kabel tanpa cacat.',
            'contribution_value' => 1,
            'work_file' => UploadedFile::fake()->create('laporan.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect(route('work.index'));

    $this->actingAs($intern)
        ->post(route('work.store'), [
            'division_target_id' => $divTarget->id,
            'work_date' => '2026-11-24',
            'title' => 'Checklist Stok Bahan Magang',
            'contribution_value' => 1,
            'work_file' => UploadedFile::fake()->create('stok.pdf', 80, 'application/pdf'),
        ])
        ->assertRedirect(route('work.index'));

    $staffSubmission = DailyWorkSubmission::query()->where('employee_id', $staff->employee->id)->firstOrFail();
    expect($staffSubmission->drive_file_id)->toBe('drive-file-999')
        ->and($staffSubmission->drive_web_view_link)->toBe('https://drive.google.com/file/d/drive-file-999/view')
        ->and($staffSubmission->status)->toBe(WorkSubmissionStatus::PENDING_REVIEW);

    // Ketua divisi lain & diri sendiri ditolak 403 saat mencoba mengonfirmasi.
    $this->actingAs($otherDivHead)->post(route('work.approve', $staffSubmission))->assertForbidden();
    $this->actingAs($staff)->post(route('work.approve', $staffSubmission))->assertForbidden();

    // 5. Ketua Divisi mencentang kerjaan -> persentase mingguan naik (25%, lalu 50%) -> mendorong bulanan sesuai bobot 50% (12.5%, lalu 25%).
    $this->actingAs($divisionHead)
        ->post(route('work.approve', $staffSubmission), ['review_note' => 'Sesuai hasil shift.'])
        ->assertRedirect();

    $divTarget->refresh();
    $deptTarget->refresh();
    expect($divTarget->approved_value)->toBe(1)
        ->and((float) $divTarget->progress_percent)->toBe(25.0)
        ->and($deptTarget->progressPercent())->toBe(12.5);

    $internSubmission = DailyWorkSubmission::query()->where('employee_id', $intern->employee->id)->firstOrFail();
    $this->actingAs($divisionHead)->post(route('work.approve', $internSubmission))->assertRedirect();

    $divTarget->refresh();
    $deptTarget->refresh();
    expect($divTarget->approved_value)->toBe(2)
        ->and((float) $divTarget->progress_percent)->toBe(50.0)
        ->and($deptTarget->progressPercent())->toBe(25.0);

    // Pembuat target mengubah bobot Target Mingguan dari 50% menjadi 80% -> progress Bulanan naik dari 25% menjadi 40%.
    $this->actingAs($staff)
        ->patch(route('targets.division.weight', $divTarget), ['weight_percent' => 80])
        ->assertForbidden();

    $this->actingAs($divisionHead)
        ->patch(route('targets.division.weight', $divTarget), ['weight_percent' => 80])
        ->assertRedirect();

    $deptTarget->refresh();
    expect($deptTarget->progressPercent())->toBe(40.0);

    // 6. Catat presensi masuk + pulang hari ini dan verifikasi Timeline (Bulanan/Mingguan/Bukti Harian) + log harian.
    Attendance::query()->create([
        'employee_id' => $staff->employee->id,
        'work_date' => '2026-11-24',
        'check_in_at' => CarbonImmutable::parse('2026-11-24 07:55:00', 'Asia/Jakarta'),
        'check_out_at' => CarbonImmutable::parse('2026-11-24 17:05:00', 'Asia/Jakarta'),
        'office_location_id' => $office->id,
        'status' => AttendanceStatus::PRESENT,
        'flags' => [],
    ]);

    $this->actingAs($staff)
        ->get(route('timeline.index', ['month' => '2026-11', 'date' => '2026-11-24']))
        ->assertOk()
        ->assertSee('Target Bulanan')
        ->assertSee('Target Mingguan')
        ->assertSee('Bukti Kerja Harian (Drive PT)')
        ->assertSee('Pekan 1')
        ->assertSee('Pekan 4')
        ->assertSee('Target Kualitas Lini November')
        ->assertSee('Audit Mingguan Kabel Pekan 4')
        ->assertSee('Bobot 80%')
        ->assertSee('2/4 laporan (50.0%)')
        ->assertSee('Laporan Output Shift Pagi')
        ->assertSee('07:55 WIB')
        ->assertSee('17:05 WIB');

    // Manager Departemen melihat progress target departemen & timeline tugas.
    $this->actingAs($deptManager)
        ->get(route('targets.index', ['month' => '2026-11']))
        ->assertOk()
        ->assertSee('Target Kualitas Lini November')
        ->assertSee('Audit Mingguan Kabel Pekan 4');

    $this->actingAs($deptManager)
        ->get(route('timeline.index', ['month' => '2026-11', 'date' => '2026-11-24', 'employee_id' => $staff->employee->id]))
        ->assertOk()
        ->assertSee('Audit Mingguan Kabel Pekan 4')
        ->assertSee('Laporan Output Shift Pagi');
});
