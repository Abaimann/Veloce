<?php

declare(strict_types=1);

use App\Enums\AttendanceStatus;
use App\Enums\RoleName;
use App\Models\Attendance;
use App\Models\OfficeLocation;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake((string) config('attendance.private_disk', 'local'));
    Storage::fake('public');
    $this->seed();
});

afterEach(function (): void {
    Carbon::setTestNow();
    CarbonImmutable::setTestNow();
});

test('check-in tepat waktu mencatat status present dan mengembalikan json 201', function (): void {
    $now = CarbonImmutable::parse('2026-11-24 08:10:00', 'Asia/Jakarta');
    Carbon::setTestNow($now);
    CarbonImmutable::setTestNow($now);

    $user = User::query()->where('email', 'operator.prd@veloce.test')->firstOrFail();
    $office = OfficeLocation::query()->active()->firstOrFail();

    $response = $this->actingAs($user)->postJson(route('attendance.check-in'), [
        'photo' => UploadedFile::fake()->image('selfie-in.jpg', 900, 900),
        'lat' => $office->lat,
        'lng' => $office->lng,
        'accuracy' => 15.5,
        'client_time' => $now->toIso8601String(),
    ]);

    $response->assertCreated()
        ->assertJsonPath('redirect_url', route('dashboard'))
        ->assertJsonPath('attendance.status', AttendanceStatus::PRESENT->value);

    $attendance = Attendance::query()
        ->where('employee_id', $user->employee->id)
        ->first();

    expect($attendance)->not->toBeNull()
        ->and($attendance->status)->toBe(AttendanceStatus::PRESENT)
        ->and($attendance->work_date->toDateString())->toBe('2026-11-24')
        ->and($attendance->check_in_at)->not->toBeNull()
        ->and($attendance->check_out_at)->toBeNull()
        ->and($attendance->office_location_id)->toBe($office->id);
});

test('check-in melewati batas toleransi mencatat status late', function (): void {
    $now = CarbonImmutable::parse('2026-11-24 08:20:00', 'Asia/Jakarta');
    Carbon::setTestNow($now);
    CarbonImmutable::setTestNow($now);

    $user = User::query()->where('email', 'operator.prd@veloce.test')->firstOrFail();
    $office = OfficeLocation::query()->active()->firstOrFail();

    $this->actingAs($user)->postJson(route('attendance.check-in'), [
        'photo' => UploadedFile::fake()->image('selfie-late.jpg', 900, 900),
        'lat' => $office->lat,
        'lng' => $office->lng,
        'accuracy' => 12.0,
        'client_time' => $now->toIso8601String(),
    ])->assertCreated();

    $attendance = Attendance::query()
        ->where('employee_id', $user->employee->id)
        ->firstOrFail();

    expect($attendance->status)->toBe(AttendanceStatus::LATE);
});

test('check-in dua kali pada hari yang sama ditolak', function (): void {
    $firstNow = CarbonImmutable::parse('2026-11-24 08:05:00', 'Asia/Jakarta');
    Carbon::setTestNow($firstNow);
    CarbonImmutable::setTestNow($firstNow);

    $user = User::query()->where('email', 'operator.prd@veloce.test')->firstOrFail();
    $office = OfficeLocation::query()->active()->firstOrFail();

    $this->actingAs($user)->postJson(route('attendance.check-in'), [
        'photo' => UploadedFile::fake()->image('selfie-1.jpg', 800, 800),
        'lat' => $office->lat,
        'lng' => $office->lng,
        'accuracy' => 10.0,
        'client_time' => $firstNow->toIso8601String(),
    ])->assertCreated();

    $secondNow = CarbonImmutable::parse('2026-11-24 09:00:00', 'Asia/Jakarta');
    Carbon::setTestNow($secondNow);
    CarbonImmutable::setTestNow($secondNow);

    $this->actingAs($user)->postJson(route('attendance.check-in'), [
        'photo' => UploadedFile::fake()->image('selfie-2.jpg', 800, 800),
        'lat' => $office->lat,
        'lng' => $office->lng,
        'accuracy' => 10.0,
        'client_time' => $secondNow->toIso8601String(),
    ])->assertUnprocessable();

    expect(Attendance::query()->where('employee_id', $user->employee->id)->count())->toBe(1);
});

