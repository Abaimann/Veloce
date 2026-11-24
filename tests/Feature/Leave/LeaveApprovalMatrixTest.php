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

test('matriks persetujuan absensi: semua pengajuan karyawan, division head, dan dept manager hanya disetujui oleh hr_manager', function (): void {
    $staff = User::query()->where('email', 'operator.prd@veloce.test')->firstOrFail();
    $divisionHead = User::query()->where('email', 'head.prd@veloce.test')->firstOrFail();
    $deptManager = User::query()->where('email', 'manager.eng@veloce.test')->firstOrFail();
    $hrManager = User::role(RoleName::HR_MANAGER->value)->firstOrFail();
    $superAdmin = User::role(RoleName::SUPER_ADMIN->value)->firstOrFail();

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
            RoleName::HR_MANAGER->value,
        ]);

    // Manager selain HR (division_head & dept_manager) serta super_admin (untuk pemohon non-HR) tidak bisa menyetujui absensi/cuti.
    expect($hrManager->can('approve', $leaveRequest))->toBeTrue()
        ->and($divisionHead->can('approve', $leaveRequest))->toBeFalse()
        ->and($deptManager->can('approve', $leaveRequest))->toBeFalse()
        ->and($superAdmin->can('approve', $leaveRequest))->toBeFalse();

    $approveAction = app(ApproveLeaveRequestAction::class);

    expect(fn () => $approveAction->execute($divisionHead, $leaveRequest))
        ->toThrow(AuthorizationException::class);
    expect(fn () => $approveAction->execute($deptManager, $leaveRequest))
        ->toThrow(AuthorizationException::class);

    $final = $approveAction->execute($hrManager, $leaveRequest, 'Disetujui oleh HR Manager.');
    expect($final->status)->toBe(LeaveRequestStatus::APPROVED)
        ->and($final->finalized_at)->not->toBeNull()
        ->and($final->approvals->pluck('status')->all())->toBe([
            ApprovalStatus::APPROVED,
        ]);
});

test('matriks persetujuan absensi: division_head, dept_manager, payroll, dan super_admin diproses oleh hr_manager', function (string $applicantEmail): void {
    $applicant = User::query()->where('email', $applicantEmail)->firstOrFail();
    $hrManager = User::role(RoleName::HR_MANAGER->value)->firstOrFail();
    $annualLeave = LeaveType::query()->where('code', 'annual_leave')->firstOrFail();

    $leaveRequest = app(SubmitLeaveRequestAction::class)->execute(
        $applicant->employee,
        $annualLeave,
        CarbonImmutable::parse('2026-12-01', 'Asia/Jakarta'),
        CarbonImmutable::parse('2026-12-04', 'Asia/Jakarta'),
        'Pengajuan cuti empat hari.',
    );

    expect($leaveRequest->approvals->pluck('role')->all())->toBe([
        RoleName::HR_MANAGER->value,
    ])
        ->and($hrManager->can('approve', $leaveRequest))->toBeTrue();

    $approved = app(ApproveLeaveRequestAction::class)->execute($hrManager, $leaveRequest);
    expect($approved->status)->toBe(LeaveRequestStatus::APPROVED);
})->with([
    'Division Head' => 'head.prd@veloce.test',
    'Dept Manager' => 'manager.eng@veloce.test',
    'Payroll' => 'payroll@veloce.test',
    'Super Admin' => 'admin@veloce.test',
]);

