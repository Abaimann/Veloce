<?php

declare(strict_types=1);

namespace App\Http\Controllers\Management;

use App\Enums\AttendanceStatus;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Department;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TeamAttendanceController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        if ($user === null || ! $user->hasAnyRole([
            RoleName::DIVISION_HEAD->value,
            RoleName::DEPT_MANAGER->value,
            RoleName::HR_MANAGER->value,
            RoleName::SUPER_ADMIN->value,
        ])) {
            abort(403);
        }

        $user->loadMissing('employee');

        $timezone = (string) config('attendance.timezone', 'Asia/Jakarta');
        $todayDate = CarbonImmutable::now($timezone)->toDateString();

        $rawDate = $request->query('date');
        if ($rawDate === 'all') {
            $selectedDate = 'all';
            $dateFilter = null;
        } elseif (is_string($rawDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDate) === 1) {
            $parsedDate = CarbonImmutable::createFromFormat('!Y-m-d', $rawDate, $timezone);
            $isValidDate = $parsedDate !== false && $parsedDate->format('Y-m-d') === $rawDate;

            $selectedDate = $isValidDate ? $rawDate : $todayDate;
            $dateFilter = $selectedDate;
        } else {
            $selectedDate = $todayDate;
            $dateFilter = $todayDate;
        }

        $rawStatus = $request->query('status');
        $selectedStatus = is_string($rawStatus) && $rawStatus !== ''
            ? AttendanceStatus::tryFrom($rawStatus)
            : null;

        $canFilterDepartment = $user->hasAnyRole([
            RoleName::HR_MANAGER->value,
            RoleName::SUPER_ADMIN->value,
        ]);

        $rawDepartmentId = $request->query('department_id');
        $selectedDepartmentId = $canFilterDepartment
            && is_scalar($rawDepartmentId)
            && ctype_digit((string) $rawDepartmentId)
            && (int) $rawDepartmentId > 0
            ? (int) $rawDepartmentId
            : null;

        $flaggedOnly = $request->boolean('flagged_only');

        $attendances = Attendance::query()
            ->visibleTo($user)
            ->with(['employee.user', 'employee.department', 'employee.division', 'officeLocation'])
            ->when(
                $dateFilter !== null,
                static fn (Builder $query): Builder => $query->whereDate('work_date', $dateFilter),
            )
            ->when(
                $selectedStatus !== null,
                static fn (Builder $query): Builder => $query->where('status', $selectedStatus->value),
            )
            ->when(
                $selectedDepartmentId !== null,
                static function (Builder $query) use ($selectedDepartmentId): void {
                    $query->whereHas('employee', static function (Builder $employeeQuery) use ($selectedDepartmentId): void {
                        $employeeQuery->where('department_id', $selectedDepartmentId);
                    });
                },
            )
            ->when($flaggedOnly, static function (Builder $query): void {
                $driver = DB::getDriverName();

                if ($driver === 'pgsql') {
                    $query->whereRaw("jsonb_array_length(COALESCE(flags, '[]'::jsonb)) > 0");
                } elseif ($driver === 'sqlite') {
                    $query->whereRaw("json_array_length(COALESCE(NULLIF(flags, ''), '[]')) > 0");
                } else {
                    $query->whereRaw("JSON_LENGTH(COALESCE(flags, '[]')) > 0");
                }
            })
            ->orderByDesc('work_date')
            ->orderByDesc('check_in_at')
            ->paginate(15)
            ->withQueryString();

        $departments = $canFilterDepartment
            ? Department::query()->active()->orderBy('name')->get(['id', 'code', 'name'])
            : collect();

        return view('management.attendances.index', [
            'attendances' => $attendances,
            'selectedDate' => $selectedDate,
            'todayDate' => $todayDate,
            'selectedStatus' => $selectedStatus,
            'selectedDepartmentId' => $selectedDepartmentId,
            'flaggedOnly' => $flaggedOnly,
            'canFilterDepartment' => $canFilterDepartment,
            'departments' => $departments,
            'statuses' => AttendanceStatus::cases(),
            'timezone' => $timezone,
        ]);
    }
}
