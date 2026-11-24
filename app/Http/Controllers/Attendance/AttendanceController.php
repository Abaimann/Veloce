<?php

declare(strict_types=1);

namespace App\Http\Controllers\Attendance;

use App\Actions\Attendance\CheckInAction;
use App\Actions\Attendance\CheckOutAction;
use App\Enums\AttendanceStatus;
use App\Enums\LeaveRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\RecordAttendanceRequest;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\OfficeLocation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function camera(Request $request): View
    {
        $employee = $this->resolveAuthorizedEmployee($request);
        $employee->loadMissing(['workSchedule', 'department', 'division', 'user']);

        $timezone = (string) config('attendance.timezone', 'Asia/Jakarta');
        $nowWib = CarbonImmutable::now($timezone);
        $today = $nowWib->toDateString();

        $todayAttendance = Attendance::query()
            ->with('officeLocation')
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', $today)
            ->first();

        $officeLocations = OfficeLocation::query()
            ->active()
            ->orderBy('name')
            ->get();

        $hasApprovedWfh = $this->hasApprovedWfhToday($employee, $today, $todayAttendance);
        $enforceGeofenceOnCheckout = (bool) config('attendance.enforce_geofence_on_checkout', true);
        $allowNonWorkday = (bool) config('attendance.allow_non_workday_attendance', false);

        $blockedReason = null;

        if ($todayAttendance !== null && $todayAttendance->status->blocksSelfieCheckIn()) {
            $mode = 'blocked';
            $blockedReason = __('attendance.messages.blocked_by_leave', [
                'status' => $todayAttendance->status->label(),
            ]);
        } elseif ($todayAttendance !== null && $todayAttendance->check_in_at !== null && $todayAttendance->check_out_at !== null) {
            $mode = 'completed';
        } elseif ($todayAttendance !== null && $todayAttendance->check_in_at !== null) {
            if ($enforceGeofenceOnCheckout && ! $hasApprovedWfh && $officeLocations->isEmpty()) {
                $mode = 'blocked';
                $blockedReason = __('attendance.messages.no_active_office');
            } else {
                $mode = 'check_out';
            }
        } elseif (! $employee->is_active) {
            $mode = 'blocked';
            $blockedReason = 'Status kepegawaian Anda sedang nonaktif. Hubungi HR Manager.';
        } elseif (! $allowNonWorkday && ! $hasApprovedWfh && ! $this->isWorkday($employee, $nowWib)) {
            $mode = 'blocked';
            $blockedReason = __('attendance.messages.blocked_non_workday');
        } elseif (! $hasApprovedWfh && $officeLocations->isEmpty()) {
            $mode = 'blocked';
            $blockedReason = __('attendance.messages.no_active_office');
        } else {
            $mode = 'check_in';
        }

        $officesPayload = $officeLocations->map(static fn (OfficeLocation $office): array => [
            'id' => $office->id,
            'name' => $office->name,
            'lat' => $office->lat,
            'lng' => $office->lng,
            'radius_meters' => $office->radius_meters,
        ])->values()->all();

        return view('attendance.camera', [
            'employee' => $employee,
            'todayAttendance' => $todayAttendance,
            'officeLocations' => $officeLocations,
            'officesPayload' => $officesPayload,
            'hasApprovedWfh' => $hasApprovedWfh,
            'enforceGeofenceOnCheckout' => $enforceGeofenceOnCheckout,
            'mode' => $mode,
            'blockedReason' => $blockedReason,
            'serverNow' => $nowWib,
            'timezone' => $timezone,
            'maxAcceptableAccuracy' => (int) config('attendance.max_acceptable_accuracy_meters', 100),
            'selfieMaxWidth' => (int) config('attendance.selfie_max_width', 720),
            'selfieJpegQuality' => 0.72,
        ]);
    }

    public function checkIn(RecordAttendanceRequest $request, CheckInAction $action): JsonResponse|RedirectResponse
    {
        $timezone = (string) config('attendance.timezone', 'Asia/Jakarta');

        $attendance = $action->execute(
            $request->user()->employee,
            $request->file('photo'),
            (float) $request->validated('lat'),
            (float) $request->validated('lng'),
            (float) $request->validated('accuracy'),
            CarbonImmutable::parse((string) $request->validated('client_time'), $timezone),
        );

        $timeText = ($attendance->check_in_at ?? CarbonImmutable::now($timezone))
            ->setTimezone($timezone)
            ->format('H:i');

        $message = __('attendance.messages.check_in_success', ['time' => $timeText]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'redirect_url' => route('dashboard'),
                'attendance' => $this->formatAttendancePayload($attendance, $timezone),
            ], 201);
        }

        return redirect()
            ->route('dashboard')
            ->with('status', $message);
    }

    public function checkOut(RecordAttendanceRequest $request, CheckOutAction $action): JsonResponse|RedirectResponse
    {
        $timezone = (string) config('attendance.timezone', 'Asia/Jakarta');

        $attendance = $action->execute(
            $request->user()->employee,
            $request->file('photo'),
            (float) $request->validated('lat'),
            (float) $request->validated('lng'),
            (float) $request->validated('accuracy'),
            CarbonImmutable::parse((string) $request->validated('client_time'), $timezone),
        );

        $timeText = ($attendance->check_out_at ?? CarbonImmutable::now($timezone))
            ->setTimezone($timezone)
            ->format('H:i');

        $message = __('attendance.messages.check_out_success', ['time' => $timeText]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'redirect_url' => route('dashboard'),
                'attendance' => $this->formatAttendancePayload($attendance, $timezone),
            ], 200);
        }

        return redirect()
            ->route('dashboard')
            ->with('status', $message);
    }

    public function history(Request $request): View
    {
        $employee = $this->resolveAuthorizedEmployee($request);
        $employee->loadMissing(['user', 'department', 'division', 'workSchedule']);

        $timezone = (string) config('attendance.timezone', 'Asia/Jakarta');
        $nowWib = CarbonImmutable::now($timezone);
        $rawMonth = $request->query('month');

        $selectedMonthDate = $nowWib->startOfMonth();

        if (is_string($rawMonth) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $rawMonth) === 1) {
            $parsed = CarbonImmutable::createFromFormat('!Y-m', $rawMonth, $timezone);

            if ($parsed !== false) {
                $selectedMonthDate = $parsed->startOfMonth();
            }
        }

        $attendances = Attendance::query()
            ->with('officeLocation')
            ->where('employee_id', $employee->id)
            ->whereBetween('work_date', [
                $selectedMonthDate->startOfMonth()->toDateString(),
                $selectedMonthDate->endOfMonth()->toDateString(),
            ])
            ->orderByDesc('work_date')
            ->get();

        $summary = [
            'total' => $attendances->count(),
            'present' => $attendances->where('status', AttendanceStatus::PRESENT)->count(),
            'late' => $attendances->where('status', AttendanceStatus::LATE)->count(),
            'wfh' => $attendances->where('status', AttendanceStatus::WFH)->count(),
            'leave_total' => $attendances->whereIn('status', [
                AttendanceStatus::LEAVE,
                AttendanceStatus::SICK,
                AttendanceStatus::PERMISSION,
            ])->count(),
        ];

        return view('attendance.history', [
            'employee' => $employee,
            'attendances' => $attendances,
            'selectedMonth' => $selectedMonthDate->format('Y-m'),
            'selectedMonthLabel' => $selectedMonthDate->translatedFormat('F Y'),
            'summary' => $summary,
            'timezone' => $timezone,
        ]);
    }

    private function resolveAuthorizedEmployee(Request $request): Employee
    {
        $user = $request->user();
        $employee = $user?->employee;

        if ($user === null || $employee === null) {
            abort(403);
        }

        return $employee;
    }

    private function hasApprovedWfhToday(Employee $employee, string $today, ?Attendance $todayAttendance): bool
    {
        if ($todayAttendance?->status === AttendanceStatus::WFH) {
            return true;
        }

        return LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('status', LeaveRequestStatus::APPROVED->value)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->whereHas('leaveType', static function (Builder $query): void {
                $query->where('code', 'wfh')
                    ->orWhere('attendance_status', AttendanceStatus::WFH->value);
            })
            ->exists();
    }

    private function isWorkday(Employee $employee, CarbonImmutable $dateWib): bool
    {
        $isHoliday = Holiday::query()
            ->whereDate('date', $dateWib->toDateString())
            ->exists();

        if ($isHoliday) {
            return false;
        }

        $schedule = $employee->workSchedule;

        if ($schedule === null || ! $schedule->is_active) {
            return false;
        }

        return $schedule->isWorkday($dateWib->dayOfWeekIso);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatAttendancePayload(Attendance $attendance, string $timezone): array
    {
        return [
            'id' => $attendance->id,
            'work_date' => $attendance->work_date?->toDateString(),
            'status' => $attendance->status?->value,
            'status_label' => $attendance->status?->label(),
            'check_in_at' => $attendance->check_in_at?->setTimezone($timezone)->toIso8601String(),
            'check_out_at' => $attendance->check_out_at?->setTimezone($timezone)->toIso8601String(),
            'flags' => $attendance->flags ?? [],
        ];
    }
}
