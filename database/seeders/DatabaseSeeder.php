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

            if (! app()->runningUnitTests()) {
                $this->seedEnterpriseDepartmentsAndEmployees($workSchedule, $demoPassword);
            }

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

    private function seedEnterpriseDepartmentsAndEmployees(WorkSchedule $workSchedule, string $demoPassword): void
    {
        // 1. Tambahan Divisi & Karyawan di HR & GA (HRGA)
        $hrga = Department::query()->where('code', 'HRGA')->firstOrFail();
        $hrManager = Employee::query()->where('employee_number', 'DCI-HR-001')->firstOrFail();

        $hrRec = $this->upsertDivision($hrga, 'HRGA-REC', 'Rekrutmen & Hubungan Industrial');
        $hrGa = $this->upsertDivision($hrga, 'HRGA-GA', 'General Affairs & Fasilitas');

        $recHead = $this->upsertEmployeeAccount(
            email: 'head.rec@veloce.test',
            name: 'Maya Anggraini',
            role: RoleName::DIVISION_HEAD,
            password: $demoPassword,
            employeeNumber: 'DCI-HR-002',
            department: $hrga,
            division: $hrRec,
            supervisor: $hrManager,
            employmentType: EmploymentType::STAFF,
            positionTitle: 'Kepala Divisi Rekrutmen & Hubungan Industrial',
            workSchedule: $workSchedule,
            joinDate: '2024-04-01',
        );
        $hrRec->update(['head_employee_id' => $recHead->id]);

        $this->upsertEmployeeAccount(
            email: 'staff.rec@veloce.test',
            name: 'Bayu Pratama',
            role: RoleName::EMPLOYEE,
            password: $demoPassword,
            employeeNumber: 'DCI-HR-003',
            department: $hrga,
            division: $hrRec,
            supervisor: $recHead,
            employmentType: EmploymentType::STAFF,
            positionTitle: 'Staff Rekrutmen & Pelatihan',
            workSchedule: $workSchedule,
            joinDate: '2025-02-15',
        );

        $gaHead = $this->upsertEmployeeAccount(
            email: 'head.ga@veloce.test',
            name: 'Doni Setiawan',
            role: RoleName::DIVISION_HEAD,
            password: $demoPassword,
            employeeNumber: 'DCI-HR-004',
            department: $hrga,
            division: $hrGa,
            supervisor: $hrManager,
            employmentType: EmploymentType::STAFF,
            positionTitle: 'Kepala Divisi General Affairs',
            workSchedule: $workSchedule,
            joinDate: '2024-05-01',
        );
        $hrGa->update(['head_employee_id' => $gaHead->id]);

        $this->upsertEmployeeAccount(
            email: 'staff.ga@veloce.test',
            name: 'Joko Susilo',
            role: RoleName::EMPLOYEE,
            password: $demoPassword,
            employeeNumber: 'DCI-HR-005',
            department: $hrga,
            division: $hrGa,
            supervisor: $gaHead,
            employmentType: EmploymentType::STAFF,
            positionTitle: 'Staff Sarana & Prasarana Pabrik',
            workSchedule: $workSchedule,
            joinDate: '2025-03-10',
        );

        // 2. Departemen IT (Teknologi Informasi & Sistem Digital)
        $it = $this->upsertDepartment('IT', 'Teknologi Informasi & Sistem Digital');
        $itInf = $this->upsertDivision($it, 'IT-INF', 'Infrastruktur & Keamanan Jaringan');
        $itDev = $this->upsertDivision($it, 'IT-DEV', 'Pengembangan Software & ERP');

        $itManager = $this->upsertEmployeeAccount(
            email: 'manager.it@veloce.test',
            name: 'Irfan Hakim',
            role: RoleName::DEPT_MANAGER,
            password: $demoPassword,
            employeeNumber: 'DCI-IT-001',
            department: $it,
            division: null,
            supervisor: null,
            employmentType: EmploymentType::EXECUTIVE,
            positionTitle: 'IT & Digital Systems Manager',
            workSchedule: $workSchedule,
            joinDate: '2024-01-15',
        );
        $it->update(['head_employee_id' => $itManager->id]);

        $itInfHead = $this->upsertEmployeeAccount(
            email: 'head.itinf@veloce.test',
            name: 'Satria Wibawa',
            role: RoleName::DIVISION_HEAD,
            password: $demoPassword,
            employeeNumber: 'DCI-IT-002',
            department: $it,
            division: $itInf,
            supervisor: $itManager,
            employmentType: EmploymentType::STAFF,
            positionTitle: 'Kepala Divisi Infrastruktur Jaringan',
            workSchedule: $workSchedule,
            joinDate: '2024-06-01',
        );
        $itInf->update(['head_employee_id' => $itInfHead->id]);

        $this->upsertEmployeeAccount(
            email: 'network.it@veloce.test',
            name: 'Kevin Sanjaya',
            role: RoleName::EMPLOYEE,
            password: $demoPassword,
            employeeNumber: 'DCI-IT-003',
            department: $it,
            division: $itInf,
            supervisor: $itInfHead,
            employmentType: EmploymentType::STAFF,
            positionTitle: 'Network & System Engineer',
            workSchedule: $workSchedule,
            joinDate: '2025-01-10',
        );

        $this->upsertEmployeeAccount(
            email: 'support.it@veloce.test',
            name: 'Rizky Maulana',
            role: RoleName::EMPLOYEE,
            password: $demoPassword,
            employeeNumber: 'DCI-IT-004',
            department: $it,
            division: $itInf,
            supervisor: $itInfHead,
            employmentType: EmploymentType::STAFF,
            positionTitle: 'IT Technical Support',
            workSchedule: $workSchedule,
            joinDate: '2025-04-15',
        );

        $itDevHead = $this->upsertEmployeeAccount(
            email: 'head.itdev@veloce.test',
            name: 'Ahmad Zaki',
            role: RoleName::DIVISION_HEAD,
            password: $demoPassword,
            employeeNumber: 'DCI-IT-005',
            department: $it,
            division: $itDev,
            supervisor: $itManager,
            employmentType: EmploymentType::STAFF,
            positionTitle: 'Kepala Divisi Software & ERP',
            workSchedule: $workSchedule,
            joinDate: '2024-07-01',
        );
        $itDev->update(['head_employee_id' => $itDevHead->id]);

        $this->upsertEmployeeAccount(
            email: 'programmer.it@veloce.test',
            name: 'Farhan Alamsyah',
            role: RoleName::EMPLOYEE,
            password: $demoPassword,
            employeeNumber: 'DCI-IT-006',
            department: $it,
            division: $itDev,
            supervisor: $itDevHead,
            employmentType: EmploymentType::STAFF,
            positionTitle: 'Senior Web & Mobile Developer',
            workSchedule: $workSchedule,
            joinDate: '2025-03-01',
        );

        $this->upsertEmployeeAccount(
            email: 'analyst.it@veloce.test',
            name: 'Nabila Putri',
            role: RoleName::EMPLOYEE,
            password: $demoPassword,
            employeeNumber: 'DCI-IT-007',
            department: $it,
            division: $itDev,
            supervisor: $itDevHead,
            employmentType: EmploymentType::STAFF,
            positionTitle: 'Business & System Analyst',
            workSchedule: $workSchedule,
            joinDate: '2025-06-01',
        );

        // 3. Departemen FIN (Finance, Accounting & Tax)
        $fin = $this->upsertDepartment('FIN', 'Finance, Accounting & Pajak');
        $finAcc = $this->upsertDivision($fin, 'FIN-ACC', 'Akuntansi & Keuangan Operasional');
        $finTax = $this->upsertDivision($fin, 'FIN-TAX', 'Perpajakan & Audit Internal');

        $finManager = $this->upsertEmployeeAccount(
            email: 'manager.fin@veloce.test',
            name: 'Sri Wahyuni',
            role: RoleName::DEPT_MANAGER,
            password: $demoPassword,
            employeeNumber: 'DCI-FIN-001',
            department: $fin,
            division: null,
            supervisor: null,
            employmentType: EmploymentType::EXECUTIVE,
            positionTitle: 'Finance & Accounting Manager',
            workSchedule: $workSchedule,
            joinDate: '2024-01-20',
        );
        $fin->update(['head_employee_id' => $finManager->id]);

        $finAccHead = $this->upsertEmployeeAccount(
            email: 'head.acc@veloce.test',
            name: 'Cindy Permata',
            role: RoleName::DIVISION_HEAD,
            password: $demoPassword,
            employeeNumber: 'DCI-FIN-002',
            department: $fin,
            division: $finAcc,
            supervisor: $finManager,
            employmentType: EmploymentType::STAFF,
            positionTitle: 'Kepala Divisi Akuntansi',
            workSchedule: $workSchedule,
            joinDate: '2024-05-15',
        );
        $finAcc->update(['head_employee_id' => $finAccHead->id]);

        $this->upsertEmployeeAccount(
            email: 'staff.acc@veloce.test',
            name: 'Dian Kusumawati',
            role: RoleName::EMPLOYEE,
            password: $demoPassword,
            employeeNumber: 'DCI-FIN-003',
            department: $fin,
            division: $finAcc,
            supervisor: $finAccHead,
            employmentType: EmploymentType::STAFF,
            positionTitle: 'Staff Akuntansi Keuangan',
            workSchedule: $workSchedule,
            joinDate: '2025-02-01',
        );

        $this->upsertEmployeeAccount(
            email: 'staff.fin@veloce.test',
            name: 'Eko Prasetyo',
            role: RoleName::EMPLOYEE,
            password: $demoPassword,
            employeeNumber: 'DCI-FIN-004',
            department: $fin,
            division: $finAcc,
            supervisor: $finAccHead,
            employmentType: EmploymentType::STAFF,
            positionTitle: 'Staff Kasir & Perbendaharaan',
            workSchedule: $workSchedule,
            joinDate: '2025-04-20',
        );

        $finTaxHead = $this->upsertEmployeeAccount(
            email: 'head.tax@veloce.test',
            name: 'Gunawan Santosa',
            role: RoleName::DIVISION_HEAD,
            password: $demoPassword,
            employeeNumber: 'DCI-FIN-005',
            department: $fin,
            division: $finTax,
            supervisor: $finManager,
            employmentType: EmploymentType::STAFF,
            positionTitle: 'Kepala Divisi Perpajakan & Audit',
            workSchedule: $workSchedule,
            joinDate: '2024-08-01',
        );
        $finTax->update(['head_employee_id' => $finTaxHead->id]);

        $this->upsertEmployeeAccount(
            email: 'staff.tax@veloce.test',
            name: 'Hani Amalia',
            role: RoleName::EMPLOYEE,
            password: $demoPassword,
            employeeNumber: 'DCI-FIN-006',
            department: $fin,
            division: $finTax,
            supervisor: $finTaxHead,
            employmentType: EmploymentType::STAFF,
            positionTitle: 'Staff Administrasi Pajak (Tax Officer)',
            workSchedule: $workSchedule,
            joinDate: '2025-05-12',
        );

        // 4. Departemen SCM (Supply Chain Management & Procurement)
        $scm = $this->upsertDepartment('SCM', 'Supply Chain & Pengadaan Material');
        $scmPrc = $this->upsertDivision($scm, 'SCM-PRC', 'Pengadaan Material & Vendor');
        $scmPln = $this->upsertDivision($scm, 'SCM-PLN', 'Perencanaan Produksi & Inventori');

        $scmManager = $this->upsertEmployeeAccount(
            email: 'manager.scm@veloce.test',
            name: 'Bambang Irawan',
            role: RoleName::DEPT_MANAGER,
            password: $demoPassword,
            employeeNumber: 'DCI-SCM-001',
            department: $scm,
            division: null,
            supervisor: null,
            employmentType: EmploymentType::EXECUTIVE,
            positionTitle: 'Supply Chain & Procurement Manager',
            workSchedule: $workSchedule,
            joinDate: '2024-02-10',
        );
        $scm->update(['head_employee_id' => $scmManager->id]);

        $scmPrcHead = $this->upsertEmployeeAccount(
            email: 'head.prc@veloce.test',
            name: 'Anita Rahmawati',
            role: RoleName::DIVISION_HEAD,
            password: $demoPassword,
            employeeNumber: 'DCI-SCM-002',
            department: $scm,
            division: $scmPrc,
            supervisor: $scmManager,
            employmentType: EmploymentType::STAFF,
            positionTitle: 'Kepala Divisi Pengadaan & Vendor',
            workSchedule: $workSchedule,
            joinDate: '2024-06-20',
        );
        $scmPrc->update(['head_employee_id' => $scmPrcHead->id]);

        $this->upsertEmployeeAccount(
            email: 'buyer.scm@veloce.test',
            name: 'Ilham Ramadhan',
            role: RoleName::EMPLOYEE,
            password: $demoPassword,
            employeeNumber: 'DCI-SCM-003',
            department: $scm,
            division: $scmPrc,
            supervisor: $scmPrcHead,
            employmentType: EmploymentType::STAFF,
            positionTitle: 'Purchasing Officer (Bahan Baku Logam)',
            workSchedule: $workSchedule,
            joinDate: '2025-01-20',
        );

        $this->upsertEmployeeAccount(
            email: 'buyer2.scm@veloce.test',
            name: 'Tiara Maharani',
            role: RoleName::EMPLOYEE,
            password: $demoPassword,
            employeeNumber: 'DCI-SCM-004',
            department: $scm,
            division: $scmPrc,
            supervisor: $scmPrcHead,
            employmentType: EmploymentType::STAFF,
            positionTitle: 'Purchasing Officer (Komponen Polimer & Kemasan)',
            workSchedule: $workSchedule,
            joinDate: '2025-03-25',
        );

        $scmPlnHead = $this->upsertEmployeeAccount(
            email: 'head.ppic@veloce.test',
            name: 'Yoga Saputra',
            role: RoleName::DIVISION_HEAD,
            password: $demoPassword,
            employeeNumber: 'DCI-SCM-005',
            department: $scm,
            division: $scmPln,
            supervisor: $scmManager,
            employmentType: EmploymentType::STAFF,
            positionTitle: 'Kepala Divisi PPIC',
            workSchedule: $workSchedule,
            joinDate: '2024-07-15',
        );
        $scmPln->update(['head_employee_id' => $scmPlnHead->id]);

        $this->upsertEmployeeAccount(
            email: 'staff.ppic@veloce.test',
            name: 'Rian Hidayat',
            role: RoleName::EMPLOYEE,
            password: $demoPassword,
            employeeNumber: 'DCI-SCM-006',
            department: $scm,
            division: $scmPln,
            supervisor: $scmPlnHead,
            employmentType: EmploymentType::STAFF,
            positionTitle: 'Staff Pengendalian Inventori & Jadwal',
            workSchedule: $workSchedule,
            joinDate: '2025-05-15',
        );

        // 5. Departemen MKT (Pemasaran & Penjualan Komersial)
        $mkt = $this->upsertDepartment('MKT', 'Penjualan & Pemasaran Komersial');
        $mktOem = $this->upsertDivision($mkt, 'MKT-OEM', 'Penjualan Industri & OEM Otomotif');
        $mktAft = $this->upsertDivision($mkt, 'MKT-AFT', 'Aftermarket & Distribusi Retail');

        $mktManager = $this->upsertEmployeeAccount(
            email: 'manager.mkt@veloce.test',
            name: 'Surya Pratama',
            role: RoleName::DEPT_MANAGER,
            password: $demoPassword,
            employeeNumber: 'DCI-MKT-001',
            department: $mkt,
            division: null,
            supervisor: null,
            employmentType: EmploymentType::EXECUTIVE,
            positionTitle: 'Sales & Marketing Commercial Manager',
            workSchedule: $workSchedule,
            joinDate: '2024-02-01',
        );
        $mkt->update(['head_employee_id' => $mktManager->id]);

        $mktOemHead = $this->upsertEmployeeAccount(
            email: 'head.oem@veloce.test',
            name: 'Denny Kurnia',
            role: RoleName::DIVISION_HEAD,
            password: $demoPassword,
            employeeNumber: 'DCI-MKT-002',
            department: $mkt,
            division: $mktOem,
            supervisor: $mktManager,
            employmentType: EmploymentType::STAFF,
            positionTitle: 'Kepala Divisi Penjualan OEM',
            workSchedule: $workSchedule,
            joinDate: '2024-05-10',
        );
        $mktOem->update(['head_employee_id' => $mktOemHead->id]);

        $this->upsertEmployeeAccount(
            email: 'sales.oem@veloce.test',
            name: 'Aditya Nugraha',
            role: RoleName::EMPLOYEE,
            password: $demoPassword,
            employeeNumber: 'DCI-MKT-003',
            department: $mkt,
            division: $mktOem,
            supervisor: $mktOemHead,
            employmentType: EmploymentType::STAFF,
            positionTitle: 'Key Account Executive (OEM)',
            workSchedule: $workSchedule,
            joinDate: '2025-02-10',
        );

        $mktAftHead = $this->upsertEmployeeAccount(
            email: 'head.aft@veloce.test',
            name: 'Wahyu Utomo',
            role: RoleName::DIVISION_HEAD,
            password: $demoPassword,
            employeeNumber: 'DCI-MKT-004',
            department: $mkt,
            division: $mktAft,
            supervisor: $mktManager,
            employmentType: EmploymentType::STAFF,
            positionTitle: 'Kepala Divisi Aftermarket & Retail',
            workSchedule: $workSchedule,
            joinDate: '2024-08-20',
        );
        $mktAft->update(['head_employee_id' => $mktAftHead->id]);

        $this->upsertEmployeeAccount(
            email: 'sales.aft@veloce.test',
            name: 'Putri Utami',
            role: RoleName::EMPLOYEE,
            password: $demoPassword,
            employeeNumber: 'DCI-MKT-005',
            department: $mkt,
            division: $mktAft,
            supervisor: $mktAftHead,
            employmentType: EmploymentType::STAFF,
            positionTitle: 'Sales Representative Jawa Barat',
            workSchedule: $workSchedule,
            joinDate: '2025-04-05',
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
