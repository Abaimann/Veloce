<?php

declare(strict_types=1);

use App\Actions\Leave\ApproveLeaveRequestAction;
use App\Actions\Leave\RejectLeaveRequestAction;
use App\Actions\Leave\SubmitLeaveRequestAction;
use App\Enums\ApprovalStatus;
use App\Enums\LeaveRequestStatus;
use App\Enums\RoleName;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake((string) config('attendance.private_disk', 'local'));
    $this->seed();
});

afterEach(function (): void {
    Carbon::setTestNow();
    CarbonImmutable::setTestNow();
});

test('matriks persetujuan 1: karyawan berdivisi untuk cuti 2 hari mengikuti alur division_head lalu dept_manager', function (): void {
    $staff = User::query()->where('email', 'operator.prd@veloce.test')->firstOrFail();
    $divisionHead = User::query()->where('email', 'head.prd@veloce.test')->firstOrFail();
    $deptManager = User::query()->where('email', 'manager.eng@veloce.test')->firstOrFail();
    $hrManager = User::role(RoleName::HR_MANAGER->value)->firstOrFail();
    $otherDivHead = User::query()->where('email', 'head.mnt@veloce.test')->firstOrFail();

    $annualLeave = LeaveType::query()->where('code', 'annual_leave')->firstOrFail();

    $response = $this->actingAs($staff)->post(route('leave.store'), [
        'leave_type_id' => $annualLeave->id,
        'start_date' => '2026-11-24',
        'end_date' => '2026-11-25',
        'reason' => 'Keperluan keluarga selama dua hari kerja.',
    ]);

    $leaveRequest = LeaveRequest::query()
        ->where('employee_id', $staff->employee->id)
        ->with('approvals')
        ->firstOrFail();

    $response->assertRedirect(route('leave.show', $leaveRequest));

    expect($leaveRequest->total_work_days)->toBe(2)
        ->and($leaveRequest->status)->toBe(LeaveRequestStatus::PENDING)
        ->and($leaveRequest->current_step)->toBe(1)
        ->and($leaveRequest->approvals->pluck('role')->all())->toBe([
            RoleName::DIVISION_HEAD->value,
            RoleName::DEPT_MANAGER->value,
        ]);

    // Tahap 1 hanya boleh disetujui Ketua Divisi sedivisi.
    expect($divisionHead->can('approve', $leaveRequest))->toBeTrue()
        ->and($otherDivHead->can('approve', $leaveRequest))->toBeFalse()
        ->and($deptManager->can('approve', $leaveRequest))->toBeFalse()
        ->and($hrManager->can('approve', $leaveRequest))->toBeFalse();

    $approveAction = app(ApproveLeaveRequestAction::class);

    $afterStep1 = $approveAction->execute($divisionHead, $leaveRequest, 'Disetujui kepala divisi');
    expect($afterStep1->status)->toBe(LeaveRequestStatus::PENDING)
        ->and($afterStep1->current_step)->toBe(2)
        ->and($deptManager->can('approve', $afterStep1))->toBeTrue();

    $final = $approveAction->execute($deptManager, $afterStep1, 'Disetujui manager departemen');
    expect($final->status)->toBe(LeaveRequestStatus::APPROVED)
        ->and($final->finalized_at)->not->toBeNull()
        ->and($final->approvals->pluck('status')->all())->toBe([
            ApprovalStatus::APPROVED,
            ApprovalStatus::APPROVED,
        ]);
});

