<?php

declare(strict_types=1);

use App\Actions\Leave\ApproveLeaveRequestAction;
use App\Actions\Leave\SubmitLeaveRequestAction;
use App\Enums\AttendanceStatus;
use App\Enums\LeaveRequestStatus;
use App\Enums\RoleName;
use App\Models\Attendance;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\OfficeLocation;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    Storage::fake((string) config('attendance.private_disk', 'local'));
    $this->seed();
});

afterEach(function (): void {
    Carbon::setTestNow();
    CarbonImmutable::setTestNow();
});

test('pengurangan saldo cuti tahunan tepat sesuai hari kerja efektif melewati akhir pekan dan hari libur nasional', function (): void {
    $staff = User::query()->where('email', 'operator.prd@veloce.test')->firstOrFail();
    $hrManager = User::role(RoleName::HR_MANAGER->value)->firstOrFail();
    $annualLeave = LeaveType::query()->where('code', 'annual_leave')->firstOrFail();

    $submitAction = app(SubmitLeaveRequestAction::class);
    $approveAction = app(ApproveLeaveRequestAction::class);

    // 2026-08-14 (Jumat) s.d. 2026-08-18 (Selasa):
    // 15 (Sabtu), 16 (Minggu), 17 (Libur Kemerdekaan RI) -> efektif hanya 2 hari kerja!
    $augustLeave = $submitAction->execute(
        $staff->employee,
        $annualLeave,
        CarbonImmutable::parse('2026-08-14', 'Asia/Jakarta'),
        CarbonImmutable::parse('2026-08-18', 'Asia/Jakarta'),
        'Cuti sekitar hari kemerdekaan.',
    );

    expect($augustLeave->total_work_days)->toBe(2);

    $balanceBeforeFinal = LeaveBalance::query()
        ->where('employee_id', $staff->employee->id)
        ->where('year', 2026)
        ->firstOrFail();
    expect($balanceBeforeFinal->used_days)->toBe(0);

    $approveAction->execute($hrManager, $augustLeave);

    $balanceAfterAugust = $balanceBeforeFinal->fresh();
    expect($balanceAfterAugust->entitled_days)->toBe(12)
        ->and($balanceAfterAugust->used_days)->toBe(2)
        ->and($balanceAfterAugust->remainingDays())->toBe(10);

    // 2026-12-24 (Kamis) s.d. 2026-12-28 (Senin):
    // 25 (Libur Natal), 26 (Sabtu), 27 (Minggu) -> efektif hanya 2 hari kerja!
    $decemberLeave = $submitAction->execute(
        $staff->employee,
        $annualLeave,
        CarbonImmutable::parse('2026-12-24', 'Asia/Jakarta'),
        CarbonImmutable::parse('2026-12-28', 'Asia/Jakarta'),
        'Cuti sekitar hari raya natal.',
    );

    expect($decemberLeave->total_work_days)->toBe(2);

    $approveAction->execute($hrManager, $decemberLeave);

    $balanceAfterDecember = $balanceAfterAugust->fresh();
    expect($balanceAfterDecember->used_days)->toBe(4)
        ->and($balanceAfterDecember->remainingDays())->toBe(8);

    // Rentang yang hanya berisi akhir pekan + hari libur harus ditolak (0 hari kerja).
    expect(fn () => $submitAction->execute(
        $staff->employee,
        $annualLeave,
        CarbonImmutable::parse('2026-08-15', 'Asia/Jakarta'),
        CarbonImmutable::parse('2026-08-17', 'Asia/Jakarta'),
        'Coba ajukan pada akhir pekan dan libur.',
    ))->toThrow(ValidationException::class);

    // Rentang lintas tahun harus ditolak.
    expect(fn () => $submitAction->execute(
        $staff->employee,
        $annualLeave,
        CarbonImmutable::parse('2026-12-31', 'Asia/Jakarta'),
        CarbonImmutable::parse('2027-01-04', 'Asia/Jakarta'),
        'Coba ajukan lintas pergantian tahun.',
    ))->toThrow(ValidationException::class);
});

