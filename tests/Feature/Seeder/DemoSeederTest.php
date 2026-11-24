<?php

declare(strict_types=1);

use App\Enums\EmploymentType;
use App\Enums\RoleName;
use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\OfficeLocation;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

test('demo seeder mengisi seluruh role, struktur organisasi, master data, dan akun secara idempoten', function (): void {
    $this->seed();

    // Tepat 6 role tersedia pada guard web.
    expect(Role::query()->count())->toBe(6);

    foreach (RoleName::cases() as $role) {
        expect(Role::query()->where('name', $role->value)->where('guard_name', 'web')->exists())->toBeTrue();
    }

    // Akun inti per role.
    $superAdmins = User::role(RoleName::SUPER_ADMIN->value)->get();
    $hrManagers = User::role(RoleName::HR_MANAGER->value)->get();
    $payrolls = User::role(RoleName::PAYROLL->value)->get();

    expect($superAdmins)->toHaveCount(1)
        ->and($hrManagers)->toHaveCount(1)
        ->and($payrolls)->toHaveCount(1)
        ->and($superAdmins->first()->email)->toBe((string) config('attendance.demo.super_admin_email'))
        ->and($hrManagers->first()->email)->toBe((string) config('attendance.demo.hr_manager_email'))
        ->and($payrolls->first()->email)->toBe((string) config('attendance.demo.payroll_email'))
        ->and($superAdmins->first()->employee)->not->toBeNull()
        ->and($payrolls->first()->employee)->not->toBeNull()
        ->and($hrManagers->first()->employee)->not->toBeNull()
        ->and($hrManagers->first()->employee->employment_type)->toBe(EmploymentType::EXECUTIVE)
        ->and($hrManagers->first()->employee->base_salary)->toBeGreaterThan(0);

    $configuredPassword = config('attendance.demo.default_password');
    if (is_string($configuredPassword) && $configuredPassword !== '') {
        expect(Hash::check($configuredPassword, (string) $superAdmins->first()->password))->toBeTrue();
    }

    // Master data jadwal kerja, libur nasional 2026, dan lokasi kantor.
    $workSchedule = WorkSchedule::query()->where('name', 'Reguler Senin-Jumat')->first();
    expect($workSchedule)->not->toBeNull()
        ->and($workSchedule->check_in_time)->toStartWith('08:00')
        ->and($workSchedule->check_out_time)->toStartWith('17:00')
        ->and($workSchedule->late_tolerance_minutes)->toBe(15)
        ->and(array_map('intval', $workSchedule->work_days))->toBe([1, 2, 3, 4, 5])
        ->and($workSchedule->is_active)->toBeTrue();

    expect(Holiday::query()->whereDate('date', '2026-08-17')->where('name', 'Hari Kemerdekaan RI')->exists())->toBeTrue()
        ->and(Holiday::query()->whereDate('date', '2026-12-25')->where('name', 'Hari Raya Natal')->exists())->toBeTrue();

    $office = OfficeLocation::query()->where('name', (string) config('attendance.demo.office_name'))->first();
    expect($office)->not->toBeNull()
        ->and(OfficeLocation::query()->count())->toBe(1)
        ->and($office->radius_meters)->toBe((int) config('attendance.demo.office_radius_meters'))
        ->and($office->is_active)->toBeTrue();

    // Departemen & divisi operasional.
    $hrga = Department::query()->where('code', 'HRGA')->first();
    $eng = Department::query()->where('code', 'ENG')->first();
    $qal = Department::query()->where('code', 'QAL')->first();

    expect($hrga)->not->toBeNull()
        ->and($eng)->not->toBeNull()
        ->and($qal)->not->toBeNull()
        ->and($eng->divisions()->count())->toBeGreaterThanOrEqual(2)
        ->and($qal->divisions()->count())->toBeGreaterThanOrEqual(2)
        ->and($eng->head_employee_id)->not->toBeNull()
        ->and($qal->head_employee_id)->not->toBeNull();

    foreach (['ENG-PRD', 'ENG-MNT', 'QAL-QC', 'QAL-LOG'] as $divisionCode) {
        $division = Division::query()->where('code', $divisionCode)->first();
        expect($division)->not->toBeNull()
            ->and($division->head_employee_id)->not->toBeNull();
    }

    // Karyawan: dept_manager, division_head, staff, karyawan langsung tanpa divisi, dan 1 anak magang.
    expect(User::role(RoleName::DEPT_MANAGER->value)->count())->toBeGreaterThanOrEqual(2)
        ->and(User::role(RoleName::DIVISION_HEAD->value)->count())->toBeGreaterThanOrEqual(4)
        ->and(Employee::query()->where('employment_type', EmploymentType::STAFF->value)->count())->toBeGreaterThanOrEqual(8);

    $directEngEmployee = Employee::query()
        ->where('department_id', $eng->id)
        ->whereNull('division_id')
        ->where('supervisor_id', $eng->head_employee_id)
        ->first();

    expect($directEngEmployee)->not->toBeNull()
        ->and($directEngEmployee->user->hasRole(RoleName::EMPLOYEE->value))->toBeTrue();

    $engPrd = Division::query()->where('code', 'ENG-PRD')->firstOrFail();
    $interns = Employee::query()->where('employment_type', EmploymentType::INTERN->value)->get();

    expect($interns)->toHaveCount(1)
        ->and($interns->first()->division_id)->toBe($engPrd->id)
        ->and($interns->first()->user->hasRole(RoleName::EMPLOYEE->value))->toBeTrue();

    // Idempotensi: menjalankan seeder ulang tidak menduplikasi data.
    $userCountBefore = User::query()->count();
    $employeeCountBefore = Employee::query()->count();
    $departmentCountBefore = Department::query()->count();
    $divisionCountBefore = Division::query()->count();

    $this->seed();

    expect(Role::query()->count())->toBe(6)
        ->and(User::query()->count())->toBe($userCountBefore)
        ->and(Employee::query()->count())->toBe($employeeCountBefore)
        ->and(Department::query()->count())->toBe($departmentCountBefore)
        ->and(Division::query()->count())->toBe($divisionCountBefore)
        ->and(Holiday::query()->count())->toBe(2)
        ->and(OfficeLocation::query()->count())->toBe(1);
});
