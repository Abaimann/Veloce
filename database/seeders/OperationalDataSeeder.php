<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Payroll\CalculateMonthlyPayrollAction;
use App\Enums\ApprovalStatus;
use App\Enums\AttendanceStatus;
use App\Enums\LeaveRequestStatus;
use App\Enums\RoleName;
use App\Enums\TargetPeriodType;
use App\Enums\WorkSubmissionStatus;
use App\Models\Attendance;
use App\Models\DailyWorkSubmission;
use App\Models\DepartmentTarget;
use App\Models\DivisionTarget;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestApproval;
use App\Models\LeaveType;
use App\Models\OfficeLocation;
use App\Models\User;
use App\Notifications\LeaveStatusUpdatedNotification;
use App\Notifications\LeaveSubmittedForApprovalNotification;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OperationalDataSeeder extends Seeder
{
    /**
     * Mengisi data aktivitas operasional realistis (presensi historis, presensi hari ini,
     * pengajuan cuti berjenjang dengan status approved, pending & rejected, serta notifikasi in-app).
     */
    public function run(): void
    {
        $tz = (string) config('attendance.timezone', 'Asia/Jakarta');
        $office = OfficeLocation::query()->where('is_active', true)->first();

        if ($office === null) {
            return;
        }

        $employees = Employee::query()->with(['user', 'department', 'division'])->get()->keyBy(fn (Employee $e) => (string) $e->user?->email);
        $superAdmin = User::role(RoleName::SUPER_ADMIN->value)->first();
        $leaveTypes = LeaveType::query()->get()->keyBy('code');

        if ($employees->isEmpty() || $leaveTypes->isEmpty() || $superAdmin === null) {
            return;
        }

        DB::transaction(function () use ($employees, $superAdmin, $leaveTypes, $office, $tz): void {
            $this->seedHistoricalLeaveRequests($employees, $superAdmin, $leaveTypes, $tz);
            $this->seedAttendances($employees, $office, $tz);
            $this->seedTargetsAndWorkSubmissions($employees, $tz);
        });

        app(CalculateMonthlyPayrollAction::class)->execute(2026, 11, $superAdmin);
    }

    /**
     * @param  Collection<string, Employee>  $employees
     * @param  Collection<string, LeaveType>  $leaveTypes
     */
    private function seedHistoricalLeaveRequests($employees, User $superAdmin, $leaveTypes, string $tz): void
    {
        $annualLeave = $leaveTypes->get('annual_leave');
        $sickLeave = $leaveTypes->get('sick');
        $permissionLeave = $leaveTypes->get('permission');
        $wfhLeave = $leaveTypes->get('wfh');

        $siti = $employees->get('operator.prd@veloce.test');
        $agus = $employees->get('head.prd@veloce.test');
        $budi = $employees->get('manager.eng@veloce.test');
        $hendra = $employees->get('head.mnt@veloce.test');
        $rudi = $employees->get('teknisi.mnt@veloce.test');
        $fajar = $employees->get('inspector.qc@veloce.test');
        $dewi = $employees->get('head.qc@veloce.test');
        $andi = $employees->get('manager.qal@veloce.test');
        $ratna = $employees->get('hr@veloce.test');
        $dimas = $employees->get('magang.prd@veloce.test');

        // 1. Approved Historical Cuti Tahunan: Siti Aminah (12 - 13 Nov 2026)
        if ($siti !== null && $annualLeave !== null && $agus !== null && $budi !== null) {
            $req1 = LeaveRequest::query()->updateOrCreate(
                [
                    'employee_id' => $siti->id,
                    'start_date' => '2026-11-12',
                    'end_date' => '2026-11-13',
                ],
                [
                    'leave_type_id' => $annualLeave->id,
                    'total_work_days' => 2,
                    'reason' => 'Acara syukuran keluarga di kampung halaman.',
                    'status' => LeaveRequestStatus::APPROVED,
                    'current_step' => 2,
                    'finalized_at' => CarbonImmutable::parse('2026-11-10 14:30:00', $tz),
                    'created_at' => CarbonImmutable::parse('2026-11-10 09:15:00', $tz),
                    'updated_at' => CarbonImmutable::parse('2026-11-10 14:30:00', $tz),
                ]
            );

            LeaveRequestApproval::query()->updateOrCreate(
                ['leave_request_id' => $req1->id, 'step' => 1],
                [
                    'role' => RoleName::DIVISION_HEAD->value,
                    'approver_id' => $agus->user_id,
                    'status' => ApprovalStatus::APPROVED,
                    'note' => 'Disetujui. Tugas shift operator telah dialihkan.',
                    'acted_at' => CarbonImmutable::parse('2026-11-10 10:45:00', $tz),
                ]
            );

            LeaveRequestApproval::query()->updateOrCreate(
                ['leave_request_id' => $req1->id, 'step' => 2],
                [
                    'role' => RoleName::DEPT_MANAGER->value,
                    'approver_id' => $budi->user_id,
                    'status' => ApprovalStatus::APPROVED,
                    'note' => 'Disetujui. Selamat berlibur.',
                    'acted_at' => CarbonImmutable::parse('2026-11-10 14:30:00', $tz),
                ]
            );

            // Update pemakaian saldo cuti tahunan
            LeaveBalance::query()
                ->where('employee_id', $siti->id)
                ->where('year', 2026)
                ->update(['used_days' => 2]);

            if ($siti->user !== null && ! $this->hasNotificationForRequest($siti->user, $req1->id)) {
                $siti->user->notify(new LeaveStatusUpdatedNotification(
                    $req1,
                    'Pengajuan Cuti Tahunan Disetujui',
                    'Pengajuan cuti tahunan Anda tanggal 12-13 Nov 2026 telah disetujui penuh oleh Manager Departemen.'
                ));
            }
        }

        // 2. Approved Historical WFH: Hendra Wijaya (18 Nov 2026)
        if ($hendra !== null && $wfhLeave !== null && $budi !== null) {
            $req2 = LeaveRequest::query()->updateOrCreate(
                [
                    'employee_id' => $hendra->id,
                    'start_date' => '2026-11-18',
                    'end_date' => '2026-11-18',
                ],
                [
                    'leave_type_id' => $wfhLeave->id,
                    'total_work_days' => 1,
                    'reason' => 'Koordinasi teknis daring dengan vendor mesin pemotong kabel.',
                    'status' => LeaveRequestStatus::APPROVED,
                    'current_step' => 1,
                    'finalized_at' => CarbonImmutable::parse('2026-11-16 11:00:00', $tz),
                    'created_at' => CarbonImmutable::parse('2026-11-16 08:30:00', $tz),
                    'updated_at' => CarbonImmutable::parse('2026-11-16 11:00:00', $tz),
                ]
            );

            LeaveRequestApproval::query()->updateOrCreate(
                ['leave_request_id' => $req2->id, 'step' => 1],
                [
                    'role' => RoleName::DEPT_MANAGER->value,
                    'approver_id' => $budi->user_id,
                    'status' => ApprovalStatus::APPROVED,
                    'note' => 'Disetujui. Pastikan laporan hasil evaluasi vendor dikirim sore hari.',
                    'acted_at' => CarbonImmutable::parse('2026-11-16 11:00:00', $tz),
                ]
            );
        }

        // 3. Approved Historical Sakit: Rudi Hartono (09 Nov 2026)
        if ($rudi !== null && $sickLeave !== null && $hendra !== null && $budi !== null) {
            $req3 = LeaveRequest::query()->updateOrCreate(
                [
                    'employee_id' => $rudi->id,
                    'start_date' => '2026-11-09',
                    'end_date' => '2026-11-09',
                ],
                [
                    'leave_type_id' => $sickLeave->id,
                    'total_work_days' => 1,
                    'reason' => 'Demam tinggi dan flu, istirahat atas petunjuk dokter klinik.',
                    'status' => LeaveRequestStatus::APPROVED,
                    'current_step' => 2,
                    'finalized_at' => CarbonImmutable::parse('2026-11-09 11:15:00', $tz),
                    'created_at' => CarbonImmutable::parse('2026-11-09 08:00:00', $tz),
                    'updated_at' => CarbonImmutable::parse('2026-11-09 11:15:00', $tz),
                ]
            );

            LeaveRequestApproval::query()->updateOrCreate(
                ['leave_request_id' => $req3->id, 'step' => 1],
                [
                    'role' => RoleName::DIVISION_HEAD->value,
                    'approver_id' => $hendra->user_id,
                    'status' => ApprovalStatus::APPROVED,
                    'note' => 'Disetujui. Cepat pulih Rudi.',
                    'acted_at' => CarbonImmutable::parse('2026-11-09 09:30:00', $tz),
                ]
            );

            LeaveRequestApproval::query()->updateOrCreate(
                ['leave_request_id' => $req3->id, 'step' => 2],
                [
                    'role' => RoleName::DEPT_MANAGER->value,
                    'approver_id' => $budi->user_id,
                    'status' => ApprovalStatus::APPROVED,
                    'note' => 'Disetujui. Istirahat yang cukup.',
                    'acted_at' => CarbonImmutable::parse('2026-11-09 11:15:00', $tz),
                ]
            );
        }

        // 4. PENDING for Super Admin: Ratna Dewi (HR Manager) WFH besok (25 Nov 2026)
        if ($ratna !== null && $wfhLeave !== null) {
            $req4 = LeaveRequest::query()->updateOrCreate(
                [
                    'employee_id' => $ratna->id,
                    'start_date' => '2026-11-25',
                    'end_date' => '2026-11-25',
                ],
                [
                    'leave_type_id' => $wfhLeave->id,
                    'total_work_days' => 1,
                    'reason' => 'Rekapitulasi evaluasi KPI tahunan perusahaan dan draf kebijakan cuti bersama 2027.',
                    'status' => LeaveRequestStatus::PENDING,
                    'current_step' => 1,
                    'created_at' => CarbonImmutable::parse('2026-11-24 10:00:00', $tz),
                    'updated_at' => CarbonImmutable::parse('2026-11-24 10:00:00', $tz),
                ]
            );

            LeaveRequestApproval::query()->updateOrCreate(
                ['leave_request_id' => $req4->id, 'step' => 1],
                [
                    'role' => RoleName::SUPER_ADMIN->value,
                    'approver_id' => null,
                    'status' => ApprovalStatus::PENDING,
                    'note' => null,
                    'acted_at' => null,
                ]
            );

            if (! $this->hasNotificationForRequest($superAdmin, $req4->id)) {
                $superAdmin->notify(new LeaveSubmittedForApprovalNotification($req4, 1));
            }
        }

        // 5. PENDING for HR Manager (Ratna Dewi): Siti Aminah Cuti Tahunan (27 Nov 2026)
        if ($siti !== null && $annualLeave !== null && $ratna !== null) {
            $req5 = LeaveRequest::query()->updateOrCreate(
                [
                    'employee_id' => $siti->id,
                    'start_date' => '2026-11-27',
                    'end_date' => '2026-11-27',
                ],
                [
                    'leave_type_id' => $annualLeave->id,
                    'total_work_days' => 1,
                    'reason' => 'Perpanjangan dokumen kendaraan bermotor di Samsat Cikarang.',
                    'status' => LeaveRequestStatus::PENDING,
                    'current_step' => 1,
                    'created_at' => CarbonImmutable::parse('2026-11-24 11:30:00', $tz),
                    'updated_at' => CarbonImmutable::parse('2026-11-24 11:30:00', $tz),
                ]
            );

            LeaveRequestApproval::query()->where('leave_request_id', $req5->id)->where('step', '>', 1)->delete();
            LeaveRequestApproval::query()->updateOrCreate(
                ['leave_request_id' => $req5->id, 'step' => 1],
                [
                    'role' => RoleName::HR_MANAGER->value,
                    'approver_id' => null,
                    'status' => ApprovalStatus::PENDING,
                    'note' => null,
                    'acted_at' => null,
                ]
            );

            if ($ratna->user !== null && ! $this->hasNotificationForRequest($ratna->user, $req5->id)) {
                $ratna->user->notify(new LeaveSubmittedForApprovalNotification($req5, 1));
            }
        }

        // 6. PENDING for HR Manager (Ratna Dewi): Rudi Hartono Izin Keperluan Pribadi (26 Nov 2026)
        if ($rudi !== null && $permissionLeave !== null && $ratna !== null) {
            $req6 = LeaveRequest::query()->updateOrCreate(
                [
                    'employee_id' => $rudi->id,
                    'start_date' => '2026-11-26',
                    'end_date' => '2026-11-26',
                ],
                [
                    'leave_type_id' => $permissionLeave->id,
                    'total_work_days' => 1,
                    'reason' => 'Mengantar orang tua kontrol kesehatan di RS Harapan Cikarang.',
                    'status' => LeaveRequestStatus::PENDING,
                    'current_step' => 1,
                    'created_at' => CarbonImmutable::parse('2026-11-24 13:10:00', $tz),
                    'updated_at' => CarbonImmutable::parse('2026-11-24 13:10:00', $tz),
                ]
            );

            LeaveRequestApproval::query()->where('leave_request_id', $req6->id)->where('step', '>', 1)->delete();
            LeaveRequestApproval::query()->updateOrCreate(
                ['leave_request_id' => $req6->id, 'step' => 1],
                [
                    'role' => RoleName::HR_MANAGER->value,
                    'approver_id' => null,
                    'status' => ApprovalStatus::PENDING,
                    'note' => null,
                    'acted_at' => null,
                ]
            );

            if ($ratna->user !== null && ! $this->hasNotificationForRequest($ratna->user, $req6->id)) {
                $ratna->user->notify(new LeaveSubmittedForApprovalNotification($req6, 1));
            }
        }

        // 7. PENDING for HR Manager (Ratna Dewi): Fajar Nugroho Cuti Tahunan 4 Hari (01 - 04 Des 2026)
        if ($fajar !== null && $annualLeave !== null && $ratna !== null) {
            $req7 = LeaveRequest::query()->updateOrCreate(
                [
                    'employee_id' => $fajar->id,
                    'start_date' => '2026-12-01',
                    'end_date' => '2026-12-04',
                ],
                [
                    'leave_type_id' => $annualLeave->id,
                    'total_work_days' => 4,
                    'reason' => 'Cuti tahunan untuk syukuran keluarga di Jawa Tengah.',
                    'status' => LeaveRequestStatus::PENDING,
                    'current_step' => 1,
                    'created_at' => CarbonImmutable::parse('2026-11-23 14:00:00', $tz),
                    'updated_at' => CarbonImmutable::parse('2026-11-24 09:30:00', $tz),
                ]
            );

            LeaveRequestApproval::query()->where('leave_request_id', $req7->id)->where('step', '>', 1)->delete();
            LeaveRequestApproval::query()->updateOrCreate(
                ['leave_request_id' => $req7->id, 'step' => 1],
                [
                    'role' => RoleName::HR_MANAGER->value,
                    'approver_id' => null,
                    'status' => ApprovalStatus::PENDING,
                    'note' => null,
                    'acted_at' => null,
                ]
            );

            if ($ratna->user !== null && ! $this->hasNotificationForRequest($ratna->user, $req7->id)) {
                $ratna->user->notify(new LeaveSubmittedForApprovalNotification($req7, 1));
            }
        }

        // 8. Rejected Historical Cuti Tahunan: Dimas Saputra (11 Nov 2026)
        if ($dimas !== null && $annualLeave !== null && $agus !== null) {
            $req8 = LeaveRequest::query()->updateOrCreate(
                [
                    'employee_id' => $dimas->id,
                    'start_date' => '2026-11-11',
                    'end_date' => '2026-11-11',
                ],
                [
                    'leave_type_id' => $annualLeave->id,
                    'total_work_days' => 1,
                    'reason' => 'Keperluan keluarga di luar kota.',
                    'status' => LeaveRequestStatus::REJECTED,
                    'current_step' => 1,
                    'finalized_at' => CarbonImmutable::parse('2026-11-10 13:00:00', $tz),
                    'created_at' => CarbonImmutable::parse('2026-11-10 11:00:00', $tz),
                    'updated_at' => CarbonImmutable::parse('2026-11-10 13:00:00', $tz),
                ]
            );

            LeaveRequestApproval::query()->updateOrCreate(
                ['leave_request_id' => $req8->id, 'step' => 1],
                [
                    'role' => RoleName::DIVISION_HEAD->value,
                    'approver_id' => $agus->user_id,
                    'status' => ApprovalStatus::REJECTED,
                    'note' => 'Karyawan magang tidak memiliki kuota cuti tahunan, silakan ajukan Izin Keperluan Pribadi atau Cuti Tanpa Gaji.',
                    'acted_at' => CarbonImmutable::parse('2026-11-10 13:00:00', $tz),
                ]
            );

            if ($dimas->user !== null && ! $this->hasNotificationForRequest($dimas->user, $req8->id)) {
                $dimas->user->notify(new LeaveStatusUpdatedNotification(
                    $req8,
                    'Pengajuan Cuti Tahunan Ditolak',
                    'Pengajuan cuti tahunan Anda tanggal 11 Nov 2026 ditolak oleh Kepala Divisi.'
                ));
            }
        }
    }

    /**
     * @param  Collection<string, Employee>  $employees
     */
    private function seedAttendances($employees, OfficeLocation $office, string $tz): void
    {
        $workdates = [
            // Minggu 1
            '2026-11-02', '2026-11-03', '2026-11-04', '2026-11-05', '2026-11-06',
            // Minggu 2
            '2026-11-09', '2026-11-10', '2026-11-11', '2026-11-12', '2026-11-13',
            // Minggu 3
            '2026-11-16', '2026-11-17', '2026-11-18', '2026-11-19', '2026-11-20',
            // Minggu 4
            '2026-11-23', '2026-11-24',
        ];

        $today = '2026-11-24';
        $baseLat = (float) $office->lat;
        $baseLng = (float) $office->lng;

        // Ambil ID leave request untuk relasi presensi izin/cuti
        $siti = $employees->get('operator.prd@veloce.test');
        $hendra = $employees->get('head.mnt@veloce.test');
        $rudi = $employees->get('teknisi.mnt@veloce.test');

        $sitiLeaveReq = LeaveRequest::query()->where('employee_id', $siti?->id)->whereDate('start_date', '2026-11-12')->first();
        $rudiSickReq = LeaveRequest::query()->where('employee_id', $rudi?->id)->whereDate('start_date', '2026-11-09')->first();
        $hendraWfhReq = LeaveRequest::query()->where('employee_id', $hendra?->id)->whereDate('start_date', '2026-11-18')->first();

        foreach ($employees as $email => $employee) {
            foreach ($workdates as $index => $date) {
                // Hari ini (2026-11-24): hanya check-in pagi untuk sebagian besar karyawan
                if ($date === $today) {
                    // Dua karyawan belum check-in pagi ini agar ada variasi "Belum Absen Masuk"
                    if (in_array($email, ['head.mnt@veloce.test', 'teknisi.mnt@veloce.test'], true)) {
                        continue;
                    }

                    // Fajar Nugroho terlambat hari ini (08:21 WIB)
                    $isLate = ($email === 'inspector.qc@veloce.test');
                    $inMinute = $isLate ? 21 : 45 + ($employee->id % 15);
                    $inSecond = 10 + ($employee->id * 3) % 45;

                    $checkInAt = CarbonImmutable::parse("{$date} 08:{$inMinute}:{$inSecond}", $tz);
                    if (! $isLate) {
                        $checkInAt = CarbonImmutable::parse("{$date} 07:{$inMinute}:{$inSecond}", $tz);
                    }

                    Attendance::query()->updateOrCreate(
                        [
                            'employee_id' => $employee->id,
                            'work_date' => $date,
                        ],
                        [
                            'check_in_at' => $checkInAt,
                            'check_out_at' => null,
                            'check_in_lat' => $baseLat + (($employee->id % 7 - 3) * 0.00008),
                            'check_in_lng' => $baseLng + (($employee->id % 5 - 2) * 0.00008),
                            'check_in_accuracy' => 12.5 + ($employee->id % 10),
                            'check_in_distance_meters' => 24.0 + ($employee->id % 40),
                            'check_out_lat' => null,
                            'check_out_lng' => null,
                            'check_out_accuracy' => null,
                            'check_out_distance_meters' => null,
                            'office_location_id' => $office->id,
                            'status' => $isLate ? AttendanceStatus::LATE : AttendanceStatus::PRESENT,
                            'leave_request_id' => null,
                            'flags' => [],
                        ]
                    );

                    continue;
                }

                // Cek presensi Cuti Tahunan Siti Aminah (12-13 Nov)
                if ($email === 'operator.prd@veloce.test' && in_array($date, ['2026-11-12', '2026-11-13'], true)) {
                    Attendance::query()->updateOrCreate(
                        ['employee_id' => $employee->id, 'work_date' => $date],
                        [
                            'check_in_at' => null,
                            'check_out_at' => null,
                            'check_in_lat' => null,
                            'check_in_lng' => null,
                            'check_in_accuracy' => null,
                            'check_in_distance_meters' => null,
                            'check_out_lat' => null,
                            'check_out_lng' => null,
                            'check_out_accuracy' => null,
                            'check_out_distance_meters' => null,
                            'office_location_id' => null,
                            'status' => AttendanceStatus::LEAVE,
                            'leave_request_id' => $sitiLeaveReq?->id,
                            'flags' => [],
                        ]
                    );

                    continue;
                }

                // Cek presensi Sakit Rudi Hartono (09 Nov)
                if ($email === 'teknisi.mnt@veloce.test' && $date === '2026-11-09') {
                    Attendance::query()->updateOrCreate(
                        ['employee_id' => $employee->id, 'work_date' => $date],
                        [
                            'check_in_at' => null,
                            'check_out_at' => null,
                            'check_in_lat' => null,
                            'check_in_lng' => null,
                            'check_in_accuracy' => null,
                            'check_in_distance_meters' => null,
                            'check_out_lat' => null,
                            'check_out_lng' => null,
                            'check_out_accuracy' => null,
                            'check_out_distance_meters' => null,
                            'office_location_id' => null,
                            'status' => AttendanceStatus::SICK,
                            'leave_request_id' => $rudiSickReq?->id,
                            'flags' => [],
                        ]
                    );

                    continue;
                }

                // Cek presensi WFH Hendra Wijaya (18 Nov)
                if ($email === 'head.mnt@veloce.test' && $date === '2026-11-18') {
                    Attendance::query()->updateOrCreate(
                        ['employee_id' => $employee->id, 'work_date' => $date],
                        [
                            'check_in_at' => CarbonImmutable::parse("{$date} 07:55:18", $tz),
                            'check_out_at' => CarbonImmutable::parse("{$date} 17:08:42", $tz),
                            'check_in_lat' => -6.2851200,
                            'check_in_lng' => 107.1124500,
                            'check_in_accuracy' => 14.0,
                            'check_in_distance_meters' => 4520.0,
                            'check_out_lat' => -6.2851400,
                            'check_out_lng' => 107.1124200,
                            'check_out_accuracy' => 15.0,
                            'check_out_distance_meters' => 4525.0,
                            'office_location_id' => null,
                            'status' => AttendanceStatus::WFH,
                            'leave_request_id' => $hendraWfhReq?->id,
                            'flags' => [],
                        ]
                    );

                    continue;
                }

                // Presensi harian reguler
                // Sekitar 10% kejadian terlambat (check-in antara 08:16 - 08:26 WIB)
                $isLate = (($employee->id + $index) % 9 === 0);
                $inMinute = $isLate ? (16 + ($employee->id % 9)) : (45 + ($employee->id % 14));
                $inHour = $isLate ? '08' : '07';
                $outMinute = 5 + ($employee->id % 25);

                $checkInTime = CarbonImmutable::parse(sprintf('%s %s:%02d:%02d', $date, $inHour, $inMinute, ($employee->id * 7) % 55), $tz);
                $checkOutTime = CarbonImmutable::parse(sprintf('%s 17:%02d:%02d', $date, $outMinute, ($employee->id * 11) % 55), $tz);

                $flags = [];
                $checkInAccuracy = 10.0 + ($employee->id % 15);

                // Contoh anomali GPS akurasi rendah (> 100m) pada Taufik Hidayat (17 Nov)
                if ($email === 'head.log@veloce.test' && $date === '2026-11-17') {
                    $checkInAccuracy = 125.0;
                    $flags[] = 'low_gps_accuracy';
                }

                // Contoh anomali selisih jam klien (> 5 menit) pada Rudi Hartono (20 Nov)
                if ($email === 'teknisi.mnt@veloce.test' && $date === '2026-11-20') {
                    $flags[] = 'client_clock_skew';
                }

                Attendance::query()->updateOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'work_date' => $date,
                    ],
                    [
                        'check_in_at' => $checkInTime,
                        'check_out_at' => $checkOutTime,
                        'check_in_lat' => $baseLat + ((($employee->id + $index) % 7 - 3) * 0.00007),
                        'check_in_lng' => $baseLng + ((($employee->id + $index) % 5 - 2) * 0.00007),
                        'check_in_accuracy' => $checkInAccuracy,
                        'check_in_distance_meters' => 20.0 + (($employee->id + $index) % 45),
                        'check_out_lat' => $baseLat + ((($employee->id + $index) % 6 - 2) * 0.00007),
                        'check_out_lng' => $baseLng + ((($employee->id + $index) % 4 - 2) * 0.00007),
                        'check_out_accuracy' => 12.0 + ($employee->id % 12),
                        'check_out_distance_meters' => 25.0 + (($employee->id + $index) % 40),
                        'office_location_id' => $office->id,
                        'status' => $isLate ? AttendanceStatus::LATE : AttendanceStatus::PRESENT,
                        'leave_request_id' => null,
                        'flags' => $flags,
                    ]
                );
            }
        }
    }

    /**
     * @param  Collection<string, Employee>  $employees
     */
    private function seedTargetsAndWorkSubmissions($employees, string $tz): void
    {
        $budi = $employees->get('manager.eng@veloce.test');
        $agus = $employees->get('head.prd@veloce.test');
        $siti = $employees->get('operator.prd@veloce.test');
        $dimas = $employees->get('magang.prd@veloce.test');

        $irfan = $employees->get('manager.it@veloce.test');
        $zaki = $employees->get('head.itdev@veloce.test');
        $farhan = $employees->get('programmer.it@veloce.test');
        $nabila = $employees->get('analyst.it@veloce.test');

        $andi = $employees->get('manager.qal@veloce.test');
        $dewi = $employees->get('head.qc@veloce.test');
        $fajar = $employees->get('inspector.qc@veloce.test');

        if ($budi !== null && $agus !== null) {
            $engMonthly = DepartmentTarget::query()->updateOrCreate(
                [
                    'department_id' => $budi->department_id,
                    'year' => 2026,
                    'month' => 11,
                    'title' => 'Target Produksi & Pemeliharaan Lini November 2026',
                ],
                [
                    'created_by' => $budi->user_id,
                    'description' => 'Memastikan output produksi kabel dan kesiapan mesin pabrik tercapai 100% sesuai jadwal.',
                    'target_value' => 10,
                    'unit_label' => 'laporan',
                    'status' => 'active',
                ]
            );

            $prdWeekly = DivisionTarget::query()->updateOrCreate(
                [
                    'division_id' => $agus->division_id,
                    'title' => 'Laporan Output Harian Lini Produksi Kabel Minggu ke-4',
                    'start_date' => '2026-11-23',
                    'end_date' => '2026-11-27',
                ],
                [
                    'department_id' => $budi->department_id,
                    'department_target_id' => $engMonthly->id,
                    'parent_division_target_id' => null,
                    'created_by' => $agus->user_id,
                    'description' => 'Target produksi mingguan; operator dan magang mengunggah bukti kerja harian langsung ke Drive PT.',
                    'period_type' => TargetPeriodType::WEEKLY,
                    'target_value' => 5,
                    'weight_percent' => 60.0,
                    'unit_label' => 'laporan',
                ]
            );

            if ($siti !== null) {
                DailyWorkSubmission::query()->updateOrCreate(
                    ['employee_id' => $siti->id, 'work_date' => '2026-11-23', 'title' => 'Log Output Kabel NYY Batch #210'],
                    [
                        'division_target_id' => $prdWeekly->id,
                        'description' => 'Pengecekan gulungan tembaga dan pelabelan 120 roll kabel selesai tepat waktu.',
                        'contribution_value' => 2,
                        'drive_file_id' => 'demo-drive-prd-210',
                        'drive_file_name' => 'DCI-ENG-003_2026-11-23_Log-Output-NYY-210.pdf',
                        'drive_web_view_link' => 'https://drive.google.com/drive/my-drive',
                        'drive_folder_path' => 'ENG - Engineering & Produksi / ENG-PRD - Produksi Kabel / 2026-11',
                        'mime_type' => 'application/pdf',
                        'status' => WorkSubmissionStatus::APPROVED,
                        'reviewed_by' => $agus->user_id,
                        'reviewed_at' => CarbonImmutable::parse('2026-11-23 16:45:00', $tz),
                        'review_note' => 'Data batch cocok dengan rekap gudang. Disetujui.',
                        'created_at' => CarbonImmutable::parse('2026-11-23 15:30:00', $tz),
                    ]
                );

                DailyWorkSubmission::query()->updateOrCreate(
                    ['employee_id' => $siti->id, 'work_date' => '2026-11-24', 'title' => 'Laporan Hasil Extruder Shift Pagi Batch #215'],
                    [
                        'division_target_id' => $prdWeekly->id,
                        'description' => 'Pengukuran ketebalan isolasi PVC lulus standar toleransi.',
                        'contribution_value' => 1,
                        'drive_file_id' => 'demo-drive-prd-215',
                        'drive_file_name' => 'DCI-ENG-003_2026-11-24_Extruder-Batch-215.pdf',
                        'drive_web_view_link' => 'https://drive.google.com/drive/my-drive',
                        'drive_folder_path' => 'ENG - Engineering & Produksi / ENG-PRD - Produksi Kabel / 2026-11',
                        'mime_type' => 'application/pdf',
                        'status' => WorkSubmissionStatus::PENDING_REVIEW,
                        'created_at' => CarbonImmutable::parse('2026-11-24 14:20:00', $tz),
                    ]
                );
            }

            if ($dimas !== null) {
                DailyWorkSubmission::query()->updateOrCreate(
                    ['employee_id' => $dimas->id, 'work_date' => '2026-11-24', 'title' => 'Rekap Inventarisasi Sparepart Roll Lini 2'],
                    [
                        'division_target_id' => $prdWeekly->id,
                        'description' => 'Dokumentasi pengecekan stok komponen harian oleh tim magang.',
                        'contribution_value' => 1,
                        'drive_file_id' => 'demo-drive-int-215',
                        'drive_file_name' => 'DCI-INT-001_2026-11-24_Inventaris-Lini2.xlsx',
                        'drive_web_view_link' => 'https://drive.google.com/drive/my-drive',
                        'drive_folder_path' => 'ENG - Engineering & Produksi / ENG-PRD - Produksi Kabel / 2026-11',
                        'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'status' => WorkSubmissionStatus::APPROVED,
                        'reviewed_by' => $agus->user_id,
                        'reviewed_at' => CarbonImmutable::parse('2026-11-24 16:05:00', $tz),
                        'review_note' => 'Sudah diperiksa dan sesuai stok fisik.',
                        'created_at' => CarbonImmutable::parse('2026-11-24 13:40:00', $tz),
                    ]
                );
            }

            DivisionTarget::query()->updateOrCreate(
                [
                    'division_id' => $agus->division_id,
                    'title' => 'Kalibrasi & Uji Tarik Mesin Extruder Minggu ke-3',
                    'start_date' => '2026-11-16',
                    'end_date' => '2026-11-20',
                ],
                [
                    'department_id' => $budi->department_id,
                    'department_target_id' => $engMonthly->id,
                    'parent_division_target_id' => null,
                    'created_by' => $agus->user_id,
                    'description' => 'Pemeriksaan berkala lini produksi minggu ketiga bulan berjalan.',
                    'period_type' => TargetPeriodType::WEEKLY,
                    'target_value' => 4,
                    'approved_value' => 4,
                    'progress_percent' => 100.0,
                    'weight_percent' => 40.0,
                    'unit_label' => 'mesin',
                    'status' => 'completed',
                ]
            );

            $prdWeekly->recalculateProgress();
        }

        if ($irfan !== null && $zaki !== null) {
            $itMonthly = DepartmentTarget::query()->updateOrCreate(
                [
                    'department_id' => $irfan->department_id,
                    'year' => 2026,
                    'month' => 11,
                    'title' => 'Digitalisasi Sistem Absensi, Payroll & KPI Veloce',
                ],
                [
                    'created_by' => $irfan->user_id,
                    'description' => 'Penyelesaian modul absensi selfie, integrasi Drive PT, dan kalkulasi gaji otomatis.',
                    'target_value' => 100,
                    'unit_label' => 'persen',
                    'status' => 'active',
                ]
            );

            DivisionTarget::query()->updateOrCreate(
                [
                    'division_id' => $zaki->division_id,
                    'title' => 'Fondasi Database, Auth & Self-Attendance Minggu ke-3',
                    'start_date' => '2026-11-16',
                    'end_date' => '2026-11-20',
                ],
                [
                    'department_id' => $irfan->department_id,
                    'department_target_id' => $itMonthly->id,
                    'parent_division_target_id' => null,
                    'created_by' => $zaki->user_id,
                    'description' => 'Sprint mingguan fondasi database & absensi kamera selfie.',
                    'period_type' => TargetPeriodType::WEEKLY,
                    'target_value' => 5,
                    'approved_value' => 5,
                    'progress_percent' => 100.0,
                    'weight_percent' => 40.0,
                    'unit_label' => 'fitur',
                    'status' => 'completed',
                ]
            );

            $itWeeklyW4 = DivisionTarget::query()->updateOrCreate(
                [
                    'division_id' => $zaki->division_id,
                    'title' => 'Sprint Integrasi Payroll, Drive PT & Timeline Minggu ke-4',
                    'start_date' => '2026-11-23',
                    'end_date' => '2026-11-27',
                ],
                [
                    'department_id' => $irfan->department_id,
                    'department_target_id' => $itMonthly->id,
                    'parent_division_target_id' => null,
                    'created_by' => $zaki->user_id,
                    'description' => 'Sprint mingguan integrasi Drive PT, timeline, dan payroll.',
                    'period_type' => TargetPeriodType::WEEKLY,
                    'target_value' => 4,
                    'weight_percent' => 60.0,
                    'unit_label' => 'fitur',
                ]
            );

            if ($farhan !== null) {
                DailyWorkSubmission::query()->updateOrCreate(
                    ['employee_id' => $farhan->id, 'work_date' => '2026-11-24', 'title' => 'Implementasi API Integrasi Google Drive & Uji Upload'],
                    [
                        'division_target_id' => $itWeeklyW4->id,
                        'description' => 'Service Account OAuth2 JWT + upload multipart ke folder divisi.',
                        'contribution_value' => 2,
                        'drive_file_id' => 'demo-drive-it-101',
                        'drive_file_name' => 'DCI-IT-006_2026-11-24_Integrasi-Drive-PT.pdf',
                        'drive_web_view_link' => 'https://drive.google.com/drive/my-drive',
                        'drive_folder_path' => 'IT - Teknologi Informasi & Sistem Digital / IT-DEV - Pengembangan Software & ERP / 2026-11',
                        'mime_type' => 'application/pdf',
                        'status' => WorkSubmissionStatus::APPROVED,
                        'reviewed_by' => $zaki->user_id,
                        'reviewed_at' => CarbonImmutable::parse('2026-11-24 15:50:00', $tz),
                        'review_note' => 'Sudah dites dan cocok dengan standar keamanan PT.',
                        'created_at' => CarbonImmutable::parse('2026-11-24 14:15:00', $tz),
                    ]
                );
            }

            if ($nabila !== null) {
                DailyWorkSubmission::query()->updateOrCreate(
                    ['employee_id' => $nabila->id, 'work_date' => '2026-11-24', 'title' => 'Dokumen UAT Matriks Hak Akses HR & Payroll'],
                    [
                        'division_target_id' => $itWeeklyW4->id,
                        'description' => 'Checklist skenario testing untuk semua peran pengguna.',
                        'contribution_value' => 2,
                        'drive_file_id' => 'demo-drive-it-102',
                        'drive_file_name' => 'DCI-IT-007_2026-11-24_UAT-Matriks-Veloce.pdf',
                        'drive_web_view_link' => 'https://drive.google.com/drive/my-drive',
                        'drive_folder_path' => 'IT - Teknologi Informasi & Sistem Digital / IT-DEV - Pengembangan Software & ERP / 2026-11',
                        'mime_type' => 'application/pdf',
                        'status' => WorkSubmissionStatus::PENDING_REVIEW,
                        'created_at' => CarbonImmutable::parse('2026-11-24 16:30:00', $tz),
                    ]
                );
            }

            $itWeeklyW4->recalculateProgress();
        }

        if ($andi !== null && $dewi !== null && $fajar !== null) {
            $qalMonthly = DepartmentTarget::query()->updateOrCreate(
                [
                    'department_id' => $andi->department_id,
                    'year' => 2026,
                    'month' => 11,
                    'title' => 'Zero Defect Pengiriman Barang Jadi November 2026',
                ],
                [
                    'created_by' => $andi->user_id,
                    'description' => 'Target mutu bulanan Quality Control dan ketepatan muat Logistik.',
                    'target_value' => 20,
                    'unit_label' => 'inspeksi',
                    'status' => 'active',
                ]
            );

            $qcMonthly = DivisionTarget::query()->updateOrCreate(
                [
                    'division_id' => $dewi->division_id,
                    'title' => 'Inspeksi Tegangan & Isolasi Kabel Bulan November',
                    'start_date' => '2026-11-01',
                    'end_date' => '2026-11-30',
                ],
                [
                    'department_id' => $andi->department_id,
                    'department_target_id' => $qalMonthly->id,
                    'created_by' => $dewi->user_id,
                    'period_type' => TargetPeriodType::MONTHLY,
                    'target_value' => 10,
                    'unit_label' => 'inspeksi',
                ]
            );

            DailyWorkSubmission::query()->updateOrCreate(
                ['employee_id' => $fajar->id, 'work_date' => '2026-11-24', 'title' => 'Sertifikat Uji Tegangan Kabel Lot #880'],
                [
                    'division_target_id' => $qcMonthly->id,
                    'description' => 'Pengujian dielektrik 10 sampel acak lulus tanpa kebocoran arus.',
                    'contribution_value' => 4,
                    'drive_file_id' => 'demo-drive-qc-880',
                    'drive_file_name' => 'DCI-QAL-003_2026-11-24_QC-Lot-880.pdf',
                    'drive_web_view_link' => 'https://drive.google.com/drive/my-drive',
                    'drive_folder_path' => 'QAL - Quality & Logistik / QAL-QC - Quality Control / 2026-11',
                    'mime_type' => 'application/pdf',
                    'status' => WorkSubmissionStatus::APPROVED,
                    'reviewed_by' => $dewi->user_id,
                    'reviewed_at' => CarbonImmutable::parse('2026-11-24 15:10:00', $tz),
                    'review_note' => 'Lolos inspeksi, siap kirim ke pelanggan.',
                    'created_at' => CarbonImmutable::parse('2026-11-24 11:45:00', $tz),
                ]
            );

            $qcMonthly->recalculateProgress();
        }
    }

    private function hasNotificationForRequest(User $user, int $leaveRequestId): bool
    {
        return DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->where('data', 'like', '%"leave_request_id":'.$leaveRequestId.'%')
            ->exists();
    }
}