test('matriks persetujuan 2: karyawan berdivisi untuk cuti 4 hari menambahkan hr_manager di tahap akhir', function (): void {
    $staff = User::query()->where('email', 'operator.prd@veloce.test')->firstOrFail();
    $divisionHead = User::query()->where('email', 'head.prd@veloce.test')->firstOrFail();
    $deptManager = User::query()->where('email', 'manager.eng@veloce.test')->firstOrFail();
    $hrManager = User::role(RoleName::HR_MANAGER->value)->firstOrFail();

    $annualLeave = LeaveType::query()->where('code', 'annual_leave')->firstOrFail();

    $leaveRequest = app(SubmitLeaveRequestAction::class)->execute(
        $staff->employee,
        $annualLeave,
        CarbonImmutable::parse('2026-12-01', 'Asia/Jakarta'),
        CarbonImmutable::parse('2026-12-04', 'Asia/Jakarta'),
        'Cuti tahunan empat hari berturut-turut.',
    );

    expect($leaveRequest->total_work_days)->toBe(4)
        ->and($leaveRequest->approvals->pluck('role')->all())->toBe([
            RoleName::DIVISION_HEAD->value,
            RoleName::DEPT_MANAGER->value,
            RoleName::HR_MANAGER->value,
        ]);

    $approveAction = app(ApproveLeaveRequestAction::class);

    $step1 = $approveAction->execute($divisionHead, $leaveRequest);
    expect($step1->status)->toBe(LeaveRequestStatus::PENDING)
        ->and($step1->current_step)->toBe(2);

    $step2 = $approveAction->execute($deptManager, $step1);
    expect($step2->status)->toBe(LeaveRequestStatus::PENDING)
        ->and($step2->current_step)->toBe(3)
        ->and($hrManager->can('approve', $step2))->toBeTrue();

    $step3 = $approveAction->execute($hrManager, $step2);
    expect($step3->status)->toBe(LeaveRequestStatus::APPROVED)
        ->and($step3->finalized_at)->not->toBeNull();
});

test('matriks persetujuan 3: karyawan tanpa divisi melewati tahap division_head langsung ke dept_manager', function (): void {
    $noDivisionStaff = User::query()->where('email', 'admin.eng@veloce.test')->firstOrFail();
    $deptManager = User::query()->where('email', 'manager.eng@veloce.test')->firstOrFail();
    $sickLeave = LeaveType::query()->where('code', 'sick')->firstOrFail();

    expect($noDivisionStaff->employee->division_id)->toBeNull();

    $leaveRequest = app(SubmitLeaveRequestAction::class)->execute(
        $noDivisionStaff->employee,
        $sickLeave,
        CarbonImmutable::parse('2026-11-24', 'Asia/Jakarta'),
        CarbonImmutable::parse('2026-11-24', 'Asia/Jakarta'),
        'Demam dan perlu istirahat sesuai surat dokter.',
        UploadedFile::fake()->image('surat-dokter.jpg', 600, 800),
    );

    expect($leaveRequest->total_work_days)->toBe(1)
        ->and($leaveRequest->approvals->pluck('role')->all())->toBe([
            RoleName::DEPT_MANAGER->value,
        ])
        ->and($deptManager->can('approve', $leaveRequest))->toBeTrue();

    $final = app(ApproveLeaveRequestAction::class)->execute($deptManager, $leaveRequest);
    expect($final->status)->toBe(LeaveRequestStatus::APPROVED);
});

test('matriks persetujuan 4: division_head 2 hari hanya ke dept_manager sedangkan 4 hari ke dept_manager lalu hr_manager', function (): void {
    $divisionHead = User::query()->where('email', 'head.prd@veloce.test')->firstOrFail();
    $deptManager = User::query()->where('email', 'manager.eng@veloce.test')->firstOrFail();
    $hrManager = User::role(RoleName::HR_MANAGER->value)->firstOrFail();
    $annualLeave = LeaveType::query()->where('code', 'annual_leave')->firstOrFail();

    $submitAction = app(SubmitLeaveRequestAction::class);
    $approveAction = app(ApproveLeaveRequestAction::class);

    $shortLeave = $submitAction->execute(
        $divisionHead->employee,
        $annualLeave,
        CarbonImmutable::parse('2026-11-24', 'Asia/Jakarta'),
        CarbonImmutable::parse('2026-11-25', 'Asia/Jakarta'),
        'Cuti kepala divisi dua hari.',
    );

    expect($shortLeave->approvals->pluck('role')->all())->toBe([
        RoleName::DEPT_MANAGER->value,
    ]);

    $approvedShort = $approveAction->execute($deptManager, $shortLeave);
    expect($approvedShort->status)->toBe(LeaveRequestStatus::APPROVED);

    $longLeave = $submitAction->execute(
        $divisionHead->employee,
        $annualLeave,
        CarbonImmutable::parse('2026-12-01', 'Asia/Jakarta'),
        CarbonImmutable::parse('2026-12-04', 'Asia/Jakarta'),
        'Cuti kepala divisi empat hari.',
    );

    expect($longLeave->approvals->pluck('role')->all())->toBe([
        RoleName::DEPT_MANAGER->value,
        RoleName::HR_MANAGER->value,
    ]);

    $step1 = $approveAction->execute($deptManager, $longLeave);
    expect($step1->status)->toBe(LeaveRequestStatus::PENDING);

    $step2 = $approveAction->execute($hrManager, $step1);
    expect($step2->status)->toBe(LeaveRequestStatus::APPROVED);
});

