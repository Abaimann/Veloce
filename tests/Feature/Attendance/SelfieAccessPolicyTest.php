<?php

declare(strict_types=1);

use App\Enums\AttendanceStatus;
use App\Enums\RoleName;
use App\Models\Attendance;
use App\Models\OfficeLocation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake((string) config('attendance.private_disk', 'local'));
    $this->seed();
});

test('policy akses media selfie menegakkan batas otorisasi seluruh role dengan tepat', function (): void {
    $privateDisk = (string) config('attendance.private_disk', 'local');
    $selfiePath = 'selfies/2026/11/policy-check-in.jpg';

    $fakeImage = UploadedFile::fake()->image('policy-check-in.jpg', 480, 360);
    Storage::disk($privateDisk)->put($selfiePath, (string) file_get_contents($fakeImage->getRealPath()));

    $ownerUser = User::query()->where('email', 'operator.prd@veloce.test')->firstOrFail();
    $office = OfficeLocation::query()->active()->firstOrFail();
    $now = CarbonImmutable::parse('2026-11-24 08:05:00', 'Asia/Jakarta');

    $attendance = Attendance::query()->create([
        'employee_id' => $ownerUser->employee->id,
        'work_date' => '2026-11-24',
        'check_in_at' => $now,
        'check_in_lat' => $office->lat,
        'check_in_lng' => $office->lng,
        'check_in_accuracy' => 12.0,
        'check_in_distance_meters' => 10.0,
        'office_location_id' => $office->id,
        'selfie_in_path' => $selfiePath,
        'selfie_in_hash' => hash('sha256', 'policy-test'),
        'client_check_in_at' => $now,
        'status' => AttendanceStatus::PRESENT,
        'flags' => [],
    ]);

    $url = route('media.attendance.selfie', [$attendance, 'in']);

    // Tamu diarahkan ke halaman login.
    $this->get($url)->assertRedirect(route('login'));

    // Pemilik, Ketua Divisi sedivisi, Manager Departemen sedepartemen, dan HR Manager diizinkan (200).
    $sameDivisionHead = User::query()->where('email', 'head.prd@veloce.test')->firstOrFail();
    $sameDeptManager = User::query()->where('email', 'manager.eng@veloce.test')->firstOrFail();
    $hrManager = User::role(RoleName::HR_MANAGER->value)->firstOrFail();

    foreach ([$ownerUser, $sameDivisionHead, $sameDeptManager, $hrManager] as $allowedViewer) {
        $response = $this->actingAs($allowedViewer)->get($url);
        $response->assertOk();

        $cacheControl = strtolower((string) $response->headers->get('Cache-Control'));
        expect($cacheControl)
            ->toContain('private')
            ->toContain('no-store');
    }

    // Karyawan lain, Ketua Divisi lain, Manager Departemen lain, Payroll, dan Super Admin ditolak (403).
    $otherEmployee = User::query()->where('email', 'teknisi.mnt@veloce.test')->firstOrFail();
    $otherDivisionHead = User::query()->where('email', 'head.mnt@veloce.test')->firstOrFail();
    $otherDeptManager = User::query()->where('email', 'manager.qal@veloce.test')->firstOrFail();
    $payroll = User::role(RoleName::PAYROLL->value)->firstOrFail();
    $superAdmin = User::role(RoleName::SUPER_ADMIN->value)->firstOrFail();

    foreach ([$otherEmployee, $otherDivisionHead, $otherDeptManager, $payroll, $superAdmin] as $deniedViewer) {
        $this->actingAs($deniedViewer)->get($url)->assertForbidden();
    }

    // Parameter phase tidak valid menghasilkan 404.
    $this->actingAs($ownerUser)
        ->get("/media/absensi/{$attendance->id}/selfie/invalid")
        ->assertNotFound();
});
