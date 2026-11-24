<?php

declare(strict_types=1);

use App\Actions\Employee\CreateEmployeeAccountAction;
use App\Enums\EmploymentType;
use App\Enums\RoleName;
use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
use App\Models\User;
use App\Models\UserInvitation;
use App\Models\WorkSchedule;
use App\Policies\EmployeePolicy;
use App\Policies\MasterDataPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

/**
 * @return array<string, mixed>
 */
function buildEmployeePayload(
    int $departmentId,
    ?int $divisionId,
    ?int $supervisorId,
    string $role,
    string $suffix,
    ?int $workScheduleId = null,
): array {
    $scheduleId = $workScheduleId ?? WorkSchedule::query()->firstOrFail()->id;

    return [
        'name' => "Karyawan Uji {$suffix}",
        'email' => "uji.{$suffix}@veloce.test",
        'employee_number' => 'DCI-TEST-'.strtoupper($suffix),
        'department_id' => $departmentId,
        'division_id' => $divisionId,
        'supervisor_id' => $supervisorId,
        'employment_type' => EmploymentType::STAFF->value,
        'position_title' => 'Staff Uji',
        'work_schedule_id' => $scheduleId,
        'join_date' => '2026-11-01',
        'role' => $role,
    ];
}

test('scope visibleTo membatasi visibilitas data karyawan secara presisi untuk keenam role', function (): void {
    $this->seed();

    $eng = Department::query()->where('code', 'ENG')->firstOrFail();
    $engPrd = Division::query()->where('code', 'ENG-PRD')->firstOrFail();
    $qal = Department::query()->where('code', 'QAL')->firstOrFail();

    $superAdmin = User::role(RoleName::SUPER_ADMIN->value)->firstOrFail();
    $hrManager = User::role(RoleName::HR_MANAGER->value)->firstOrFail();
    $payroll = User::role(RoleName::PAYROLL->value)->firstOrFail();
    $engManager = User::query()->where('email', 'manager.eng@veloce.test')->firstOrFail();
    $engPrdHead = User::query()->where('email', 'head.prd@veloce.test')->firstOrFail();
    $engPrdStaff = User::query()->where('email', 'operator.prd@veloce.test')->firstOrFail();

    $totalEmployees = Employee::query()->count();
    $engCount = Employee::query()->where('department_id', $eng->id)->count();
    $engPrdCount = Employee::query()->where('division_id', $engPrd->id)->count();

    expect(Employee::query()->visibleTo($superAdmin)->count())->toBe($totalEmployees)
        ->and(Employee::query()->visibleTo($hrManager)->count())->toBe($totalEmployees)
        ->and(Employee::query()->visibleTo($payroll)->count())->toBe(0);

    $managerVisible = Employee::query()->visibleTo($engManager)->get();
    expect($managerVisible)->toHaveCount($engCount)
        ->and($managerVisible->pluck('department_id')->unique()->all())->toBe([$eng->id])
        ->and($managerVisible->where('department_id', $qal->id))->toBeEmpty();

    $divisionHeadVisible = Employee::query()->visibleTo($engPrdHead)->get();
    expect($divisionHeadVisible)->toHaveCount($engPrdCount)
        ->and($divisionHeadVisible->pluck('division_id')->unique()->all())->toBe([$engPrd->id]);

    $staffVisible = Employee::query()->visibleTo($engPrdStaff)->get();
    expect($staffVisible)->toHaveCount(1)
        ->and($staffVisible->first()->id)->toBe($engPrdStaff->employee->id);
});