test('matriks persetujuan absensi: hr_manager hanya memerlukan persetujuan super_admin', function (): void {
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

test('self-approval ditolak dengan AuthorizationException 403 untuk hr_manager maupun super_admin', function (): void {
    $hrManager = User::role(RoleName::HR_MANAGER->value)->firstOrFail();
    $annualLeave = LeaveType::query()->where('code', 'annual_leave')->firstOrFail();

    $submitAction = app(SubmitLeaveRequestAction::class);
    $approveAction = app(ApproveLeaveRequestAction::class);
    $rejectAction = app(RejectLeaveRequestAction::class);

    $request = $submitAction->execute(
        $hrManager->employee,
        $annualLeave,
        CarbonImmutable::parse('2026-11-24', 'Asia/Jakarta'),
        CarbonImmutable::parse('2026-11-25', 'Asia/Jakarta'),
        'Pengajuan pribadi HR Manager.',
    );

    expect($hrManager->can('approve', $request))->toBeFalse();

    expect(fn () => $approveAction->execute($hrManager, $request))
        ->toThrow(AuthorizationException::class);

    expect(fn () => $rejectAction->execute($hrManager, $request, 'Coba tolak sendiri'))
        ->toThrow(AuthorizationException::class);
});

test('penolakan oleh hr_manager langsung menjadikan status rejected dan mengembalikan saldo', function (): void {
    $staff = User::query()->where('email', 'operator.prd@veloce.test')->firstOrFail();
    $hrManager = User::role(RoleName::HR_MANAGER->value)->firstOrFail();
    $annualLeave = LeaveType::query()->where('code', 'annual_leave')->firstOrFail();

    $leaveRequest = app(SubmitLeaveRequestAction::class)->execute(
        $staff->employee,
        $annualLeave,
        CarbonImmutable::parse('2026-12-01', 'Asia/Jakarta'),
        CarbonImmutable::parse('2026-12-04', 'Asia/Jakarta'),
        'Cuti empat hari yang ditolak HR.',
    );

    $rejected = app(RejectLeaveRequestAction::class)->execute(
        $hrManager,
        $leaveRequest,
        'Sedang jadwal produksi prioritas tinggi.',
    );

    $approvals = $rejected->approvals()->orderBy('step')->get();

    expect($rejected->status)->toBe(LeaveRequestStatus::REJECTED)
        ->and($rejected->finalized_at)->not->toBeNull()
        ->and($approvals[0]->status)->toBe(ApprovalStatus::REJECTED)
        ->and($approvals[0]->approver_id)->toBe($hrManager->id)
        ->and($hrManager->can('approve', $rejected))->toBeFalse();

    $balance = LeaveBalance::query()
        ->where('employee_id', $staff->employee->id)
        ->where('year', 2026)
        ->firstOrFail();

    expect($balance->used_days)->toBe(0);
});

test('pembatalan hanya boleh dilakukan oleh pemohon saat status masih pending', function (): void {
    $owner = User::query()->where('email', 'operator.prd@veloce.test')->firstOrFail();
    $otherEmployee = User::query()->where('email', 'teknisi.mnt@veloce.test')->firstOrFail();
    $hrManager = User::role(RoleName::HR_MANAGER->value)->firstOrFail();
    $annualLeave = LeaveType::query()->where('code', 'annual_leave')->firstOrFail();

    $submitAction = app(SubmitLeaveRequestAction::class);

    $pendingRequest = $submitAction->execute(
        $owner->employee,
        $annualLeave,
        CarbonImmutable::parse('2026-11-24', 'Asia/Jakarta'),
        CarbonImmutable::parse('2026-11-25', 'Asia/Jakarta'),
        'Pengajuan yang akan dibatalkan.',
    );

    expect($otherEmployee->can('cancel', $pendingRequest))->toBeFalse()
        ->and($hrManager->can('cancel', $pendingRequest))->toBeFalse();

    $this->actingAs($otherEmployee)->post(route('leave.cancel', $pendingRequest))->assertForbidden();
    $this->actingAs($hrManager)->post(route('leave.cancel', $pendingRequest))->assertForbidden();

    expect($owner->can('cancel', $pendingRequest))->toBeTrue();

    $this->actingAs($owner)
        ->post(route('leave.cancel', $pendingRequest))
        ->assertRedirect(route('leave.show', $pendingRequest));

    $pendingRequest->refresh();
    expect($pendingRequest->status)->toBe(LeaveRequestStatus::CANCELLED)
        ->and($pendingRequest->cancelled_at)->not->toBeNull()
        ->and($owner->can('cancel', $pendingRequest))->toBeFalse();

    $approvedRequest = $submitAction->execute(
        $owner->employee,
        $annualLeave,
        CarbonImmutable::parse('2026-12-01', 'Asia/Jakarta'),
        CarbonImmutable::parse('2026-12-02', 'Asia/Jakarta'),
        'Pengajuan yang akan disetujui.',
    );
    $approved = app(ApproveLeaveRequestAction::class)->execute($hrManager, $approvedRequest);

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

    $sickLeave = LeaveType::query()->where('code', 'sick')->firstOrFail();

    $this->actingAs($owner)->get(route('leave.index'))->assertOk();
    $this->actingAs($owner)->get(route('leave.create'))->assertOk()->assertSee('HR Manager');

    // Karena semua orang wajib absen, payroll & admin pun dapat mengakses halaman cuti/izin pribadi,
    // sedangkan akun tanpa profil karyawan ditolak 403.
    $noEmployeeUser = User::factory()->create();
    $noEmployeeUser->assignRole(RoleName::EMPLOYEE->value);
    $this->actingAs($payroll)->get(route('leave.index'))->assertOk();
    $this->actingAs($noEmployeeUser)->get(route('leave.index'))->assertForbidden();
    $this->actingAs($noEmployeeUser)->get(route('leave.create'))->assertForbidden();

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

    $attachmentUrl = route('media.leave.attachment', $leaveRequest);
    $showUrl = route('leave.show', $leaveRequest);

    auth()->logout();
    $this->get($attachmentUrl)->assertRedirect(route('login'));
    $this->get($showUrl)->assertRedirect(route('login'));

    foreach ([$owner, $sameDivisionHead, $sameDeptManager, $hrManager] as $allowedViewer) {
        $this->actingAs($allowedViewer)->get($showUrl)->assertOk();
        $mediaResponse = $this->actingAs($allowedViewer)->get($attachmentUrl);
        $mediaResponse->assertOk();
    }

    foreach ([$otherEmployee, $otherDivisionHead, $otherDeptManager, $payroll] as $deniedViewer) {
        $this->actingAs($deniedViewer)->get($showUrl)->assertForbidden();
        $this->actingAs($deniedViewer)->get($attachmentUrl)->assertForbidden();
    }
});
