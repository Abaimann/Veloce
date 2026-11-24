<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Enums\RoleName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable([
    'employee_id',
    'work_date',
    'check_in_at',
    'check_out_at',
    'check_in_lat',
    'check_in_lng',
    'check_in_accuracy',
    'check_in_distance_meters',
    'check_out_lat',
    'check_out_lng',
    'check_out_accuracy',
    'check_out_distance_meters',
    'office_location_id',
    'selfie_in_path',
    'selfie_out_path',
    'selfie_in_hash',
    'selfie_out_hash',
    'client_check_in_at',
    'client_check_out_at',
    'status',
    'leave_request_id',
    'flags',
    'selfie_pruned_at',
])]
class Attendance extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'work_date', 'check_in_at', 'check_out_at', 'flags', 'leave_request_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'work_date' => 'immutable_date',
            'check_in_at' => 'immutable_datetime',
            'check_out_at' => 'immutable_datetime',
            'client_check_in_at' => 'immutable_datetime',
            'client_check_out_at' => 'immutable_datetime',
            'selfie_pruned_at' => 'immutable_datetime',
            'check_in_lat' => 'float',
            'check_in_lng' => 'float',
            'check_in_accuracy' => 'float',
            'check_in_distance_meters' => 'float',
            'check_out_lat' => 'float',
            'check_out_lng' => 'float',
            'check_out_accuracy' => 'float',
            'check_out_distance_meters' => 'float',
            'status' => AttendanceStatus::class,
            'flags' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<OfficeLocation, $this>
     */
    public function officeLocation(): BelongsTo
    {
        return $this->belongsTo(OfficeLocation::class);
    }

    /**
     * @return BelongsTo<LeaveRequest, $this>
     */
    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    /**
     * @param  Builder<Attendance>  $query
     * @return Builder<Attendance>
     */
    public function scopeVisibleTo(Builder $query, User $viewer): Builder
    {
        if ($viewer->hasAnyRole([RoleName::SUPER_ADMIN->value, RoleName::HR_MANAGER->value])) {
            return $query;
        }

        $viewerEmployee = $viewer->employee;

        if ($viewerEmployee === null) {
            return $query->whereRaw('1 = 0');
        }

        if ($viewer->hasRole(RoleName::DEPT_MANAGER->value)) {
            return $query->whereHas('employee', function (Builder $employeeQuery) use ($viewerEmployee): void {
                $employeeQuery->where('department_id', $viewerEmployee->department_id);
            });
        }

        if ($viewer->hasRole(RoleName::DIVISION_HEAD->value)) {
            return $query->whereHas('employee', function (Builder $employeeQuery) use ($viewerEmployee): void {
                $employeeQuery->where(function (Builder $sub) use ($viewerEmployee): void {
                    if ($viewerEmployee->division_id !== null) {
                        $sub->where('division_id', $viewerEmployee->division_id);
                    }

                    $sub->orWhereKey($viewerEmployee->id);
                });
            });
        }

        if ($viewer->hasRole(RoleName::EMPLOYEE->value)) {
            return $query->where('employee_id', $viewerEmployee->id);
        }

        return $query->whereRaw('1 = 0');
    }

    public function hasSuspiciousFlags(): bool
    {
        return count($this->flags ?? []) > 0;
    }
}
