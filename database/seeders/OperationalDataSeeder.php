<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ApprovalStatus;
use App\Enums\AttendanceStatus;
use App\Enums\LeaveRequestStatus;
use App\Enums\RoleName;
use App\Models\Attendance;
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
        });
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

        // 5. PENDING for Head PRD (Agus Prasetyo): Siti Aminah Cuti Tahunan (27 Nov 2026)
        if ($siti !== null && $annualLeave !== null && $agus !== null) {
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

            LeaveRequestApproval::query()->updateOrCreate(
                ['leave_request_id' => $req5->id, 'step' => 1],
                [
                    'role' => RoleName::DIVISION_HEAD->value,
                    'approver_id' => null,
                    'status' => ApprovalStatus::PENDING,
                    'note' => null,
                    'acted_at' => null,
                ]
            );

            LeaveRequestApproval::query()->updateOrCreate(
                ['leave_request_id' => $req5->id, 'step' => 2],
                [
                    'role' => RoleName::DEPT_MANAGER->value,
                    'approver_id' => null,
                    'status' => ApprovalStatus::PENDING,
                    'note' => null,
                    'acted_at' => null,
                ]
            );

            if ($agus->user !== null && ! $this->hasNotificationForRequest($agus->user, $req5->id)) {
                $agus->user->notify(new LeaveSubmittedForApprovalNotification($req5, 1));
            }
        }

        // 6. PENDING for Head MNT (Hendra Wijaya): Rudi Hartono Izin Keperluan Pribadi (26 Nov 2026)
        if ($rudi !== null && $permissionLeave !== null && $hendra !== null) {
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

            LeaveRequestApproval::query()->updateOrCreate(
                ['leave_request_id' => $req6->id, 'step' => 1],
                [
                    'role' => RoleName::DIVISION_HEAD->value,
                    'approver_id' => null,
                    'status' => ApprovalStatus::PENDING,
                    'note' => null,
                    'acted_at' => null,
                ]
            );

            LeaveRequestApproval::query()->updateOrCreate(
                ['leave_request_id' => $req6->id, 'step' => 2],
                [
                    'role' => RoleName::DEPT_MANAGER->value,
                    'approver_id' => null,
                    'status' => ApprovalStatus::PENDING,
                    'note' => null,
                    'acted_at' => null,
                ]
            );

            if ($hendra->user !== null && ! $this->hasNotificationForRequest($hendra->user, $req6->id)) {
                $hendra->user->notify(new LeaveSubmittedForApprovalNotification($req6, 1));
            }
        }

        // 7. PENDING Step 2 for Dept Mgr QAL (Andi Kurniawan): Fajar Nugroho Cuti Tahunan 4 Hari (01 - 04 Des 2026)
        if ($fajar !== null && $annualLeave !== null && $dewi !== null && $andi !== null) {
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
                    'current_step' => 2,
                    'created_at' => CarbonImmutable::parse('2026-11-23 14:00:00', $tz),
                    'updated_at' => CarbonImmutable::parse('2026-11-24 09:30:00', $tz),
                ]
            );

            LeaveRequestApproval::query()->updateOrCreate(
                ['leave_request_id' => $req7->id, 'step' => 1],
                [
                    'role' => RoleName::DIVISION_HEAD->value,
                    'approver_id' => $dewi->user_id,
                    'status' => ApprovalStatus::APPROVED,
                    'note' => 'Disetujui. Sampling inspeksi QC sudah diatur bersama tim.',
                    'acted_at' => CarbonImmutable::parse('2026-11-24 09:30:00', $tz),
                ]
            );

            LeaveRequestApproval::query()->updateOrCreate(
                ['leave_request_id' => $req7->id, 'step' => 2],
                [
                    'role' => RoleName::DEPT_MANAGER->value,
                    'approver_id' => null,
                    'status' => ApprovalStatus::PENDING,
                    'note' => null,
                    'acted_at' => null,
                ]
            );

            LeaveRequestApproval::query()->updateOrCreate(
                ['leave_request_id' => $req7->id, 'step' => 3],
                [
                    'role' => RoleName::HR_MANAGER->value,
                    'approver_id' => null,
                    'status' => ApprovalStatus::PENDING,
                    'note' => null,
                    'acted_at' => null,
                ]
            );

            if ($andi->user !== null && ! $this->hasNotificationForRequest($andi->user, $req7->id)) {
                $andi->user->notify(new LeaveSubmittedForApprovalNotification($req7, 2));
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

    private function hasNotificationForRequest(User $user, int $leaveRequestId): bool
    {
        return DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->where('data', 'like', '%"leave_request_id":'.$leaveRequestId.'%')
            ->exists();
    }
}