test('pengajuan cuti tahunan ditolak bila saldo tidak cukup dan anak magang boleh sakit, izin, cuti tanpa gaji, serta wfh', function (): void {
    $staff = User::query()->where('email', 'operator.prd@veloce.test')->firstOrFail();
    $intern = User::query()->where('email', 'magang.prd@veloce.test')->firstOrFail();

    $annualLeave = LeaveType::query()->where('code', 'annual_leave')->firstOrFail();
    $sickLeave = LeaveType::query()->where('code', 'sick')->firstOrFail();
    $permissionLeave = LeaveType::query()->where('code', 'permission')->firstOrFail();
    $unpaidLeave = LeaveType::query()->where('code', 'unpaid_leave')->firstOrFail();
    $wfhLeave = LeaveType::query()->where('code', 'wfh')->firstOrFail();

    $submitAction = app(SubmitLeaveRequestAction::class);

    // Karyawan tetap sisa saldo 1 hari ditolak mengajukan 2 hari cuti tahunan.
    LeaveBalance::query()
        ->where('employee_id', $staff->employee->id)
        ->where('year', 2026)
        ->update(['used_days' => 11]);

    expect(fn () => $submitAction->execute(
        $staff->employee,
        $annualLeave,
        CarbonImmutable::parse('2026-11-24', 'Asia/Jakarta'),
        CarbonImmutable::parse('2026-11-25', 'Asia/Jakarta'),
        'Coba ajukan melebihi sisa saldo.',
    ))->toThrow(ValidationException::class);

    // Anak magang memiliki kuota cuti tahunan 0 -> ditolak mengajukan Cuti Tahunan.
    $internBalance = LeaveBalance::query()
        ->where('employee_id', $intern->employee->id)
        ->where('year', 2026)
        ->firstOrFail();
    expect($internBalance->entitled_days)->toBe(0);

    expect(fn () => $submitAction->execute(
        $intern->employee,
        $annualLeave,
        CarbonImmutable::parse('2026-11-02', 'Asia/Jakarta'),
        CarbonImmutable::parse('2026-11-02', 'Asia/Jakarta'),
        'Coba ajukan cuti tahunan untuk magang.',
    ))->toThrow(ValidationException::class);

    // Namun anak magang BOLEH mengajukan Sakit, Izin, Cuti Tanpa Gaji, dan WFH.
    $sickRequest = $submitAction->execute(
        $intern->employee,
        $sickLeave,
        CarbonImmutable::parse('2026-11-03', 'Asia/Jakarta'),
        CarbonImmutable::parse('2026-11-03', 'Asia/Jakarta'),
        'Sakit dengan surat keterangan dokter.',
        UploadedFile::fake()->image('surat-dokter-magang.jpg', 640, 800),
    );

    $permissionRequest = $submitAction->execute(
        $intern->employee,
        $permissionLeave,
        CarbonImmutable::parse('2026-11-04', 'Asia/Jakarta'),
        CarbonImmutable::parse('2026-11-04', 'Asia/Jakarta'),
        'Izin bimbingan skripsi di kampus.',
    );

    $unpaidRequest = $submitAction->execute(
        $intern->employee,
        $unpaidLeave,
        CarbonImmutable::parse('2026-11-05', 'Asia/Jakarta'),
        CarbonImmutable::parse('2026-11-05', 'Asia/Jakarta'),
        'Cuti tanpa gaji keperluan keluarga.',
    );

    $wfhRequest = $submitAction->execute(
        $intern->employee,
        $wfhLeave,
        CarbonImmutable::parse('2026-11-06', 'Asia/Jakarta'),
        CarbonImmutable::parse('2026-11-06', 'Asia/Jakarta'),
        'Menyelesaikan laporan dokumentasi dari rumah.',
    );

    expect($sickRequest->status)->toBe(LeaveRequestStatus::PENDING)
        ->and($permissionRequest->status)->toBe(LeaveRequestStatus::PENDING)
        ->and($unpaidRequest->status)->toBe(LeaveRequestStatus::PENDING)
        ->and($wfhRequest->status)->toBe(LeaveRequestStatus::PENDING);
});