test('check-out sebelum melakukan check-in ditolak', function (): void {
    $now = CarbonImmutable::parse('2026-11-24 17:05:00', 'Asia/Jakarta');
    Carbon::setTestNow($now);
    CarbonImmutable::setTestNow($now);

    $user = User::query()->where('email', 'operator.prd@veloce.test')->firstOrFail();
    $office = OfficeLocation::query()->active()->firstOrFail();

    $this->actingAs($user)->postJson(route('attendance.check-out'), [
        'photo' => UploadedFile::fake()->image('selfie-out.jpg', 800, 800),
        'lat' => $office->lat,
        'lng' => $office->lng,
        'accuracy' => 10.0,
        'client_time' => $now->toIso8601String(),
    ])->assertUnprocessable();

    expect(Attendance::query()->where('employee_id', $user->employee->id)->count())->toBe(0);
});

test('check-in lalu check-out sukses menghasilkan tepat satu record di database', function (): void {
    $checkInNow = CarbonImmutable::parse('2026-11-24 08:02:00', 'Asia/Jakarta');
    Carbon::setTestNow($checkInNow);
    CarbonImmutable::setTestNow($checkInNow);

    $user = User::query()->where('email', 'operator.prd@veloce.test')->firstOrFail();
    $office = OfficeLocation::query()->active()->firstOrFail();

    $this->actingAs($user)->postJson(route('attendance.check-in'), [
        'photo' => UploadedFile::fake()->image('selfie-in.jpg', 800, 800),
        'lat' => $office->lat,
        'lng' => $office->lng,
        'accuracy' => 12.0,
        'client_time' => $checkInNow->toIso8601String(),
    ])->assertCreated();

    $checkOutNow = CarbonImmutable::parse('2026-11-24 17:05:00', 'Asia/Jakarta');
    Carbon::setTestNow($checkOutNow);
    CarbonImmutable::setTestNow($checkOutNow);

    $checkOutResponse = $this->actingAs($user)->postJson(route('attendance.check-out'), [
        'photo' => UploadedFile::fake()->image('selfie-out.jpg', 800, 800),
        'lat' => $office->lat,
        'lng' => $office->lng,
        'accuracy' => 14.0,
        'client_time' => $checkOutNow->toIso8601String(),
    ]);

    $checkOutResponse->assertOk()
        ->assertJsonPath('redirect_url', route('dashboard'));

    $records = Attendance::query()->where('employee_id', $user->employee->id)->get();

    expect($records)->toHaveCount(1)
        ->and($records->first()->check_in_at)->not->toBeNull()
        ->and($records->first()->check_out_at)->not->toBeNull()
        ->and($records->first()->selfie_in_path)->not->toBeNull()
        ->and($records->first()->selfie_out_path)->not->toBeNull();
});

test('absen di luar radius kantor ditolak dengan pesan jarak', function (): void {
    $now = CarbonImmutable::parse('2026-11-24 08:05:00', 'Asia/Jakarta');
    Carbon::setTestNow($now);
    CarbonImmutable::setTestNow($now);

    $user = User::query()->where('email', 'operator.prd@veloce.test')->firstOrFail();
    $office = OfficeLocation::query()->active()->firstOrFail();

    $response = $this->actingAs($user)->postJson(route('attendance.check-in'), [
        'photo' => UploadedFile::fake()->image('selfie-far.jpg', 800, 800),
        'lat' => $office->lat + 0.01,
        'lng' => $office->lng + 0.01,
        'accuracy' => 15.0,
        'client_time' => $now->toIso8601String(),
    ]);

    $response->assertUnprocessable();

    $message = (string) $response->json('message');
    expect($message)->toContain('radius')
        ->and($message)->toContain('m');

    expect(Attendance::query()->where('employee_id', $user->employee->id)->count())->toBe(0);
});