test('matriks EmployeePolicy dan MasterDataPolicy berjalan sesuai hak akses role', function (): void {
    $this->seed();

    $eng = Department::query()->where('code', 'ENG')->firstOrFail();
    $qal = Department::query()->where('code', 'QAL')->firstOrFail();

    $superAdmin = User::role(RoleName::SUPER_ADMIN->value)->firstOrFail();
    $hrManager = User::role(RoleName::HR_MANAGER->value)->firstOrFail();
    $payroll = User::role(RoleName::PAYROLL->value)->firstOrFail();
    $engManager = User::query()->where('email', 'manager.eng@veloce.test')->firstOrFail();
    $engPrdHead = User::query()->where('email', 'head.prd@veloce.test')->firstOrFail();
    $engPrdStaff = User::query()->where('email', 'operator.prd@veloce.test')->firstOrFail();
    $engMntStaff = User::query()->where('email', 'teknisi.mnt@veloce.test')->firstOrFail();
    $qalStaff = User::query()->where('email', 'inspector.qc@veloce.test')->firstOrFail();

    $employeePolicy = app(EmployeePolicy::class);
    $masterPolicy = app(MasterDataPolicy::class);

    // viewAny
    expect($employeePolicy->viewAny($superAdmin))->toBeTrue()
        ->and($employeePolicy->viewAny($hrManager))->toBeTrue()
        ->and($employeePolicy->viewAny($engManager))->toBeTrue()
        ->and($employeePolicy->viewAny($engPrdHead))->toBeTrue()
        ->and($employeePolicy->viewAny($engPrdStaff))->toBeFalse()
        ->and($employeePolicy->viewAny($payroll))->toBeFalse();

    // view
    expect($employeePolicy->view($engPrdHead, $engPrdStaff->employee))->toBeTrue()
        ->and($employeePolicy->view($engPrdHead, $engPrdHead->employee))->toBeTrue()
        ->and($employeePolicy->view($engPrdHead, $engMntStaff->employee))->toBeFalse()
        ->and($employeePolicy->view($engManager, $engMntStaff->employee))->toBeTrue()
        ->and($employeePolicy->view($engManager, $qalStaff->employee))->toBeFalse()
        ->and($employeePolicy->view($engPrdStaff, $engPrdStaff->employee))->toBeTrue()
        ->and($employeePolicy->view($engPrdStaff, $engMntStaff->employee))->toBeFalse()
        ->and($employeePolicy->view($payroll, $engPrdStaff->employee))->toBeFalse();

    // regenerateInvitation
    expect($employeePolicy->regenerateInvitation($superAdmin, $hrManager->employee))->toBeTrue()
        ->and($employeePolicy->regenerateInvitation($hrManager, $engPrdStaff->employee))->toBeTrue()
        ->and($employeePolicy->regenerateInvitation($engManager, $engPrdStaff->employee))->toBeTrue()
        ->and($employeePolicy->regenerateInvitation($engManager, $qalStaff->employee))->toBeFalse()
        ->and($employeePolicy->regenerateInvitation($engPrdHead, $engPrdStaff->employee))->toBeFalse();

    $unassignedAdmin = User::factory()->create();
    $unassignedAdmin->assignRole(RoleName::SUPER_ADMIN->value);

    $superAdminEmployee = Employee::query()->create([
        'user_id' => $unassignedAdmin->id,
        'employee_number' => 'DCI-SA-001',
        'department_id' => $eng->id,
        'division_id' => null,
        'supervisor_id' => null,
        'employment_type' => EmploymentType::EXECUTIVE,
        'position_title' => 'System Owner',
        'work_schedule_id' => WorkSchedule::query()->firstOrFail()->id,
        'join_date' => '2024-01-01',
        'is_active' => true,
    ]);
    expect($employeePolicy->regenerateInvitation($hrManager, $superAdminEmployee))->toBeFalse()
        ->and($employeePolicy->regenerateInvitation($superAdmin, $superAdminEmployee))->toBeTrue();

    // MasterDataPolicy
    foreach ([$hrManager, $engManager, $engPrdHead, $engPrdStaff, $payroll] as $nonAdmin) {
        expect($masterPolicy->viewAny($nonAdmin))->toBeFalse()
            ->and($masterPolicy->manage($nonAdmin))->toBeFalse();
    }

    expect($masterPolicy->viewAny($superAdmin))->toBeTrue()
        ->and($masterPolicy->manage($superAdmin))->toBeTrue();
});