test('matriks persetujuan 5: dept_manager hanya memerlukan persetujuan hr_manager', function (): void {
    $deptManager = User::query()->where('email', 'manager.eng@veloce.test')->firstOrFail();
    $hrManager = User::role(RoleName::HR_MANAGER->value)->firstOrFail();
    $annualLeave = LeaveType::query()->where('code', 'annual_leave')->firstOrFail();

    $leaveRequest = app(SubmitLeaveRequestAction::class)->execute(
        $deptManager->employee,
        $annualLeave,
        CarbonImmutable::parse('2026-12-01', 'Asia/Jakarta'),
        CarbonImmutable::parse('2026-12-04', 'Asia/Jakarta'),
        'Cuti manager departemen empat hari.',
    );

    expect($leaveRequest->approvals->pluck('role')->all())->toBe([
        RoleName::HR_MANAGER->value,
    ]);

    $approved = app(ApproveLeaveRequestAction::class)->execute($hrManager, $leaveRequest);
    expect($approved->status)->toBe(LeaveRequestStatus::APPROVED);
});

test('matriks persetujuan 6: hr_manager hanya memerlukan persetujuan super_admin', function (): void {
    $hrManager = User::role(RoleName::HR_MANAGER->value)->firstOrFail();
    $superAdmin = User::role(RoleName::SUPER_ADMIN->value)->firstOrFail();
    $annualLeave = LeaveType::query()->where('code', 'annual_leave')->firstOrFail();

    $leaveRequest = app(SubmitLeaveRequestAction::class)->execute(
        $hrManager->employee,
        $annualLeave,
        CarbonImmutable::parse('2026-11-24', 'Asia/Jakarta'),
        CarbonImmutable::parse('2026-11-25', 'Asia/Jakarta'),
        'Cuti HR Manager dua hari.',
    );

    expect($leaveRequest->approvals->pluck('role')->all())->toBe([
        RoleName::SUPER_ADMIN->value,
    ])
        ->and($hrManager->can('approve', $leaveRequest))->toBeFalse()
        ->and($superAdmin->can('approve', $leaveRequest))->toBeTrue();

    $approved = app(ApproveLeaveRequestAction::class)->execute($superAdmin, $leaveRequest);
    expect($approved->status)->toBe(LeaveRequestStatus::APPROVED);
});

test('self-approval ditolak dengan AuthorizationException 403 untuk semua pemohon approver', function (): void {
    $divisionHead = User::query()->where('email', 'head.prd@veloce.test')->firstOrFail();
    $deptManager = User::query()->where('email', 'manager.eng@veloce.test')->firstOrFail();
    $hrManager = User::role(RoleName::HR_MANAGER->value)->firstOrFail();
    $annualLeave = LeaveType::query()->where('code', 'annual_leave')->firstOrFail();

    $submitAction = app(SubmitLeaveRequestAction::class);
    $approveAction = app(ApproveLeaveRequestAction::class);
    $rejectAction = app(RejectLeaveRequestAction::class);

    foreach ([$divisionHead, $deptManager, $hrManager] as $actor) {
        $request = $submitAction->execute(
            $actor->employee,
            $annualLeave,
            CarbonImmutable::parse('2026-11-24', 'Asia/Jakarta'),
            CarbonImmutable::parse('2026-11-25', 'Asia/Jakarta'),
            'Pengajuan pribadi oleh approver.',
        );

        expect($actor->can('approve', $request))->toBeFalse();

        expect(fn () => $approveAction->execute($actor, $request))
            ->toThrow(AuthorizationException::class);

        expect(fn () => $rejectAction->execute($actor, $request, 'Coba tolak sendiri'))
            ->toThrow(AuthorizationException::class);
    }
});