test('rate limiter throttle:attendance memicu respons 429 setelah batas terlampaui', function (): void {
    $now = CarbonImmutable::parse('2026-11-24 08:05:00', 'Asia/Jakarta');
    Carbon::setTestNow($now);
    CarbonImmutable::setTestNow($now);

    $user = User::query()->where('email', 'operator.prd@veloce.test')->firstOrFail();
    $office = OfficeLocation::query()->active()->firstOrFail();

    for ($attempt = 1; $attempt <= 10; $attempt++) {
        $response = $this->actingAs($user)->postJson(route('attendance.check-in'), [
            'photo' => UploadedFile::fake()->image("selfie-rate-{$attempt}.jpg", 640, 480),
            'lat' => $office->lat + 0.01,
            'lng' => $office->lng + 0.01,
            'accuracy' => 15.0,
            'client_time' => $now->toIso8601String(),
        ]);

        expect($response->status())->toBeLessThan(429);
    }

    $throttled = $this->actingAs($user)->postJson(route('attendance.check-in'), [
        'photo' => UploadedFile::fake()->image('selfie-rate-11.jpg', 640, 480),
        'lat' => $office->lat + 0.01,
        'lng' => $office->lng + 0.01,
        'accuracy' => 15.0,
        'client_time' => $now->toIso8601String(),
    ]);

    $throttled->assertStatus(429);
});

test('absen pada hari sabtu, minggu, dan hari libur ditolak ketika allow_non_workday_attendance false', function (string $dateTime): void {
    config(['attendance.allow_non_workday_attendance' => false]);

    $now = CarbonImmutable::parse($dateTime, 'Asia/Jakarta');
    Carbon::setTestNow($now);
    CarbonImmutable::setTestNow($now);

    $user = User::query()->where('email', 'operator.prd@veloce.test')->firstOrFail();
    $office = OfficeLocation::query()->active()->firstOrFail();

    $this->actingAs($user)->postJson(route('attendance.check-in'), [
        'photo' => UploadedFile::fake()->image('selfie-offday.jpg', 800, 800),
        'lat' => $office->lat,
        'lng' => $office->lng,
        'accuracy' => 10.0,
        'client_time' => $now->toIso8601String(),
    ])->assertUnprocessable();

    expect(Attendance::query()->where('employee_id', $user->employee->id)->count())->toBe(0);
})->with([
    'Sabtu akhir pekan' => '2026-11-28 08:05:00',
    'Minggu akhir pekan' => '2026-11-29 08:05:00',
    'Hari Libur Nasional 17 Agustus' => '2026-08-17 08:05:00',
]);

test('role payroll dan akun tanpa profil karyawan ditolak 403 sedangkan karyawan dapat membuka halaman kamera dan riwayat', function (): void {
    $now = CarbonImmutable::parse('2026-11-24 08:05:00', 'Asia/Jakarta');
    Carbon::setTestNow($now);
    CarbonImmutable::setTestNow($now);

    $payroll = User::role(RoleName::PAYROLL->value)->firstOrFail();
    $superAdmin = User::role(RoleName::SUPER_ADMIN->value)->firstOrFail();
    $employeeUser = User::query()->where('email', 'operator.prd@veloce.test')->firstOrFail();

    foreach ([$payroll, $superAdmin] as $restrictedUser) {
        $this->actingAs($restrictedUser)->get(route('attendance.camera'))->assertForbidden();
        $this->actingAs($restrictedUser)->get(route('attendance.history'))->assertForbidden();
        $this->actingAs($restrictedUser)->postJson(route('attendance.check-in'), [])->assertForbidden();
    }

    $this->actingAs($employeeUser)->get(route('attendance.camera'))->assertOk();
    $this->actingAs($employeeUser)->get(route('attendance.history'))->assertOk();
});