test('halaman daftar karyawan menegakkan otorisasi dan cakupan data per role', function (): void {
    $this->seed();

    $superAdmin = User::role(RoleName::SUPER_ADMIN->value)->firstOrFail();
    $hrManager = User::role(RoleName::HR_MANAGER->value)->firstOrFail();
    $payroll = User::role(RoleName::PAYROLL->value)->firstOrFail();
    $engManager = User::query()->where('email', 'manager.eng@veloce.test')->firstOrFail();
    $engPrdHead = User::query()->where('email', 'head.prd@veloce.test')->firstOrFail();
    $engPrdStaff = User::query()->where('email', 'operator.prd@veloce.test')->firstOrFail();

    $this->actingAs($engPrdStaff)
        ->get(route('management.employees.index'))
        ->assertForbidden();

    $this->actingAs($payroll)
        ->get(route('management.employees.index'))
        ->assertForbidden();

    $this->actingAs($engPrdHead)
        ->get(route('management.employees.index'))
        ->assertOk()
        ->assertSee('Siti Aminah')
        ->assertSee('Dimas Saputra')
        ->assertDontSee('Rudi Hartono')
        ->assertDontSee('Lestari Wulandari')
        ->assertDontSee('Fajar Nugroho');

    $this->actingAs($engManager)
        ->get(route('management.employees.index'))
        ->assertOk()
        ->assertSee('Siti Aminah')
        ->assertSee('Rudi Hartono')
        ->assertSee('Lestari Wulandari')
        ->assertDontSee('Fajar Nugroho')
        ->assertDontSee('Rina Marlina');

    $this->actingAs($hrManager)
        ->get(route('management.employees.index'))
        ->assertOk()
        ->assertSee('Siti Aminah')
        ->assertSee('Fajar Nugroho');

    $this->actingAs($superAdmin)
        ->get(route('management.employees.index'))
        ->assertOk()
        ->assertSee('Siti Aminah')
        ->assertSee('Fajar Nugroho');
});

test('manager departemen dapat membuat akun employee dan division head di departemennya namun ditolak lintas departemen atau eskalasi role', function (): void {
    $this->seed();

    $eng = Department::query()->where('code', 'ENG')->firstOrFail();
    $qal = Department::query()->where('code', 'QAL')->firstOrFail();
    $engPrd = Division::query()->where('code', 'ENG-PRD')->firstOrFail();
    $qalQc = Division::query()->where('code', 'QAL-QC')->firstOrFail();

    $engManager = User::query()->where('email', 'manager.eng@veloce.test')->firstOrFail();
    $engPrdHead = User::query()->where('email', 'head.prd@veloce.test')->firstOrFail();

    $staffResponse = $this->actingAs($engManager)->post(
        route('management.employees.store'),
        buildEmployeePayload($eng->id, $engPrd->id, $engPrdHead->employee->id, RoleName::EMPLOYEE->value, 'engstaff'),
    );

    expect($staffResponse->status())->toBeIn([200, 302]);

    $createdStaff = User::query()->where('email', 'uji.engstaff@veloce.test')->first();
    expect($createdStaff)->not->toBeNull()
        ->and($createdStaff->password)->toBeNull()
        ->and($createdStaff->email_verified_at)->toBeNull()
        ->and($createdStaff->is_active)->toBeTrue()
        ->and($createdStaff->hasRole(RoleName::EMPLOYEE->value))->toBeTrue();

    $invitation = UserInvitation::query()->where('user_id', $createdStaff->id)->first();
    expect($invitation)->not->toBeNull()
        ->and($invitation->used_at)->toBeNull();

    $divHeadResponse = $this->actingAs($engManager)->post(
        route('management.employees.store'),
        buildEmployeePayload($eng->id, $engPrd->id, $engManager->employee->id, RoleName::DIVISION_HEAD->value, 'enghead'),
    );

    expect($divHeadResponse->status())->toBeIn([200, 302]);

    $createdDivHead = User::query()->where('email', 'uji.enghead@veloce.test')->first();
    expect($createdDivHead)->not->toBeNull()
        ->and($createdDivHead->password)->toBeNull()
        ->and($createdDivHead->hasRole(RoleName::DIVISION_HEAD->value))->toBeTrue();

    // Ditolak membuat akun di departemen lain (QAL).
    $this->actingAs($engManager)
        ->post(
            route('management.employees.store'),
            buildEmployeePayload($qal->id, $qalQc->id, null, RoleName::EMPLOYEE->value, 'crossdept'),
        )
        ->assertForbidden();

    $this->assertDatabaseMissing('users', ['email' => 'uji.crossdept@veloce.test']);

    // Ditolak memberikan role dept_manager, hr_manager, atau super_admin.
    foreach ([RoleName::DEPT_MANAGER, RoleName::HR_MANAGER, RoleName::SUPER_ADMIN] as $forbiddenRole) {
        $suffix = 'escalate_'.$forbiddenRole->value;

        $this->actingAs($engManager)
            ->post(
                route('management.employees.store'),
                buildEmployeePayload($eng->id, null, null, $forbiddenRole->value, $suffix),
            )
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => "uji.{$suffix}@veloce.test"]);
    }
});

