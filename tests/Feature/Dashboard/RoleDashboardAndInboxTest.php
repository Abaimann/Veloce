<?php

declare(strict_types=1);

use App\Actions\Attendance\CheckInAction;
use App\Actions\Leave\SubmitLeaveRequestAction;
use App\Enums\ApprovalStatus;
use App\Enums\AttendanceStatus;
use App\Enums\LeaveRequestStatus;
use App\Enums\RoleName;
use App\Models\Attendance;
use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\OfficeLocation;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
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

test('dashboard employee menampilkan nama, status hari ini, tombol absen masuk, dan ringkasan bulan ini', function (): void {
    $now = CarbonImmutable::parse('2026-11-24 08:30:00', 'Asia/Jakarta');
    Carbon::setTestNow($now);
    CarbonImmutable::setTestNow($now);

    $employeeUser = User::query()->where('email', 'operator.prd@veloce.test')->firstOrFail();
    $office = OfficeLocation::query()->active()->firstOrFail();

    Attendance::query()->create([
        'employee_id' => $employeeUser->employee->id,
        'work_date' => '2026-11-20',
        'check_in_at' => CarbonImmutable::parse('2026-11-20 08:25:00', 'Asia/Jakarta'),
        'check_out_at' => CarbonImmutable::parse('2026-11-20 17:05:00', 'Asia/Jakarta'),
        'office_location_id' => $office->id,
        'status' => AttendanceStatus::LATE,
        'flags' => [],
    ]);

    Attendance::query()->create([
        'employee_id' => $employeeUser->employee->id,
        'work_date' => '2026-11-23',
        'check_in_at' => CarbonImmutable::parse('2026-11-23 07:55:00', 'Asia/Jakarta'),
        'check_out_at' => CarbonImmutable::parse('2026-11-23 17:02:00', 'Asia/Jakarta'),
        'office_location_id' => $office->id,
        'status' => AttendanceStatus::PRESENT,
        'flags' => [],
    ]);

    $response = $this->actingAs($employeeUser)->get(route('dashboard'));
    $response->assertOk()
        ->assertSee($employeeUser->name)
        ->assertSee('Absen Masuk')
        ->assertSee(__('attendance.statuses.present'))
        ->assertSee(__('attendance.statuses.late'));

    $content = $response->getContent();
    $cameraPath = preg_quote(route('attendance.camera', [], false), '~');

    expect(mb_strtolower($content))->toContain('hari ini')
        ->and($content)->toMatch("~<a\\b[^>]*href=[\"'][^\"']*{$cameraPath}[\"'][^>]*>(?:(?!</a>).)*?Absen Masuk(?:(?!</a>).)*?</a>~s");

    Attendance::query()->create([
        'employee_id' => $employeeUser->employee->id,
        'work_date' => $now->toDateString(),
        'check_in_at' => $now,
        'office_location_id' => $office->id,
        'status' => AttendanceStatus::PRESENT,
        'flags' => [],
    ]);

    $this->actingAs($employeeUser)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(__('attendance.statuses.present'))
        ->assertSee('08:30');
});