test('penolakan pada langkah 1 langsung menjadikan status rejected dan menghentikan pipeline', function (): void {
    $staff = User::query()->where('email', 'operator.prd@veloce.test')->firstOrFail();
    $divisionHead = User::query()->where('email', 'head.prd@veloce.test')->firstOrFail();
    $deptManager = User::query()->where('email', 'manager.eng@veloce.test')->firstOrFail();
    $annualLeave = LeaveType::query()->where('code', 'annual_leave')->firstOrFail();

    $leaveRequest = app(SubmitLeaveRequestAction::class)->execute(
        $staff->employee,
        $annualLeave,
        CarbonImmutable::parse('2026-12-01', 'Asia/Jakarta'),
        CarbonImmutable::parse('2026-12-04', 'Asia/Jakarta'),
        'Cuti empat hari yang akan ditolak di tahap pertama.',
    );

    $rejected = app(RejectLeaveRequestAction::class)->execute(
        $divisionHead,
        $leaveRequest,
        'Sedang jadwal produksi prioritas tinggi.',
    );

    $approvals = $rejected->approvals()->orderBy('step')->get();

    expect($rejected->status)->toBe(LeaveRequestStatus::REJECTED)
        ->and($rejected->finalized_at)->not->toBeNull()
        ->and($approvals[0]->status)->toBe(ApprovalStatus::REJECTED)
        ->and($approvals[0]->approver_id)->toBe($divisionHead->id)
        ->and($approvals[0]->note)->toBe('Sedang jadwal produksi prioritas tinggi.')
        ->and($approvals[1]->status)->toBe(ApprovalStatus::PENDING)
        ->and($approvals[1]->approver_id)->toBeNull()
        ->and($approvals[2]->status)->toBe(ApprovalStatus::PENDING)
        ->and($approvals[2]->approver_id)->toBeNull()
        ->and($deptManager->can('approve', $rejected))->toBeFalse();

    $balance = LeaveBalance::query()
        ->where('employee_id', $staff->employee->id)
        ->where('year', 2026)
        ->firstOrFail();

    expect($balance->used_days)->toBe(0);
});

test('pembatalan hanya boleh dilakukan oleh pemohon saat status masih pending', function (): void {
    $owner = User::query()->where('email', 'operator.prd@veloce.test')->firstOrFail();
    $otherEmployee = User::query()->where('email', 'teknisi.mnt@veloce.test')->firstOrFail();
    $divisionHead = User::query()->where('email', 'head.prd@veloce.test')->firstOrFail();
    $deptManager = User::query()->where('email', 'manager.eng@veloce.test')->firstOrFail();
    $annualLeave = LeaveType::query()->where('code', 'annual_leave')->firstOrFail();

    $submitAction = app(SubmitLeaveRequestAction::class);

    $pendingRequest = $submitAction->execute(
        $owner->employee,
        $annualLeave,
        CarbonImmutable::parse('2026-11-24', 'Asia/Jakarta'),
        CarbonImmutable::parse('2026-11-25', 'Asia/Jakarta'),
        'Pengajuan yang akan dibatalkan.',
    );

    // Orang lain maupun atasan tidak berhak membatalkan pengajuan milik pemohon.
    expect($otherEmployee->can('cancel', $pendingRequest))->toBeFalse()
        ->and($divisionHead->can('cancel', $pendingRequest))->toBeFalse();

    $this->actingAs($otherEmployee)
        ->post(route('leave.cancel', $pendingRequest))
        ->assertForbidden();

    $this->actingAs($divisionHead)
        ->post(route('leave.cancel', $pendingRequest))
        ->assertForbidden();

    // Pemohon boleh membatalkan saat pending.
    expect($owner->can('cancel', $pendingRequest))->toBeTrue();

    $this->actingAs($owner)
        ->post(route('leave.cancel', $pendingRequest))
        ->assertRedirect(route('leave.show', $pendingRequest));

    $pendingRequest->refresh();
    expect($pendingRequest->status)->toBe(LeaveRequestStatus::CANCELLED)
        ->and($pendingRequest->cancelled_at)->not->toBeNull()
        ->and($owner->can('cancel', $pendingRequest))->toBeFalse();

    // Pembatalan ulang ditolak.
    $this->actingAs($owner)
        ->post(route('leave.cancel', $pendingRequest))
        ->assertForbidden();

    // Pengajuan yang sudah disetujui juga tidak dapat dibatalkan.
    $approvedRequest = $submitAction->execute(
        $owner->employee,
        $annualLeave,
        CarbonImmutable::parse('2026-12-01', 'Asia/Jakarta'),
        CarbonImmutable::parse('2026-12-02', 'Asia/Jakarta'),
        'Pengajuan yang akan disetujui.',
    );
    $approveAction = app(ApproveLeaveRequestAction::class);
    $step1 = $approveAction->execute($divisionHead, $approvedRequest);
    $approved = $approveAction->execute($deptManager, $step1);

    expect($owner->can('cancel', $approved))->toBeFalse();
    $this->actingAs($owner)
        ->post(route('leave.cancel', $approved))
        ->assertForbidden();
});

