<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ApprovalStatus;
use App\Enums\LeaveRequestStatus;
use App\Enums\RoleName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable([
    'employee_id',
    'leave_type_id',
    'start_date',
    'end_date',
    'total_work_days',
    'reason',
    'attachment_path',
    'status',
    'current_step',
    'finalized_at',
    'cancelled_at',
])]
class LeaveRequest extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'current_step', 'start_date', 'end_date', 'total_work_days'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'immutable_date',
            'end_date' => 'immutable_date',
            'total_work_days' => 'integer',
            'status' => LeaveRequestStatus::class,
            'current_step' => 'integer',
            'finalized_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
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
     * @return BelongsTo<LeaveType, $this>
     */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    /**
     * @return HasMany<LeaveRequestApproval, $this>
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(LeaveRequestApproval::class)->orderBy('step');
    }

    public function currentApproval(): ?LeaveRequestApproval
    {
        if ($this->relationLoaded('approvals')) {
            return $this->approvals->firstWhere('step', $this->current_step);
        }

        return $this->approvals()->where('step', $this->current_step)->first();
    }

    /**
     * @param  Builder<LeaveRequest>  $query
     * @return Builder<LeaveRequest>
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

    /**
     * Mengambil pengajuan yang saat ini menunggu tindakan persetujuan dari $approver.
     *
     * @param  Builder<LeaveRequest>  $query
     * @return Builder<LeaveRequest>
     */
    public function scopePendingForApprover(Builder $query, User $approver): Builder
    {
        $role = $approver->primaryRole();

        if ($role === null || ! $role->isApprover()) {
            return $query->whereRaw('1 = 0');
        }

        $approverEmployee = $approver->employee;

        if (in_array($role, [RoleName::DIVISION_HEAD, RoleName::DEPT_MANAGER], true) && $approverEmployee === null) {
            return $query->whereRaw('1 = 0');
        }

        if ($role === RoleName::DIVISION_HEAD && $approverEmployee->division_id === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->where('leave_requests.status', LeaveRequestStatus::PENDING->value)
            ->whereHas('employee', function (Builder $employeeQuery) use ($approver, $role, $approverEmployee): void {
                $employeeQuery->where('user_id', '!=', $approver->id);

                if ($role === RoleName::DIVISION_HEAD && $approverEmployee !== null) {
                    $employeeQuery->where('division_id', $approverEmployee->division_id);
                } elseif ($role === RoleName::DEPT_MANAGER && $approverEmployee !== null) {
                    $employeeQuery->where('department_id', $approverEmployee->department_id);
                }
            })
            ->whereHas('approvals', function (Builder $approvalQuery) use ($role): void {
                $approvalQuery
                    ->whereColumn('leave_request_approvals.step', 'leave_requests.current_step')
                    ->where('leave_request_approvals.role', $role->value)
                    ->where('leave_request_approvals.status', ApprovalStatus::PENDING->value);
            });
    }
}