test('hr manager boleh membuat akun lintas departemen tetapi ditolak 403 memberi role super admin', function (): void {
    $this->seed();

    $eng = Department::query()->where('code', 'ENG')->firstOrFail();
    $qal = Department::query()->where('code', 'QAL')->firstOrFail();
    $qalQc = Division::query()->where('code', 'QAL-QC')->firstOrFail();
    $hrManager = User::role(RoleName::HR_MANAGER->value)->firstOrFail();

    $validResponse = $this->actingAs($hrManager)->post(
        route('management.employees.store'),
        buildEmployeePayload($qal->id, $qalQc->id, null, RoleName::EMPLOYEE->value, 'hrcreateqal'),
    );

    expect($validResponse->status())->toBeIn([200, 302]);
    $this->assertDatabaseHas('users', ['email' => 'uji.hrcreateqal@veloce.test', 'password' => null]);

    $this->actingAs($hrManager)
        ->post(
            route('management.employees.store'),
            buildEmployeePayload($eng->id, null, null, RoleName::SUPER_ADMIN->value, 'hrsuperadmin'),
        )
        ->assertForbidden();

    $this->assertDatabaseMissing('users', ['email' => 'uji.hrsuperadmin@veloce.test']);
});

test('payroll, employee, dan division head ditolak 403 membuat akun dan hanya super admin yang boleh mengakses master data', function (): void {
    $this->seed();

    $eng = Department::query()->where('code', 'ENG')->firstOrFail();
    $engPrd = Division::query()->where('code', 'ENG-PRD')->firstOrFail();

    $superAdmin = User::role(RoleName::SUPER_ADMIN->value)->firstOrFail();
    $hrManager = User::role(RoleName::HR_MANAGER->value)->firstOrFail();
    $engManager = User::query()->where('email', 'manager.eng@veloce.test')->firstOrFail();
    $engPrdHead = User::query()->where('email', 'head.prd@veloce.test')->firstOrFail();
    $engPrdStaff = User::query()->where('email', 'operator.prd@veloce.test')->firstOrFail();
    $payroll = User::role(RoleName::PAYROLL->value)->firstOrFail();

    foreach ([$payroll, $engPrdStaff, $engPrdHead] as $idx => $actor) {
        $suffix = "denied_{$idx}";

        $this->actingAs($actor)
            ->post(
                route('management.employees.store'),
                buildEmployeePayload($eng->id, $engPrd->id, null, RoleName::EMPLOYEE->value, $suffix),
            )
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => "uji.{$suffix}@veloce.test"]);
    }

    $masterAdminResponse = $this->actingAs($superAdmin)->get(route('management.master.index'));
    expect($masterAdminResponse->status())->toBeIn([200, 302]);

    foreach ([$hrManager, $engManager, $engPrdHead, $engPrdStaff, $payroll] as $nonAdmin) {
        $this->actingAs($nonAdmin)
            ->get(route('management.master.index'))
            ->assertForbidden();
    }
});

