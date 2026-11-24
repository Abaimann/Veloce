<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AttendanceStatus;
use App\Enums\EmploymentType;
use App\Enums\RoleName;
use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\OfficeLocation;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $demoPassword = $this->resolveDemoPassword();

        DB::transaction(function () use ($demoPassword): void {
            $this->seedRoles();
            $workSchedule = $this->seedMasterData();
            $this->seedLeaveTypes();
            $this->seedOrganizationAndAccounts($workSchedule, $demoPassword);
            $this->seedLeaveBalances();
        });

        if (! app()->runningUnitTests()) {
            $this->call(OperationalDataSeeder::class);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function resolveDemoPassword(): string
    {
        $configuredPassword = config('attendance.demo.default_password');

        if (is_string($configuredPassword) && $configuredPassword !== '') {
            return $configuredPassword;
        }

        if (app()->environment('testing')) {
            return Str::random(24);
        }

        throw new RuntimeException('DEMO_DEFAULT_PASSWORD belum diisi di .env');
    }

    private function seedRoles(): void
    {
        foreach (RoleName::cases() as $role) {
            Role::findOrCreate($role->value, 'web');
        }
    }

    private function seedMasterData(): WorkSchedule
    {
        $workSchedule = WorkSchedule::query()->updateOrCreate(
            ['name' => 'Reguler Senin-Jumat'],
            [
                'check_in_time' => '08:00:00',
                'check_out_time' => '17:00:00',
                'late_tolerance_minutes' => 15,
                'work_days' => [1, 2, 3, 4, 5],
                'is_active' => true,
            ],
        );

        foreach ([
            '2026-08-17' => 'Hari Kemerdekaan RI',
            '2026-12-25' => 'Hari Raya Natal',
        ] as $date => $name) {
            $holiday = Holiday::query()->whereDate('date', $date)->first();

            if ($holiday === null) {
                Holiday::query()->create([
                    'date' => $date,
                    'name' => $name,
                ]);
            } else {
                $holiday->update(['name' => $name]);
            }
        }

        OfficeLocation::query()->updateOrCreate(
            ['name' => (string) config('attendance.demo.office_name')],
            [
                'address' => 'Kawasan Industri Jababeka, Cikarang, Bekasi',
                'lat' => (float) config('attendance.demo.office_lat'),
                'lng' => (float) config('attendance.demo.office_lng'),
                'radius_meters' => (int) config('attendance.demo.office_radius_meters'),
                'is_active' => true,
            ],
        );

        return $workSchedule;
    }

    private function seedOrganizationAndAccounts(WorkSchedule $workSchedule, string $demoPassword): void
    {
        $hrga = $this->upsertDepartment('HRGA', 'HR & GA');
        $eng = $this->upsertDepartment('ENG', 'Engineering & Produksi');
        $qal = $this->upsertDepartment('QAL', 'Quality & Logistik');

        $engPrd = $this->upsertDivision($eng, 'ENG-PRD', 'Produksi Kabel');
        $engMnt = $this->upsertDivision($eng, 'ENG-MNT', 'Maintenance');
        $qalQc = $this->upsertDivision($qal, 'QAL-QC', 'Quality Control');
        $qalLog = $this->upsertDivision($qal, 'QAL-LOG', 'Logistik & Gudang');

        // Akun sistem non-karyawan (Super Admin & Payroll)
        $this->upsertUser(
            (string) config('attendance.demo.super_admin_email'),
            'Administrator Sistem',
            RoleName::SUPER_ADMIN,
            $demoPassword,
        );

        $this->upsertUser(
            (string) config('attendance.demo.payroll_email'),
            'Tim Payroll & Kompensasi',
            RoleName::PAYROLL,
            $demoPassword,
        );

        // HR Manager pada departemen HR & GA
        $hrEmployee = $this->upsertEmployeeAccount(
            email: (string) config('attendance.demo.hr_manager_email'),
            name: 'Ratna Dewi',
            role: RoleName::HR_MANAGER,
            password: $demoPassword,
            employeeNumber: 'DCI-HR-001',
            department: $hrga,
            division: null,
            supervisor: null,
            employmentType: EmploymentType::EXECUTIVE,
            positionTitle: 'HR & GA Manager',
            workSchedule: $workSchedule,
            joinDate: '2024-03-01',
        );
        $hrga->update(['head_employee_id' => $hrEmployee->id]);

        // Departemen ENG (Engineering & Produksi)
        $engManager = $this->upsertEmployeeAccount(
            email: 'manager.eng@veloce.test',
            name: 'Budi Santoso',
            role: RoleName::DEPT_MANAGER,
            password: $demoPassword,
            employeeNumber: 'DCI-ENG-001',
            department: $eng,
            division: null,
            supervisor: null,
            employmentType: EmploymentType::EXECUTIVE,
            positionTitle: 'Engineering & Production Manager',
            workSchedule: $workSchedule,
            joinDate: '2024-02-01',
        );
        $eng->update(['head_employee_id' => $engManager->id]);

        $engPrdHead = $this->upsertEmployeeAccount(
            email: 'head.prd@veloce.test',
            name: 'Agus Prasetyo',
            role: RoleName::DIVISION_HEAD,
            password: $demoPassword,
            employeeNumber: 'DCI-ENG-002',
            department: $eng,
            division: $engPrd,
            supervisor: $engManager,
            employmentType: EmploymentType::STAFF,
            positionTitle: 'Kepala Divisi Produksi Kabel',
            workSchedule: $workSchedule,
            joinDate: '2024-05-10',
        );
        $engPrd->update(['head_employee_id' => $engPrdHead->id]);

        $this->upsertEmployeeAccount(
            email: 'operator.prd@veloce.test',
            name: 'Siti Aminah',
            role: RoleName::EMPLOYEE,
            password: $demoPassword,
            employeeNumber: 'DCI-ENG-003',
            department: $eng,
            division: $engPrd,
            supervisor: $engPrdHead,
            employmentType: EmploymentType::STAFF,
            positionTitle: 'Operator Produksi Kabel',
            workSchedule: $workSchedule,
            joinDate: '2025-01-06',
        );

        $this->upsertEmployeeAccount(
            email: 'magang.prd@veloce.test',
            name: 'Dimas Saputra',
            role: RoleName::EMPLOYEE,
            password: $demoPassword,
            employeeNumber: 'DCI-INT-001',
            department: $eng,
            division: $engPrd,
            supervisor: $engPrdHead,
            employmentType: EmploymentType::INTERN,
            positionTitle: 'Mahasiswa Magang Produksi',
            workSchedule: $workSchedule,
            joinDate: '2026-09-01',
        );

        $engMntHead = $this->upsertEmployeeAccount(
            email: 'head.mnt@veloce.test',
            name: 'Hendra Wijaya',
            role: RoleName::DIVISION_HEAD,
            password: $demoPassword,
            employeeNumber: 'DCI-ENG-004',
            department: $eng,
            division: $engMnt,
            supervisor: $engManager,
            employmentType: EmploymentType::STAFF,
            positionTitle: 'Kepala Divisi Maintenance',
            workSchedule: $workSchedule,
            joinDate: '2024-06-15',
        );
        $engMnt->update(['head_employee_id' => $engMntHead->id]);

        $this->upsertEmployeeAccount(
            email: 'teknisi.mnt@veloce.test',
            name: 'Rudi Hartono',
            role: RoleName::EMPLOYEE,
            password: $demoPassword,
            employeeNumber: 'DCI-ENG-005',
            department: $eng,
            division: $engMnt,
            supervisor: $engMntHead,
            employmentType: EmploymentType::STAFF,
            positionTitle: 'Teknisi Maintenance',
            workSchedule: $workSchedule,
            joinDate: '2025-02-10',
        );

        // Karyawan langsung di bawah departemen ENG tanpa divisi
        $this->upsertEmployeeAccount(
            email: 'admin.eng@veloce.test',
            name: 'Lestari Wulandari',
            role: RoleName::EMPLOYEE,
            password: $demoPassword,
            employeeNumber: 'DCI-ENG-006',
            department: $eng,
            division: null,
            supervisor: $engManager,
            employmentType: EmploymentType::STAFF,
            positionTitle: 'Admin Teknik Departemen',
            workSchedule: $workSchedule,
            joinDate: '2025-03-03',
        );

        // Departemen QAL (Quality & Logistik)
        $qalManager = $this->upsertEmployeeAccount(
            email: 'manager.qal@veloce.test',
            name: 'Andi Kurniawan',
            role: RoleName::DEPT_MANAGER,
            password: $demoPassword,
            employeeNumber: 'DCI-QAL-001',
            department: $qal,
            division: null,
            supervisor: null,
            employmentType: EmploymentType::EXECUTIVE,
            positionTitle: 'Quality & Logistics Manager',
            workSchedule: $workSchedule,
            joinDate: '2024-02-15',
        );
        $qal->update(['head_employee_id' => $qalManager->id]);

        $qalQcHead = $this->upsertEmployeeAccount(
            email: 'head.qc@veloce.test',
            name: 'Dewi Sartika',
            role: RoleName::DIVISION_HEAD,
            password: $demoPassword,
            employeeNumber: 'DCI-QAL-002',
            department: $qal,
            division: $qalQc,
            supervisor: $qalManager,
            employmentType: EmploymentType::STAFF,
            positionTitle: 'Kepala Divisi Quality Control',
            workSchedule: $workSchedule,
            joinDate: '2024-07-01',
        );
        $qalQc->update(['head_employee_id' => $qalQcHead->id]);

        $this->upsertEmployeeAccount(
            email: 'inspector.qc@veloce.test',
            name: 'Fajar Nugroho',
            role: RoleName::EMPLOYEE,
            password: $demoPassword,
            employeeNumber: 'DCI-QAL-003',
            department: $qal,
            division: $qalQc,
            supervisor: $qalQcHead,
            employmentType: EmploymentType::STAFF,
            positionTitle: 'Staff Quality Control',
            workSchedule: $workSchedule,
            joinDate: '2025-04-01',
        );

        $qalLogHead = $this->upsertEmployeeAccount(
            email: 'head.log@veloce.test',
            name: 'Taufik Hidayat',
            role: RoleName::DIVISION_HEAD,
            password: $demoPassword,
            employeeNumber: 'DCI-QAL-004',
            department: $qal,
            division: $qalLog,
            supervisor: $qalManager,
            employmentType: EmploymentType::STAFF,
            positionTitle: 'Kepala Divisi Logistik & Gudang',
            workSchedule: $workSchedule,
            joinDate: '2024-08-12',
        );
        $qalLog->update(['head_employee_id' => $qalLogHead->id]);

        $this->upsertEmployeeAccount(
            email: 'staff.log@veloce.test',
            name: 'Rina Marlina',
            role: RoleName::EMPLOYEE,
            password: $demoPassword,
            employeeNumber: 'DCI-QAL-005',
            department: $qal,
            division: $qalLog,
            supervisor: $qalLogHead,
            employmentType: EmploymentType::STAFF,
            positionTitle: 'Staff Gudang & Logistik',
            workSchedule: $workSchedule,
            joinDate: '2025-05-05',
        );
    }

    private function upsertDepartment(string $code, string $name): Department
    {
        return Department::query()->updateOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'is_active' => true,
            ],
        );
    }

    private function upsertDivision(Department $department, string $code, string $name): Division
    {
        return Division::query()->updateOrCreate(
            [
                'department_id' => $department->id,
                'code' => $code,
            ],
            [
                'name' => $name,
                'is_active' => true,
            ],
        );
    }

    private function upsertUser(string $email, string $name, RoleName $role, string $password): User
    {
        $normalizedEmail = Str::lower(trim($email));
        $now = CarbonImmutable::now((string) config('attendance.timezone', 'Asia/Jakarta'));

        $user = User::query()->firstOrNew(['email' => $normalizedEmail]);
        $user->name = $name;
        $user->is_active = true;
        $user->email_verified_at ??= $now;

        if (! $user->exists || ! $user->hasActivatedPassword() || ! Hash::check($password, (string) $user->password)) {
            $user->password = $password;
        }

        $user->save();
        $user->syncRoles([$role->value]);

        return $user;
    }

    private function upsertEmployeeAccount(
        string $email,
        string $name,
        RoleName $role,
        string $password,
        string $employeeNumber,
        Department $department,
        ?Division $division,
        ?Employee $supervisor,
        EmploymentType $employmentType,
        string $positionTitle,
        WorkSchedule $workSchedule,
        string $joinDate,
    ): Employee {
        $user = $this->upsertUser($email, $name, $role, $password);

        return Employee::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'employee_number' => $employeeNumber,
                'department_id' => $department->id,
                'division_id' => $division?->id,
                'supervisor_id' => $supervisor?->id,
                'employment_type' => $employmentType,
                'position_title' => $positionTitle,
                'work_schedule_id' => $workSchedule->id,
                'join_date' => $joinDate,
                'is_active' => true,
            ],
        );
    }

    private function seedLeaveTypes(): void
    {
        if (! Schema::hasTable('leave_types')) {
            return;
        }

        $types = [
            [
                'code' => 'annual_leave',
                'name' => 'Cuti Tahunan',
                'attendance_status' => AttendanceStatus::LEAVE->value,
                'is_paid' => true,
                'requires_attachment' => false,
                'deducts_balance' => true,
                'hr_approval_after_days' => 3,
                'is_active' => true,
            ],
            [
                'code' => 'sick',
                'name' => 'Sakit',
                'attendance_status' => AttendanceStatus::SICK->value,
                'is_paid' => true,
                'requires_attachment' => true,
                'deducts_balance' => false,
                'hr_approval_after_days' => 2,
                'is_active' => true,
            ],
            [
                'code' => 'permission',
                'name' => 'Izin Keperluan Pribadi',
                'attendance_status' => AttendanceStatus::PERMISSION->value,
                'is_paid' => true,
                'requires_attachment' => false,
                'deducts_balance' => false,
                'hr_approval_after_days' => 2,
                'is_active' => true,
            ],
            [
                'code' => 'unpaid_leave',
                'name' => 'Cuti Tanpa Gaji',
                'attendance_status' => AttendanceStatus::LEAVE->value,
                'is_paid' => false,
                'requires_attachment' => false,
                'deducts_balance' => false,
                'hr_approval_after_days' => 3,
                'is_active' => true,
            ],
            [
                'code' => 'wfh',
                'name' => 'Work From Home (WFH)',
                'attendance_status' => AttendanceStatus::WFH->value,
                'is_paid' => true,
                'requires_attachment' => false,
                'deducts_balance' => false,
                'hr_approval_after_days' => null,
                'is_active' => true,
            ],
        ];

        foreach ($types as $type) {
            LeaveType::query()->updateOrCreate(
                ['code' => $type['code']],
                $type,
            );
        }
    }

    private function seedLeaveBalances(): void
    {
        if (! Schema::hasTable('leave_balances')) {
            return;
        }

        $currentYear = CarbonImmutable::now((string) config('attendance.timezone', 'Asia/Jakarta'))->year;

        Employee::query()->each(function (Employee $employee) use ($currentYear): void {
            LeaveBalance::query()->firstOrCreate(
                [
                    'employee_id' => $employee->id,
                    'year' => $currentYear,
                ],
                [
                    'entitled_days' => $employee->employment_type->defaultAnnualLeaveDays(),
                    'used_days' => 0,
                ],
            );
        });
    }
}