test('akses halaman leave dan lampiran privat dijaga ketat oleh policy', function (): void {
    $owner = User::query()->where('email', 'operator.prd@veloce.test')->firstOrFail();
    $sameDivisionHead = User::query()->where('email', 'head.prd@veloce.test')->firstOrFail();
    $sameDeptManager = User::query()->where('email', 'manager.eng@veloce.test')->firstOrFail();
    $hrManager = User::role(RoleName::HR_MANAGER->value)->firstOrFail();

    $otherEmployee = User::query()->where('email', 'teknisi.mnt@veloce.test')->firstOrFail();
    $otherDivisionHead = User::query()->where('email', 'head.mnt@veloce.test')->firstOrFail();
    $otherDeptManager = User::query()->where('email', 'manager.qal@veloce.test')->firstOrFail();
    $payroll = User::role(RoleName::PAYROLL->value)->firstOrFail();
    $superAdmin = User::role(RoleName::SUPER_ADMIN->value)->firstOrFail();

    $sickLeave = LeaveType::query()->where('code', 'sick')->firstOrFail();

    $this->actingAs($owner)->get(route('leave.index'))->assertOk();
    $this->actingAs($owner)->get(route('leave.create'))->assertOk()->assertSee('Ketua Divisi');

    // Payroll & akun non-karyawan tidak bisa membuka daftar / form pengajuan cuti karyawan.
    $this->actingAs($payroll)->get(route('leave.index'))->assertForbidden();
    $this->actingAs($payroll)->get(route('leave.create'))->assertForbidden();
    $this->actingAs($superAdmin)->get(route('leave.create'))->assertForbidden();

    $this->actingAs($owner)->post(route('leave.store'), [
        'leave_type_id' => $sickLeave->id,
        'start_date' => '2026-11-24',
        'end_date' => '2026-11-24',
        'reason' => 'Sakit flu berat dengan surat keterangan dokter.',
        'attachment' => UploadedFile::fake()->image('surat-sakit.jpg', 700, 900),
    ])->assertRedirect();

    $leaveRequest = LeaveRequest::query()
        ->where('employee_id', $owner->employee->id)
        ->firstOrFail();

    expect($leaveRequest->attachment_path)->not->toBeNull();

    $attachmentUrl = route('media.leave.attachment', $leaveRequest);
    $showUrl = route('leave.show', $leaveRequest);

    // Tamu diarahkan ke login.
    auth()->logout();
    $this->get($attachmentUrl)->assertRedirect(route('login'));
    $this->get($showUrl)->assertRedirect(route('login'));

    // Pemilik, Ketua Divisi sedivisi, Manager Departemen sedepartemen, dan HR Manager diizinkan.
    foreach ([$owner, $sameDivisionHead, $sameDeptManager, $hrManager] as $allowedViewer) {
        $this->actingAs($allowedViewer)->get($showUrl)->assertOk();

        $mediaResponse = $this->actingAs($allowedViewer)->get($attachmentUrl);
        $mediaResponse->assertOk();

        $cacheControl = strtolower((string) $mediaResponse->headers->get('Cache-Control'));
        expect($cacheControl)
            ->toContain('private')
            ->toContain('no-store');
    }

    // Karyawan lain, divisi lain, departemen lain, dan payroll ditolak 403.
    foreach ([$otherEmployee, $otherDivisionHead, $otherDeptManager, $payroll] as $deniedViewer) {
        $this->actingAs($deniedViewer)->get($showUrl)->assertForbidden();
        $this->actingAs($deniedViewer)->get($attachmentUrl)->assertForbidden();
    }
});