test('CreateEmployeeAccountAction menegakkan validasi divisi lintas departemen, kepala organisasi otomatis, dan activitylog', function (): void {
    $this->seed();

    $action = app(CreateEmployeeAccountAction::class);
    $superAdmin = User::role(RoleName::SUPER_ADMIN->value)->firstOrFail();
    $eng = Department::query()->where('code', 'ENG')->firstOrFail();
    $qalQc = Division::query()->where('code', 'QAL-QC')->firstOrFail();

    // Divisi tidak milik departemen yang dipilih -> ValidationException.
    expect(fn () => $action->execute(
        $superAdmin,
        buildEmployeePayload($eng->id, $qalQc->id, null, RoleName::EMPLOYEE->value, 'mismatch'),
    ))->toThrow(ValidationException::class);

    // Bypass otorisasi dari actor tidak berwenang -> AuthorizationException.
    $payroll = User::role(RoleName::PAYROLL->value)->firstOrFail();
    expect(fn () => $action->execute(
        $payroll,
        buildEmployeePayload($eng->id, null, null, RoleName::EMPLOYEE->value, 'bypass'),
    ))->toThrow(AuthorizationException::class);

    // Departemen & divisi baru tanpa head otomatis diisi head_employee_id saat membuat dept_manager / division_head.
    $newDept = Department::query()->create(['code' => 'FIN', 'name' => 'Finance', 'is_active' => true]);
    $newDiv = Division::query()->create([
        'department_id' => $newDept->id,
        'code' => 'FIN-ACC',
        'name' => 'Accounting',
        'is_active' => true,
    ]);

    $deptManagerResult = $action->execute(
        $superAdmin,
        buildEmployeePayload($newDept->id, null, null, RoleName::DEPT_MANAGER->value, 'finmgr'),
    );

    $divHeadResult = $action->execute(
        $superAdmin,
        buildEmployeePayload($newDept->id, $newDiv->id, $deptManagerResult['employee']->id, RoleName::DIVISION_HEAD->value, 'finhead'),
    );

    expect($newDept->fresh()->head_employee_id)->toBe($deptManagerResult['employee']->id)
        ->and($newDiv->fresh()->head_employee_id)->toBe($divHeadResult['employee']->id)
        ->and($deptManagerResult['invitation_url'])->toContain('/undangan/')
        ->and(Activity::query()->where('subject_type', Employee::class)->where('subject_id', $divHeadResult['employee']->id)->exists())->toBeTrue();
});

