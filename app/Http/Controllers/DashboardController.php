<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Enums\EmploymentType;
use App\Enums\LeaveRequestStatus;
use App\Enums\RoleName;
use App\Enums\WorkSubmissionStatus;
use App\Models\Attendance;
use App\Models\DailyWorkSubmission;
use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\OfficeLocation;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $user->loadMissing([
            'roles',
            'employee.department',
            'employee.division',
            'employee.workSchedule',
        ]);

        $timezone = (string) config('attendance.timezone', 'Asia/Jakarta');
        $nowWib = CarbonImmutable::now($timezone);
        $unreadNotificationsCount = $user->unreadNotifications()->count();

        if ($user->hasRole(RoleName::PAYROLL->value)) {
            return $this->payrollDashboard($user, $nowWib, $unreadNotificationsCount);
        }

        if ($user->hasRole(RoleName::SUPER_ADMIN->value)) {
            return $this->adminDashboard($user, $nowWib, $unreadNotificationsCount);
        }

        return $this->employeeDashboard($user, $nowWib, $timezone, $unreadNotificationsCount);
    }

    private function payrollDashboard(User $user, CarbonImmutable $nowWib, int $unreadNotificationsCount): View
    {
        $monthStart = $nowWib->startOfMonth()->toDateString();
        $monthEnd = $nowWib->endOfMonth()->toDateString();

        $employeeCountsByType = Employee::query()
            ->active()
            ->selectRaw('employment_type, COUNT(*) as total')
            ->groupBy('employment_type')
            ->get()
            ->mapWithKeys(static function (Employee $row): array {
                $typeKey = $row->employment_type instanceof EmploymentType
                    ? $row->employment_type->value
                    : (string) $row->employment_type;

                return [$typeKey => (int) $row->getAttribute('total')];
            });

        $employmentCounts = [];
        foreach (EmploymentType::cases() as $employmentType) {
            $employmentCounts[$employmentType->value] = (int) ($employeeCountsByType[$employmentType->value] ?? 0);
        }

        $attendanceRawCounts = Attendance::query()
            ->whereBetween('work_date', [$monthStart, $monthEnd])
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(static function (Attendance $row): array {
                $statusKey = $row->status instanceof AttendanceStatus
                    ? $row->status->value
                    : (string) $row->status;

                return [$statusKey => (int) $row->getAttribute('total')];
            });

        $monthlyAttendanceSummary = [];
        foreach (AttendanceStatus::cases() as $status) {
            $monthlyAttendanceSummary[$status->value] = (int) ($attendanceRawCounts[$status->value] ?? 0);
        }

        $todayAttendance = $user->employee !== null
            ? Attendance::query()
                ->with('officeLocation')
                ->where('employee_id', $user->employee->id)
                ->whereDate('work_date', $nowWib->toDateString())
                ->first()
            : null;

        $payrollPeriod = PayrollPeriod::query()->where('year', $nowWib->year)->where('month', $nowWib->month)->first();
        $payrollTotalNet = $payrollPeriod !== null
            ? (int) PayrollItem::query()->where('payroll_period_id', $payrollPeriod->id)->sum('net_salary')
            : null;

        return view('dashboard.payroll', [
            'user' => $user,
            'employee' => $user->employee,
            'todayAttendance' => $todayAttendance,
            'nowWib' => $nowWib,
            'monthLabel' => $nowWib->translatedFormat('F Y'),
            'employmentCounts' => $employmentCounts,
            'employmentTypes' => EmploymentType::cases(),
            'totalActiveEmployees' => array_sum($employmentCounts),
            'activeDepartmentsCount' => Department::query()->active()->count(),
            'activeDivisionsCount' => Division::query()->active()->count(),
            'monthlyAttendanceSummary' => $monthlyAttendanceSummary,
            'attendanceStatuses' => AttendanceStatus::cases(),
            'totalMonthlyAttendanceRecords' => array_sum($monthlyAttendanceSummary),
            'payrollPeriod' => $payrollPeriod,
            'payrollTotalNet' => $payrollTotalNet,
            'pendingApprovalsCount' => 0,
            'unreadNotificationsCount' => $unreadNotificationsCount,
        ]);
    }

    private function adminDashboard(User $user, CarbonImmutable $nowWib, int $unreadNotificationsCount): View
    {
        $pendingInvitationsCount = User::query()
            ->where('is_active', true)
            ->where(static function (Builder $query): void {
                $query->whereNull('password')
                    ->orWhere('password', '');
            })
            ->count();

        $pendingHrApprovals = LeaveRequest::pendingForApprover($user)->count();
        $todayAttendance = $user->employee !== null
            ? Attendance::query()
                ->with('officeLocation')
                ->where('employee_id', $user->employee->id)
                ->whereDate('work_date', $nowWib->toDateString())
                ->first()
            : null;

        return view('dashboard.admin', [
            'user' => $user,
            'employee' => $user->employee,
            'todayAttendance' => $todayAttendance,
            'nowWib' => $nowWib,
            'activeDepartmentsCount' => Department::query()->active()->count(),
            'activeDivisionsCount' => Division::query()->active()->count(),
            'activeOfficesCount' => OfficeLocation::query()->active()->count(),
            'activeSchedulesCount' => WorkSchedule::query()->active()->count(),
            'activeEmployeesCount' => Employee::query()->active()->count(),
            'pendingInvitationsCount' => $pendingInvitationsCount,
            'pendingHrApprovals' => $pendingHrApprovals,
            'unreadNotificationsCount' => $unreadNotificationsCount,
        ]);
    }

    private function employeeDashboard(
        User $user,
        CarbonImmutable $nowWib,
        string $timezone,
        int $unreadNotificationsCount,
    ): View {
        $employee = $user->employee;
        $todayDate = $nowWib->toDateString();

        $isApprover = $user->primaryRole()?->isApprover() ?? false;
        $pendingApprovalsCount = $isApprover
            ? LeaveRequest::pendingForApprover($user)->count()
            : 0;

        $todayAttendance = null;
        $hasApprovedWfh = false;
        $approvedBlockingLeave = null;
        $leaveBalance = null;
        $remainingLeaveDays = 0;

        $monthlySummary = [
            'hadir' => 0,
            'present' => 0,
            'wfh' => 0,
            'terlambat' => 0,
            'izin_cuti' => 0,
            'leave' => 0,
            'permission' => 0,
            'sakit' => 0,
        ];

        if ($employee !== null) {
            $todayAttendance = Attendance::query()
                ->with('officeLocation')
                ->where('employee_id', $employee->id)
                ->whereDate('work_date', $todayDate)
                ->first();

            $hasApprovedWfh = $todayAttendance?->status === AttendanceStatus::WFH
                || LeaveRequest::query()
                    ->where('employee_id', $employee->id)
                    ->where('status', LeaveRequestStatus::APPROVED->value)
                    ->whereDate('start_date', '<=', $todayDate)
                    ->whereDate('end_date', '>=', $todayDate)
                    ->whereHas('leaveType', static function (Builder $query): void {
                        $query->where('code', 'wfh')
                            ->orWhere('attendance_status', AttendanceStatus::WFH->value);
                    })
                    ->exists();

            if ($todayAttendance === null) {
                $approvedBlockingLeave = LeaveRequest::query()
                    ->with('leaveType')
                    ->where('employee_id', $employee->id)
                    ->where('status', LeaveRequestStatus::APPROVED->value)
                    ->whereDate('start_date', '<=', $todayDate)
                    ->whereDate('end_date', '>=', $todayDate)
                    ->whereHas('leaveType', static function (Builder $query): void {
                        $query->where('code', '!=', 'wfh')
                            ->whereIn('attendance_status', [
                                AttendanceStatus::LEAVE->value,
                                AttendanceStatus::SICK->value,
                                AttendanceStatus::PERMISSION->value,
                            ]);
                    })
                    ->first();
            }

            $monthlyRawCounts = Attendance::query()
                ->where('employee_id', $employee->id)
                ->whereBetween('work_date', [$nowWib->startOfMonth()->toDateString(), $nowWib->endOfMonth()->toDateString()])
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->get()
                ->mapWithKeys(static function (Attendance $row): array {
                    $statusKey = $row->status instanceof AttendanceStatus
                        ? $row->status->value
                        : (string) $row->status;

                    return [$statusKey => (int) $row->getAttribute('total')];
                });

            $presentCount = (int) ($monthlyRawCounts[AttendanceStatus::PRESENT->value] ?? 0);
            $wfhCount = (int) ($monthlyRawCounts[AttendanceStatus::WFH->value] ?? 0);
            $lateCount = (int) ($monthlyRawCounts[AttendanceStatus::LATE->value] ?? 0);
            $leaveCount = (int) ($monthlyRawCounts[AttendanceStatus::LEAVE->value] ?? 0);
            $permissionCount = (int) ($monthlyRawCounts[AttendanceStatus::PERMISSION->value] ?? 0);
            $sickCount = (int) ($monthlyRawCounts[AttendanceStatus::SICK->value] ?? 0);

            $monthlySummary = [
                'hadir' => $presentCount + $wfhCount,
                'present' => $presentCount,
                'wfh' => $wfhCount,
                'terlambat' => $lateCount,
                'izin_cuti' => $leaveCount + $permissionCount,
                'leave' => $leaveCount,
                'permission' => $permissionCount,
                'sakit' => $sickCount,
            ];

            $leaveBalance = LeaveBalance::query()
                ->where('employee_id', $employee->id)
                ->where('year', $nowWib->year)
                ->first();

            $remainingLeaveDays = $leaveBalance?->remainingDays() ?? 0;
        }

        $pendingWorkReviewsCount = 0;
        if ($employee !== null) {
            if ($user->hasRole(RoleName::DIVISION_HEAD->value) && $employee->division_id !== null) {
                $pendingWorkReviewsCount = DailyWorkSubmission::query()
                    ->where('status', WorkSubmissionStatus::PENDING_REVIEW->value)
                    ->where('employee_id', '!=', $employee->id)
                    ->whereHas('employee', static fn (Builder $q): Builder => $q->where('division_id', $employee->division_id))
                    ->count();
            } elseif ($user->hasRole(RoleName::DEPT_MANAGER->value)) {
                $pendingWorkReviewsCount = DailyWorkSubmission::query()
                    ->where('status', WorkSubmissionStatus::PENDING_REVIEW->value)
                    ->where('employee_id', '!=', $employee->id)
                    ->whereHas('employee', static fn (Builder $q): Builder => $q->where('department_id', $employee->department_id))
                    ->count();
            }
        }

        return view('dashboard.employee', [
            'user' => $user,
            'employee' => $employee,
            'nowWib' => $nowWib,
            'timezone' => $timezone,
            'monthLabel' => $nowWib->translatedFormat('F Y'),
            'todayAttendance' => $todayAttendance,
            'hasApprovedWfh' => $hasApprovedWfh,
            'approvedBlockingLeave' => $approvedBlockingLeave,
            'monthlySummary' => $monthlySummary,
            'leaveBalance' => $leaveBalance,
            'remainingLeaveDays' => $remainingLeaveDays,
            'isApprover' => $isApprover,
            'pendingApprovalsCount' => $pendingApprovalsCount,
            'pendingWorkReviewsCount' => $pendingWorkReviewsCount,
            'unreadNotificationsCount' => $unreadNotificationsCount,
        ]);
    }
}