test('dashboard payroll menampilkan ringkasan karyawan dan rekap absensi tanpa link check-in maupun form ubah absen', function (): void {
    $now = CarbonImmutable::parse('2026-11-24 08:30:00', 'Asia/Jakarta');
    Carbon::setTestNow($now);
    CarbonImmutable::setTestNow($now);

    $operator = User::query()->where('email', 'operator.prd@veloce.test')->firstOrFail();
    $teknisi = User::query()->where('email', 'teknisi.mnt@veloce.test')->firstOrFail();
    $office = OfficeLocation::query()->active()->firstOrFail();

    Attendance::query()->create([
        'employee_id' => $operator->employee->id,
        'work_date' => $now->toDateString(),
        'check_in_at' => CarbonImmutable::parse('2026-11-24 07:58:00', 'Asia/Jakarta'),
        'office_location_id' => $office->id,
        'status' => AttendanceStatus::PRESENT,
        'flags' => [],
    ]);

    Attendance::query()->create([
        'employee_id' => $teknisi->employee->id,
        'work_date' => $now->toDateString(),
        'check_in_at' => CarbonImmutable::parse('2026-11-24 08:25:00', 'Asia/Jakarta'),
        'office_location_id' => $office->id,
        'status' => AttendanceStatus::LATE,
        'flags' => [],
    ]);

    $payroll = User::role(RoleName::PAYROLL->value)->firstOrFail();
    $activeEmployeesCount = Employee::query()->where('is_active', true)->count();

    $response = $this->actingAs($payroll)->get(route('dashboard'));
    $response->assertOk()
        ->assertSee((string) $activeEmployeesCount)
        ->assertSee('Karyawan')
        ->assertSee(__('attendance.statuses.present'))
        ->assertSee(__('attendance.statuses.late'))
        ->assertDontSee('Absen Masuk')
        ->assertDontSee('Absen Pulang')
        ->assertDontSee(route('attendance.camera', [], false))
        ->assertDontSee(route('attendance.check-in', [], false))
        ->assertDontSee(route('attendance.check-out', [], false));

    $content = $response->getContent();
    preg_match_all('/<form\b[^>]*>/i', $content, $formMatches);

    foreach ($formMatches[0] as $formTag) {
        $isGetForm = preg_match('/\bmethod=["\']?GET["\']?/i', $formTag) === 1;
        $isLogoutForm = str_contains($formTag, route('logout'))
            || str_contains($formTag, route('logout', [], false));

        expect($isGetForm || $isLogoutForm)->toBeTrue("Ditemukan form non-logout pada dashboard payroll: {$formTag}");
    }
});

