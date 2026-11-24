<?php

declare(strict_types=1);

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

test('akurasi gps lebih dari 100 meter tercatat di flags low_gps_accuracy dan absen tetap diterima', function (): void {
    $now = CarbonImmutable::parse('2026-11-24 08:05:00', 'Asia/Jakarta');
    Carbon::setTestNow($now);
    CarbonImmutable::setTestNow($now);

    $user = User::query()->where('email', 'operator.prd@veloce.test')->firstOrFail();
    $office = OfficeLocation::query()->active()->firstOrFail();

    $this->actingAs($user)->postJson(route('attendance.check-in'), [
        'photo' => UploadedFile::fake()->image('selfie-low-gps.jpg', 900, 900),
        'lat' => $office->lat,
        'lng' => $office->lng,
        'accuracy' => 175.25,
        'client_time' => $now->toIso8601String(),
    ])->assertCreated();

    $attendance = Attendance::query()
        ->where('employee_id', $user->employee->id)
        ->firstOrFail();

    $flags = $attendance->flags ?? [];
    $hasFlag = in_array('low_gps_accuracy', $flags, true)
        || array_key_exists('low_gps_accuracy', $flags)
        || str_contains((string) json_encode($flags), 'low_gps_accuracy');

    expect($attendance->hasSuspiciousFlags())->toBeTrue()
        ->and($hasFlag)->toBeTrue();
});

test('selisih client_time lebih dari 5 menit tercatat di flags client_clock_skew', function (): void {
    $now = CarbonImmutable::parse('2026-11-24 08:05:00', 'Asia/Jakarta');
    Carbon::setTestNow($now);
    CarbonImmutable::setTestNow($now);

    $user = User::query()->where('email', 'operator.prd@veloce.test')->firstOrFail();
    $office = OfficeLocation::query()->active()->firstOrFail();

    $this->actingAs($user)->postJson(route('attendance.check-in'), [
        'photo' => UploadedFile::fake()->image('selfie-clock-skew.jpg', 900, 900),
        'lat' => $office->lat,
        'lng' => $office->lng,
        'accuracy' => 18.0,
        'client_time' => $now->subMinutes(12)->toIso8601String(),
    ])->assertCreated();

    $attendance = Attendance::query()
        ->where('employee_id', $user->employee->id)
        ->firstOrFail();

    $flags = $attendance->flags ?? [];
    $hasFlag = in_array('client_clock_skew', $flags, true)
        || array_key_exists('client_clock_skew', $flags)
        || str_contains((string) json_encode($flags), 'client_clock_skew');

    expect($attendance->hasSuspiciousFlags())->toBeTrue()
        ->and($hasFlag)->toBeTrue();
});

test('foto selfie identik pada absensi kedua tercatat sebagai duplicate_selfie_hash', function (): void {
    $seedImage = UploadedFile::fake()->image('identical-source.jpg', 900, 900);
    $identicalBytes = (string) file_get_contents($seedImage->getRealPath());

    $user = User::query()->where('email', 'operator.prd@veloce.test')->firstOrFail();
    $office = OfficeLocation::query()->active()->firstOrFail();

    $dayOne = CarbonImmutable::parse('2026-11-23 08:05:00', 'Asia/Jakarta');
    Carbon::setTestNow($dayOne);
    CarbonImmutable::setTestNow($dayOne);

    $this->actingAs($user)->postJson(route('attendance.check-in'), [
        'photo' => UploadedFile::fake()->createWithContent('selfie-day1.jpg', $identicalBytes),
        'lat' => $office->lat,
        'lng' => $office->lng,
        'accuracy' => 10.0,
        'client_time' => $dayOne->toIso8601String(),
    ])->assertCreated();

    $dayTwo = CarbonImmutable::parse('2026-11-24 08:05:00', 'Asia/Jakarta');
    Carbon::setTestNow($dayTwo);
    CarbonImmutable::setTestNow($dayTwo);

    $this->actingAs($user)->postJson(route('attendance.check-in'), [
        'photo' => UploadedFile::fake()->createWithContent('selfie-day2.jpg', $identicalBytes),
        'lat' => $office->lat,
        'lng' => $office->lng,
        'accuracy' => 10.0,
        'client_time' => $dayTwo->toIso8601String(),
    ])->assertCreated();

    $firstAttendance = Attendance::query()
        ->where('employee_id', $user->employee->id)
        ->whereDate('work_date', '2026-11-23')
        ->firstOrFail();

    $secondAttendance = Attendance::query()
        ->where('employee_id', $user->employee->id)
        ->whereDate('work_date', '2026-11-24')
        ->firstOrFail();

    $secondFlags = $secondAttendance->flags ?? [];
    $hasDuplicateFlag = in_array('duplicate_selfie_hash', $secondFlags, true)
        || array_key_exists('duplicate_selfie_hash', $secondFlags)
        || str_contains((string) json_encode($secondFlags), 'duplicate_selfie_hash');

    expect($firstAttendance->selfie_in_hash)->not->toBeNull()
        ->and($secondAttendance->selfie_in_hash)->toBe($firstAttendance->selfie_in_hash)
        ->and($hasDuplicateFlag)->toBeTrue();
});

test('selfie hasil watermark disimpan di disk privat dalam folder selfies/YYYY/MM sebagai jpeg berlebar maksimal 720px', function (): void {
    $now = CarbonImmutable::parse('2026-11-24 08:05:00', 'Asia/Jakarta');
    Carbon::setTestNow($now);
    CarbonImmutable::setTestNow($now);

    $user = User::query()->where('email', 'operator.prd@veloce.test')->firstOrFail();
    $office = OfficeLocation::query()->active()->firstOrFail();

    $this->actingAs($user)->postJson(route('attendance.check-in'), [
        'photo' => UploadedFile::fake()->image('selfie-wide.jpg', 1280, 960),
        'lat' => $office->lat,
        'lng' => $office->lng,
        'accuracy' => 12.0,
        'client_time' => $now->toIso8601String(),
    ])->assertCreated();

    $attendance = Attendance::query()
        ->where('employee_id', $user->employee->id)
        ->firstOrFail();

    $privateDisk = (string) config('attendance.private_disk', 'local');
    $path = $attendance->selfie_in_path;

    expect($path)->toBeString()
        ->and($path)->toStartWith('selfies/2026/11/')
        ->and(Storage::disk($privateDisk)->exists($path))->toBeTrue()
        ->and(Storage::disk('public')->allFiles())->toBeEmpty();

    $storedBytes = Storage::disk($privateDisk)->get($path);
    $imageInfo = getimagesizefromstring($storedBytes);

    expect($imageInfo)->not->toBeFalse()
        ->and($imageInfo[2])->toBe(IMAGETYPE_JPEG)
        ->and($imageInfo[0])->toBeGreaterThan(0)
        ->and($imageInfo[0])->toBeLessThanOrEqual(720);
});