test('simulasi race double-approve pada step terakhir gagal di percobaan kedua dengan ValidationException dan saldo tidak pernah negatif', function (): void {
    $staff = User::query()->where('email', 'operator.prd@veloce.test')->firstOrFail();
    $hrManager = User::role(RoleName::HR_MANAGER->value)->firstOrFail();
    $annualLeave = LeaveType::query()->where('code', 'annual_leave')->firstOrFail();

    // Set kuota tersisa tepat 2 hari agar percobaan potong ganda langsung terlihat jika gagal menjaga konsistensi.
    LeaveBalance::query()
        ->where('employee_id', $staff->employee->id)
        ->where('year', 2026)
        ->update([
            'entitled_days' => 2,
            'used_days' => 0,
        ]);

    $leaveRequest = app(SubmitLeaveRequestAction::class)->execute(
        $staff->employee,
        $annualLeave,
        CarbonImmutable::parse('2026-11-24', 'Asia/Jakarta'),
        CarbonImmutable::parse('2026-11-25', 'Asia/Jakarta'),
        'Pengajuan dua hari untuk uji race condition approval.',
    );

    $approveAction = app(ApproveLeaveRequestAction::class);

    // Panggilan final step pertama sukses.
    $final = $approveAction->execute($hrManager, $leaveRequest);
    expect($final->status)->toBe(LeaveRequestStatus::APPROVED);

    // Panggilan kedua berturut-turut untuk step terakhir gagal dengan ValidationException.
    expect(fn () => $approveAction->execute($hrManager, $leaveRequest))
        ->toThrow(ValidationException::class);

    $balance = LeaveBalance::query()
        ->where('employee_id', $staff->employee->id)
        ->where('year', 2026)
        ->firstOrFail();

    expect($balance->used_days)->toBe(2)
        ->and($balance->remainingDays())->toBe(0);
});

test('final approval cuti, sakit, dan izin otomatis membuat baris attendances di hari kerja serta memblokir selfie check-in', function (): void {
    $noDivStaff = User::query()->where('email', 'admin.eng@veloce.test')->firstOrFail();
    $hrManager = User::role(RoleName::HR_MANAGER->value)->firstOrFail();
    $office = OfficeLocation::query()->active()->firstOrFail();

    $annualLeave = LeaveType::query()->where('code', 'annual_leave')->firstOrFail();
    $sickLeave = LeaveType::query()->where('code', 'sick')->firstOrFail();
    $permissionLeave = LeaveType::query()->where('code', 'permission')->firstOrFail();

    $submitAction = app(SubmitLeaveRequestAction::class);
    $approveAction = app(ApproveLeaveRequestAction::class);

    // 1. Cuti melewati akhir pekan + 17 Agustus (2026-08-14 .. 2026-08-18) -> hanya 14 & 18 Agustus.
    $leaveReq = $submitAction->execute(
        $noDivStaff->employee,
        $annualLeave,
        CarbonImmutable::parse('2026-08-14', 'Asia/Jakarta'),
        CarbonImmutable::parse('2026-08-18', 'Asia/Jakarta'),
        'Cuti tahunan untuk sinkronisasi absensi.',
    );
    $approveAction->execute($hrManager, $leaveReq);

    $leaveAttendances = Attendance::query()
        ->where('employee_id', $noDivStaff->employee->id)
        ->where('leave_request_id', $leaveReq->id)
        ->orderBy('work_date')
        ->get();

    expect($leaveAttendances->map(fn (Attendance $a): string => $a->work_date->toDateString())->all())
        ->toBe(['2026-08-14', '2026-08-18'])
        ->and($leaveAttendances->every(fn (Attendance $a): bool => $a->status === AttendanceStatus::LEAVE))
        ->toBeTrue();

    // 2. Sakit (2026-11-24) -> status sick dan memblokir selfie check-in.
    $sickReq = $submitAction->execute(
        $noDivStaff->employee,
        $sickLeave,
        CarbonImmutable::parse('2026-11-24', 'Asia/Jakarta'),
        CarbonImmutable::parse('2026-11-24', 'Asia/Jakarta'),
        'Sakit demam.',
        UploadedFile::fake()->image('dokter.jpg', 500, 700),
    );
    $approveAction->execute($hrManager, $sickReq);

    $sickAttendance = Attendance::query()
        ->where('employee_id', $noDivStaff->employee->id)
        ->whereDate('work_date', '2026-11-24')
        ->firstOrFail();

    expect($sickAttendance->status)->toBe(AttendanceStatus::SICK)
        ->and($sickAttendance->leave_request_id)->toBe($sickReq->id);

    // 3. Izin (2026-11-25) -> status permission.
    $permReq = $submitAction->execute(
        $noDivStaff->employee,
        $permissionLeave,
        CarbonImmutable::parse('2026-11-25', 'Asia/Jakarta'),
        CarbonImmutable::parse('2026-11-25', 'Asia/Jakarta'),
        'Izin mengurus dokumen kependudukan.',
    );
    $approveAction->execute($hrManager, $permReq);

    $permAttendance = Attendance::query()
        ->where('employee_id', $noDivStaff->employee->id)
        ->whereDate('work_date', '2026-11-25')
        ->firstOrFail();

    expect($permAttendance->status)->toBe(AttendanceStatus::PERMISSION)
        ->and($permAttendance->leave_request_id)->toBe($permReq->id);

    // Uji pemblokiran selfie check-in pada tanggal sakit (2026-11-24) dan cuti (2026-08-14).
    foreach (['2026-11-24 08:05:00', '2026-08-14 08:05:00'] as $blockedTimestamp) {
        $now = CarbonImmutable::parse($blockedTimestamp, 'Asia/Jakarta');
        Carbon::setTestNow($now);
        CarbonImmutable::setTestNow($now);

        $this->actingAs($noDivStaff)->postJson(route('attendance.check-in'), [
            'photo' => UploadedFile::fake()->image('selfie-blocked.jpg', 800, 800),
            'lat' => $office->lat,
            'lng' => $office->lng,
            'accuracy' => 10.0,
            'client_time' => $now->toIso8601String(),
        ])->assertUnprocessable();
    }
});