test('dashboard super admin menampilkan statistik organisasi dan tautan master data serta karyawan', function (): void {
    $superAdmin = User::role(RoleName::SUPER_ADMIN->value)->firstOrFail();

    $departmentCount = Department::query()->where('is_active', true)->count();
    $divisionCount = Division::query()->where('is_active', true)->count();
    $employeeCount = Employee::query()->where('is_active', true)->count();

    $this->actingAs($superAdmin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Departemen')
        ->assertSee('Divisi')
        ->assertSee('Karyawan')
        ->assertSee('Master Data')
        ->assertSee((string) $departmentCount)
        ->assertSee((string) $divisionCount)
        ->assertSee((string) $employeeCount)
        ->assertSee(route('management.master.index', [], false))
        ->assertSee(route('management.employees.index', [], false));
});

test('dashboard division head, dept manager, dan hr manager menampilkan kartu absensi pribadi dan pintasan persetujuan', function (string $role, ?string $email): void {
    $now = CarbonImmutable::parse('2026-11-24 08:30:00', 'Asia/Jakarta');
    Carbon::setTestNow($now);
    CarbonImmutable::setTestNow($now);

    $actor = $email !== null
        ? User::query()->where('email', $email)->firstOrFail()
        : User::role($role)->firstOrFail();

    $this->actingAs($actor)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee($actor->name)
        ->assertSee('Absen Masuk')
        ->assertSee(route('attendance.camera', [], false))
        ->assertSee('Persetujuan')
        ->assertSee(route('approvals.index', [], false));
})->with([
    'Ketua Divisi' => [RoleName::DIVISION_HEAD->value, 'head.prd@veloce.test'],
    'Manager Departemen' => [RoleName::DEPT_MANAGER->value, 'manager.eng@veloce.test'],
    'HR Manager' => [RoleName::HR_MANAGER->value, null],
]);

test('inbox persetujuan dan notifikasi menegakkan otorisasi, isolasi divisi, serta alur approve lalu reject berjenjang', function (): void {
    $now = CarbonImmutable::parse('2026-11-24 08:30:00', 'Asia/Jakarta');
    Carbon::setTestNow($now);
    CarbonImmutable::setTestNow($now);

    $applicant = User::query()->where('email', 'operator.prd@veloce.test')->firstOrFail();
    $payroll = User::role(RoleName::PAYROLL->value)->firstOrFail();
    $headPrd = User::query()->where('email', 'head.prd@veloce.test')->firstOrFail();
    $headMnt = User::query()->where('email', 'head.mnt@veloce.test')->firstOrFail();
    $managerEng = User::query()->where('email', 'manager.eng@veloce.test')->firstOrFail();

    expect(route('approvals.index', [], false))->toBe('/persetujuan')
        ->and(route('notifications.index', [], false))->toBe('/notifikasi');

    // Employee & Payroll ditolak 403 di GET /persetujuan.
    $this->actingAs($applicant)->get('/persetujuan')->assertForbidden();
    $this->actingAs($payroll)->get('/persetujuan')->assertForbidden();

    $annualLeave = LeaveType::query()->where('code', 'annual_leave')->firstOrFail();
    $reason = 'Keperluan keluarga mendadak selama dua hari kerja.';

    $leaveRequest = app(SubmitLeaveRequestAction::class)->execute(
        $applicant->employee,
        $annualLeave,
        CarbonImmutable::parse('2026-11-24', 'Asia/Jakarta'),
        CarbonImmutable::parse('2026-11-25', 'Asia/Jakarta'),
        $reason,
    );

    // head.prd@veloce.test melihat badge pending = 1 di navbar/layout, pengajuan di /persetujuan, dan notifikasi in-app.
    $headPrdDashboard = $this->actingAs($headPrd)->get(route('dashboard'));
    $headPrdDashboard->assertOk();

    expect($headPrdDashboard->getContent())
        ->toMatch('~<a\b[^>]*href=["\'][^"\']*\/persetujuan["\'][^>]*>(?:(?!</a>).)*?Persetujuan(?:(?!</a>).)*?<span[^>]*>\s*1\s*</span>(?:(?!</a>).)*?</a>~s');

    $this->actingAs($headPrd)
        ->get('/persetujuan')
        ->assertOk()
        ->assertSee($applicant->name)
        ->assertSee($annualLeave->name)
        ->assertSee($reason);

    $this->actingAs($headPrd)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertSee($applicant->name)
        ->assertSee($annualLeave->name);

    // head.mnt@veloce.test (divisi lain) tidak melihat pengajuan tersebut di inbox-nya.
    $this->actingAs($headMnt)
        ->get('/persetujuan')
        ->assertOk()
        ->assertDontSee($applicant->name)
        ->assertDontSee($reason);

    // head.prd@veloce.test menyetujui tahap 1.
    $this->actingAs($headPrd)
        ->post(route('approvals.approve', $leaveRequest), [
            'note' => 'Disetujui kepala divisi produksi.',
        ])
        ->assertRedirect();

    $leaveRequest->refresh();
    expect($leaveRequest->status)->toBe(LeaveRequestStatus::PENDING)
        ->and($leaveRequest->current_step)->toBe(2);

    $this->actingAs($headPrd)
        ->get('/persetujuan')
        ->assertOk()
        ->assertDontSee($reason);

    // manager.eng@veloce.test melihat pengajuan di inbox-nya dan menolak dengan catatan.
    $this->actingAs($managerEng)
        ->get('/persetujuan')
        ->assertOk()
        ->assertSee($applicant->name)
        ->assertSee($reason);

    $rejectNote = 'Jadwal produksi sedang padat, mohon jadwalkan ulang.';

    $this->actingAs($managerEng)
        ->post(route('approvals.reject', $leaveRequest), [
            'note' => $rejectNote,
        ])
        ->assertRedirect();

    $leaveRequest->refresh();
    $step2Approval = $leaveRequest->approvals()->where('step', 2)->firstOrFail();

    expect($leaveRequest->status)->toBe(LeaveRequestStatus::REJECTED)
        ->and($leaveRequest->finalized_at)->not->toBeNull()
        ->and($step2Approval->status)->toBe(ApprovalStatus::REJECTED)
        ->and($step2Approval->note)->toBe($rejectNote);

    // Pemohon melihat status berubah, melihat notifikasi baru di GET /notifikasi, dan menandai dibaca.
    $this->actingAs($applicant)
        ->get(route('leave.show', $leaveRequest))
        ->assertOk()
        ->assertSee(__('leave.statuses.rejected'))
        ->assertSee($rejectNote);

    $this->actingAs($applicant)
        ->get('/notifikasi')
        ->assertOk()
        ->assertSee($annualLeave->name)
        ->assertSee($rejectNote);

    expect($applicant->fresh()->unreadNotifications()->count())->toBeGreaterThan(0);

    $this->actingAs($applicant)
        ->post(route('notifications.read-all'))
        ->assertRedirect();

    expect($applicant->fresh()->unreadNotifications()->count())->toBe(0);
});

test('monitoring absensi tim menegakkan otorisasi, cakupan departemen, dan filter flagged_only untuk hr manager', function (): void {
    $now = CarbonImmutable::parse('2026-11-24 08:05:00', 'Asia/Jakarta');
    Carbon::setTestNow($now);
    CarbonImmutable::setTestNow($now);

    $engOperator = User::query()->where('email', 'operator.prd@veloce.test')->firstOrFail();
    $engTechnician = User::query()->where('email', 'teknisi.mnt@veloce.test')->firstOrFail();
    $qalInspector = User::query()->where('email', 'inspector.qc@veloce.test')->firstOrFail();
    $payroll = User::role(RoleName::PAYROLL->value)->firstOrFail();
    $hrManager = User::role(RoleName::HR_MANAGER->value)->firstOrFail();
    $engManager = User::query()->where('email', 'manager.eng@veloce.test')->firstOrFail();

    // employee & payroll ditolak 403.
    $this->actingAs($engOperator)->get(route('management.attendances.index'))->assertForbidden();
    $this->actingAs($payroll)->get(route('management.attendances.index'))->assertForbidden();

    $office = OfficeLocation::query()->active()->firstOrFail();
    $checkInAction = app(CheckInAction::class);

    // Record bersih di departemen ENG.
    $checkInAction->execute(
        $engOperator->employee,
        UploadedFile::fake()->image('selfie-clean-eng.jpg', 640, 480),
        $office->lat,
        $office->lng,
        12.0,
        $now,
    );

    // Record dengan flag low_gps_accuracy di departemen ENG.
    $flaggedAttendance = $checkInAction->execute(
        $engTechnician->employee,
        UploadedFile::fake()->image('selfie-low-gps.jpg', 642, 482),
        $office->lat,
        $office->lng,
        185.0,
        $now,
    );

    expect(json_encode($flaggedAttendance->flags))->toContain('low_gps_accuracy');

    // Record bersih di departemen QAL.
    $lateNow = CarbonImmutable::parse('2026-11-24 08:25:00', 'Asia/Jakarta');
    Carbon::setTestNow($lateNow);
    CarbonImmutable::setTestNow($lateNow);

    $checkInAction->execute(
        $qalInspector->employee,
        UploadedFile::fake()->image('selfie-clean-qal.jpg', 644, 484),
        $office->lat,
        $office->lng,
        14.0,
        $lateNow,
    );

    // HR Manager melihat absensi seluruh departemen.
    $this->actingAs($hrManager)
        ->get(route('management.attendances.index'))
        ->assertOk()
        ->assertSee($engOperator->name)
        ->assertSee($engTechnician->name)
        ->assertSee($qalInspector->name);

    // HR Manager memfilter ?flagged_only=1 dan hanya melihat record dengan flag mencurigakan.
    $flaggedResponse = $this->actingAs($hrManager)
        ->get(route('management.attendances.index', ['flagged_only' => 1]));

    $flaggedResponse->assertOk()
        ->assertSee($engTechnician->name)
        ->assertDontSee($engOperator->name)
        ->assertDontSee($qalInspector->name);

    $flaggedHtml = $flaggedResponse->getContent();
    expect(
        str_contains($flaggedHtml, 'Akurasi GPS rendah')
        || str_contains($flaggedHtml, 'low_gps_accuracy')
    )->toBeTrue();

    // Dept Manager ENG hanya melihat absensi departemen ENG, tidak melihat QAL.
    $this->actingAs($engManager)
        ->get(route('management.attendances.index'))
        ->assertOk()
        ->assertSee($engOperator->name)
        ->assertSee($engTechnician->name)
        ->assertDontSee($qalInspector->name);
});

test('karyawan dapat membuka halaman profil dan memperbarui kata sandi dengan current_password yang valid', function (): void {
    $employeeUser = User::query()->where('email', 'operator.prd@veloce.test')->firstOrFail();
    $employeeUser->update(['password' => 'SandiLama123']);

    expect(route('profile.show', [], false))->toBe('/profil');

    $this->actingAs($employeeUser)
        ->get('/profil')
        ->assertOk()
        ->assertSee($employeeUser->name)
        ->assertSee($employeeUser->email)
        ->assertSee($employeeUser->employee->employee_number);

    $passwordRoute = app('router')->getRoutes()->getByName('profile.password');
    expect($passwordRoute)->not->toBeNull();

    $methods = $passwordRoute->methods();
    $httpMethod = in_array('PUT', $methods, true)
        ? 'put'
        : (in_array('PATCH', $methods, true) ? 'patch' : 'post');

    // Kata sandi saat ini salah ditolak.
    $this->actingAs($employeeUser)
        ->{$httpMethod}(route('profile.password'), [
            'current_password' => 'SandiSalah123',
            'password' => 'SandiBaru123',
            'password_confirmation' => 'SandiBaru123',
        ])
        ->assertSessionHasErrors('current_password');

    expect(Hash::check('SandiLama123', (string) $employeeUser->fresh()->password))->toBeTrue();

    // Kata sandi saat ini benar berhasil memperbarui password.
    $this->actingAs($employeeUser)
        ->{$httpMethod}(route('profile.password'), [
            'current_password' => 'SandiLama123',
            'password' => 'SandiBaru123',
            'password_confirmation' => 'SandiBaru123',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(Hash::check('SandiBaru123', (string) $employeeUser->fresh()->password))->toBeTrue();
});

test('aset pwa tersedia dengan manifest json valid, service worker network-only untuk absensi, dan ikon png 192 serta 512', function (): void {
    $manifestPath = public_path('manifest.webmanifest');
    $swPath = public_path('sw.js');
    $icon192Path = public_path('icons/icon-192.png');
    $icon512Path = public_path('icons/icon-512.png');

    expect(File::exists($manifestPath))->toBeTrue()
        ->and(File::exists($swPath))->toBeTrue()
        ->and(File::exists($icon192Path))->toBeTrue()
        ->and(File::exists($icon512Path))->toBeTrue();

    $manifest = json_decode(File::get($manifestPath), true, 512, JSON_THROW_ON_ERROR);

    expect($manifest)->toBeArray()
        ->and($manifest['name'] ?? null)->toBe('Veloce')
        ->and($manifest['short_name'] ?? null)->toBe('Veloce')
        ->and($manifest['description'] ?? null)->toBe('Sistem Absensi & Manajemen Kinerja Veloce')
        ->and($manifest['start_url'] ?? null)->toBe('/dashboard')
        ->and($manifest['scope'] ?? null)->toBe('/')
        ->and($manifest['display'] ?? null)->toBe('standalone')
        ->and($manifest['orientation'] ?? null)->toBe('portrait-primary')
        ->and($manifest['background_color'] ?? null)->toBe('#F5F7FA')
        ->and($manifest['theme_color'] ?? null)->toBe('#22346D')
        ->and($manifest['lang'] ?? null)->toBe('id')
        ->and($manifest['icons'] ?? null)->toHaveCount(2);

    $iconsBySize = collect($manifest['icons'])->keyBy('sizes');

    expect($iconsBySize->get('192x192'))->toMatchArray([
        'src' => '/icons/icon-192.png',
        'sizes' => '192x192',
        'type' => 'image/png',
        'purpose' => 'any maskable',
    ])->and($iconsBySize->get('512x512'))->toMatchArray([
        'src' => '/icons/icon-512.png',
        'sizes' => '512x512',
        'type' => 'image/png',
        'purpose' => 'any maskable',
    ]);

    $swContent = File::get($swPath);

    expect($swContent)
        ->toContain('skipWaiting')
        ->toContain('clients.claim')
        ->toContain('/build/')
        ->toContain('/icons/')
        ->toContain('/brand/')
        ->toContain('/absen')
        ->toContain('/izin')
        ->toContain('/persetujuan')
        ->toContain('/dashboard');

    $info192 = getimagesize($icon192Path);
    $info512 = getimagesize($icon512Path);

    expect($info192)->not->toBeFalse()
        ->and($info192[0])->toBe(192)
        ->and($info192[1])->toBe(192)
        ->and($info192[2])->toBe(IMAGETYPE_PNG)
        ->and($info512)->not->toBeFalse()
        ->and($info512[0])->toBe(512)
        ->and($info512[1])->toBe(512)
        ->and($info512[2])->toBe(IMAGETYPE_PNG);

    $employeeUser = User::query()->where('email', 'operator.prd@veloce.test')->firstOrFail();

    $this->actingAs($employeeUser)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('manifest.webmanifest', false)
        ->assertSee('icons/icon-192.png', false);
});