test('daftar karyawan mendukung pencarian dan filter departemen, divisi, peran, tipe, serta status sambil menjaga isolasi data', function (): void {
    $this->seed();

    $hrManager = User::role(RoleName::HR_MANAGER->value)->firstOrFail();
    $engManager = User::query()->where('email', 'manager.eng@veloce.test')->firstOrFail();
    $qal = Department::query()->where('code', 'QAL')->firstOrFail();
    $engPrd = Division::query()->where('code', 'ENG-PRD')->firstOrFail();
    $workSchedule = WorkSchedule::query()->active()->firstOrFail();

    // Tambah akun menunggu aktivasi dan nonaktif untuk menguji filter status akun.
    $pendingUser = User::factory()->unactivated()->create([
        'name' => 'Karyawan Undangan Baru',
        'email' => 'undangan.baru@veloce.test',
    ]);
    $pendingUser->assignRole(RoleName::EMPLOYEE->value);
    Employee::query()->create([
        'user_id' => $pendingUser->id,
        'employee_number' => 'DCI-NEW-001',
        'department_id' => $qal->id,
        'division_id' => null,
        'work_schedule_id' => $workSchedule->id,
        'employment_type' => EmploymentType::STAFF,
        'position_title' => 'Staff Baru Quality',
        'is_active' => true,
        'join_date' => '2026-11-20',
    ]);

    $inactiveUser = User::factory()->inactive()->create([
        'name' => 'Karyawan Nonaktif Uji',
        'email' => 'nonaktif.uji@veloce.test',
    ]);
    $inactiveUser->assignRole(RoleName::EMPLOYEE->value);
    Employee::query()->create([
        'user_id' => $inactiveUser->id,
        'employee_number' => 'DCI-OFF-001',
        'department_id' => $qal->id,
        'division_id' => null,
        'work_schedule_id' => $workSchedule->id,
        'employment_type' => EmploymentType::STAFF,
        'position_title' => 'Ex Staff',
        'is_active' => false,
        'join_date' => '2025-01-10',
    ]);

    // Pencarian q berdasarkan nama / NIK / email.
    $this->actingAs($hrManager)
        ->get(route('management.employees.index', ['q' => 'Dimas Saputra']))
        ->assertOk()
        ->assertSee('Dimas Saputra')
        ->assertDontSee('Fajar Nugroho');

    $this->actingAs($hrManager)
        ->get(route('management.employees.index', ['q' => 'DCI-QAL-003']))
        ->assertOk()
        ->assertSee('Fajar Nugroho')
        ->assertDontSee('Siti Aminah');

    // Filter departemen, divisi, peran, dan tipe karyawan.
    $this->actingAs($hrManager)
        ->get(route('management.employees.index', ['department_id' => $qal->id]))
        ->assertOk()
        ->assertSee('Fajar Nugroho')
        ->assertDontSee('Siti Aminah');

    $this->actingAs($hrManager)
        ->get(route('management.employees.index', ['division_id' => $engPrd->id]))
        ->assertOk()
        ->assertSee('Siti Aminah')
        ->assertDontSee('Rudi Hartono');

    $this->actingAs($hrManager)
        ->get(route('management.employees.index', ['division_id' => 'none']))
        ->assertOk()
        ->assertSee('Lestari Wulandari')
        ->assertDontSee('Siti Aminah');

    $this->actingAs($hrManager)
        ->get(route('management.employees.index', ['role' => RoleName::DEPT_MANAGER->value]))
        ->assertOk()
        ->assertSee('Budi Santoso')
        ->assertDontSee('Siti Aminah');

    $this->actingAs($hrManager)
        ->get(route('management.employees.index', ['employment_type' => EmploymentType::INTERN->value]))
        ->assertOk()
        ->assertSee('Dimas Saputra')
        ->assertDontSee('Siti Aminah');

    // Filter status akun.
    $this->actingAs($hrManager)
        ->get(route('management.employees.index', ['status' => 'pending_activation']))
        ->assertOk()
        ->assertSee('Karyawan Undangan Baru')
        ->assertDontSee('Siti Aminah');

    $this->actingAs($hrManager)
        ->get(route('management.employees.index', ['status' => 'inactive']))
        ->assertOk()
        ->assertSee('Karyawan Nonaktif Uji')
        ->assertDontSee('Siti Aminah');

    // Manager departemen tetap terisolasi ke departemennya meski parameter department_id dimanipulasi.
    $this->actingAs($engManager)
        ->get(route('management.employees.index', ['department_id' => $qal->id]))
        ->assertOk()
        ->assertSee('Siti Aminah')
        ->assertDontSee('Fajar Nugroho');
});
