<?php

declare(strict_types=1);

namespace App\Actions\Payroll;

use App\Enums\AttendanceStatus;
use App\Enums\PayrollPeriodStatus;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Support\Attendance\WorkdayCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class CalculateMonthlyPayrollAction
{
    public function __construct(
        private readonly WorkdayCalculator $workdayCalculator,
    ) {}

    /**
     * Menghitung gaji seluruh karyawan aktif untuk periode bulan tertentu berdasarkan data absensi.
     */
    public function execute(int $year, int $month, ?User $actor = null): PayrollPeriod
    {
        $timezone = (string) config('attendance.timezone', 'Asia/Jakarta');
        $nowWib = CarbonImmutable::now($timezone);
        $periodStart = CarbonImmutable::create($year, $month, 1, 0, 0, 0, $timezone)->startOfDay();
        $periodEnd = $periodStart->endOfMonth()->startOfDay();

        // Untuk bulan berjalan, batas evaluasi alpha hanya sampai hari ini agar hari mendatang belum dihitung alpha.
        $evaluationEnd = $periodEnd->gt($nowWib->startOfDay())
            ? $nowWib->startOfDay()
            : $periodEnd;

        $lateDeductionRate = (int) config('attendance.payroll.late_deduction_per_incident', 50000);

        return DB::transaction(function () use (
            $year,
            $month,
            $periodStart,
            $periodEnd,
            $evaluationEnd,
            $lateDeductionRate,
            $actor,
            $nowWib,
            $timezone,
        ): PayrollPeriod {
            $period = PayrollPeriod::query()->updateOrCreate(
                [
                    'year' => $year,
                    'month' => $month,
                ],
                [
                    'label' => $periodStart->translatedFormat('F Y'),
                    'status' => PayrollPeriodStatus::DRAFT,
                    'generated_by' => $actor?->id,
                    'generated_at' => $nowWib,
                ],
            );

            $employees = Employee::query()
                ->active()
                ->with(['user', 'workSchedule', 'department', 'division'])
                ->orderBy('employee_number')
                ->get();

            $periodStartDate = $periodStart->toDateString();
            $periodEndDate = $periodEnd->toDateString();
            $holidayDates = WorkdayCalculator::holidayLookupMap($periodStart, $periodEnd);

            $attendancesByEmployee = Attendance::query()
                ->with('leaveRequest.leaveType')
                ->whereBetween('work_date', [$periodStartDate, $periodEndDate])
                ->get()
                ->groupBy('employee_id');

            foreach ($employees as $employee) {
                $workdaysInMonth = max(1, $this->workdayCalculator->countWorkdays($employee, $periodStart, $periodEnd, $holidayDates));

                $effectiveStart = $employee->join_date !== null && $employee->join_date->gt($periodStart)
                    ? $employee->join_date->startOfDay()
                    : $periodStart;

                $evaluatedWorkdays = $evaluationEnd->gte($effectiveStart)
                    ? $this->workdayCalculator->countWorkdays($employee, $effectiveStart, $evaluationEnd, $holidayDates)
                    : 0;

                $records = $attendancesByEmployee->get($employee->id, collect());

                $presentOnTime = $records->where('status', AttendanceStatus::PRESENT)->count();
                $lateRecords = $records->where('status', AttendanceStatus::LATE);
                $lateDays = $lateRecords->count();
                $wfhDays = $records->where('status', AttendanceStatus::WFH)->count();

                $paidLeaveDays = 0;
                $unpaidLeaveDays = 0;

                foreach ($records as $record) {
                    if (in_array($record->status, [AttendanceStatus::LEAVE, AttendanceStatus::SICK, AttendanceStatus::PERMISSION], true)) {
                        $isPaid = $record->leaveRequest?->leaveType?->is_paid ?? true;
                        if ($isPaid) {
                            $paidLeaveDays++;
                        } else {
                            $unpaidLeaveDays++;
                        }
                    }
                }

                $lateMinutesTotal = 0;
                $schedule = $employee->workSchedule;
                if ($schedule !== null) {
                    foreach ($lateRecords as $lateRecord) {
                        if ($lateRecord->check_in_at !== null && $lateRecord->work_date !== null) {
                            $deadline = CarbonImmutable::parse(
                                $lateRecord->work_date->toDateString().' '.$schedule->check_in_time,
                                $timezone,
                            )->addMinutes((int) $schedule->late_tolerance_minutes);

                            $checkInWib = $lateRecord->check_in_at->setTimezone($timezone);
                            if ($checkInWib->gt($deadline)) {
                                $lateMinutesTotal += (int) $deadline->diffInMinutes($checkInWib);
                            }
                        }
                    }
                }

                $presentTotalDays = $presentOnTime + $lateDays + $wfhDays;
                $accountedDays = $presentTotalDays + $paidLeaveDays + $unpaidLeaveDays;
                $absentDays = max(0, $evaluatedWorkdays - $accountedDays);

                $baseSalary = (int) $employee->base_salary;
                $fixedAllowance = (int) $employee->fixed_allowance;
                $grossSalary = $baseSalary + $fixedAllowance;

                $dailyRate = (int) round($grossSalary / $workdaysInMonth);
                $lateDeduction = $lateDays * $lateDeductionRate;
                $absenceDeduction = ($unpaidLeaveDays + $absentDays) * $dailyRate;
                $netSalary = max(0, $grossSalary - $lateDeduction - $absenceDeduction);

                PayrollItem::query()->updateOrCreate(
                    [
                        'payroll_period_id' => $period->id,
                        'employee_id' => $employee->id,
                    ],
                    [
                        'workdays_in_month' => $workdaysInMonth,
                        'present_days' => $presentTotalDays,
                        'late_days' => $lateDays,
                        'late_minutes_total' => $lateMinutesTotal,
                        'wfh_days' => $wfhDays,
                        'paid_leave_days' => $paidLeaveDays,
                        'unpaid_leave_days' => $unpaidLeaveDays,
                        'absent_days' => $absentDays,
                        'base_salary' => $baseSalary,
                        'fixed_allowance' => $fixedAllowance,
                        'late_deduction' => $lateDeduction,
                        'absence_deduction' => $absenceDeduction,
                        'net_salary' => $netSalary,
                        'calculation_breakdown' => [
                            'gross_salary' => $grossSalary,
                            'daily_rate' => $dailyRate,
                            'late_deduction_per_incident' => $lateDeductionRate,
                            'evaluated_workdays_until' => $evaluationEnd->toDateString(),
                            'evaluated_workdays_count' => $evaluatedWorkdays,
                        ],
                    ],
                );
            }

            return $period->fresh(['items.employee.user', 'items.employee.department', 'items.employee.division']);
        });
    }
}