test('pengajuan wfh yang disetujui mengizinkan check-in dan check-out dari luar kantor dengan status wfh', function (): void {
    $staff = User::query()->where('email', 'operator.prd@veloce.test')->firstOrFail();
    $hrManager = User::role(RoleName::HR_MANAGER->value)->firstOrFail();
    $office = OfficeLocation::query()->active()->firstOrFail();
    $wfhLeave = LeaveType::query()->where('code', 'wfh')->firstOrFail();

    $wfhRequest = app(SubmitLeaveRequestAction::class)->execute(
        $staff->employee,
        $wfhLeave,
        CarbonImmutable::parse('2026-11-24', 'Asia/Jakarta'),
        CarbonImmutable::parse('2026-11-24', 'Asia/Jakarta'),
        'Mengerjakan laporan produksi jarak jauh dari rumah.',
    );

    $approveAction = app(ApproveLeaveRequestAction::class);
    $approvedWfh = $approveAction->execute($hrManager, $wfhRequest);

    expect($approvedWfh->status)->toBe(LeaveRequestStatus::APPROVED)
        ->and(Attendance::query()
            ->where('employee_id', $staff->employee->id)
            ->whereDate('work_date', '2026-11-24')
            ->exists())->toBeFalse();

    // Check-in dari koordinat jauh di luar radius kantor.
    $checkInNow = CarbonImmutable::parse('2026-11-24 08:25:00', 'Asia/Jakarta');
    Carbon::setTestNow($checkInNow);
    CarbonImmutable::setTestNow($checkInNow);

    $farLat = $office->lat + 0.5;
    $farLng = $office->lng + 0.5;

    $checkInResponse = $this->actingAs($staff)->postJson(route('attendance.check-in'), [
        'photo' => UploadedFile::fake()->image('selfie-wfh-in.jpg', 800, 800),
        'lat' => $farLat,
        'lng' => $farLng,
        'accuracy' => 15.0,
        'client_time' => $checkInNow->toIso8601String(),
    ]);

    $checkInResponse->assertCreated()
        ->assertJsonPath('attendance.status', AttendanceStatus::WFH->value);

    // Check-out dari koordinat jauh di luar radius kantor juga diizinkan.
    $checkOutNow = CarbonImmutable::parse('2026-11-24 17:10:00', 'Asia/Jakarta');
    Carbon::setTestNow($checkOutNow);
    CarbonImmutable::setTestNow($checkOutNow);

    $checkOutResponse = $this->actingAs($staff)->postJson(route('attendance.check-out'), [
        'photo' => UploadedFile::fake()->image('selfie-wfh-out.jpg', 800, 800),
        'lat' => $farLat,
        'lng' => $farLng,
        'accuracy' => 15.0,
        'client_time' => $checkOutNow->toIso8601String(),
    ]);

    $checkOutResponse->assertOk()
        ->assertJsonPath('attendance.status', AttendanceStatus::WFH->value);

    $record = Attendance::query()
        ->where('employee_id', $staff->employee->id)
        ->whereDate('work_date', '2026-11-24')
        ->firstOrFail();

    expect($record->status)->toBe(AttendanceStatus::WFH)
        ->and($record->leave_request_id)->toBe($wfhRequest->id)
        ->and($record->check_in_at)->not->toBeNull()
        ->and($record->check_out_at)->not->toBeNull()
        ->and($record->office_location_id)->toBeNull()
        ->and($record->check_in_distance_meters)->toBeNull()
        ->and($record->check_out_distance_meters)->toBeNull();
});
