<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ApprovalStatus;
use App\Enums\LeaveRequestStatus;
use App\Enums\RoleName;
use App\Models\LeaveRequest;
use App\Models\User;

class LeaveRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return ! $user->hasRole(RoleName::PAYROLL->value)
            && ($user->employee !== null || $user->hasAnyRole([
                RoleName::HR_MANAGER->value,
                RoleName::SUPER_ADMIN->value,
            ]));
    }

    public function view(User $user, LeaveRequest $leaveRequest): bool
    {
        $targetEmployee = $leaveRequest->employee;

        if ($targetEmployee !== null && $targetEmployee->user_id === $user->id) {
            return true;
        }

        if ($user->hasRole(RoleName::HR_MANAGER->value)) {
            return true;
        }

        if ($user->hasRole(RoleName::SUPER_ADMIN->value)) {
            $hasSuperAdminStep = $leaveRequest->relationLoaded('approvals')
                ? $leaveRequest->approvals->contains('role', RoleName::SUPER_ADMIN->value)
                : $leaveRequest->approvals()->where('role', RoleName::SUPER_ADMIN->value)->exists();

            $applicantIsHrManager = $targetEmployee?->user?->hasRole(RoleName::HR_MANAGER->value) ?? false;

            return $hasSuperAdminStep || $applicantIsHrManager;
        }

        $viewerEmployee = $user->employee;

        if ($targetEmployee === null || $viewerEmployee === null) {
            return false;
        }

        if ($user->hasRole(RoleName::DEPT_MANAGER->value)) {
            return $viewerEmployee->department_id === $targetEmployee->department_id;
        }

        if ($user->hasRole(RoleName::DIVISION_HEAD->value)) {
            return $viewerEmployee->division_id !== null
                && $viewerEmployee->division_id === $targetEmployee->division_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->employee !== null && ! $user->hasRole(RoleName::PAYROLL->value);
    }

    public function approve(User $user, LeaveRequest $leaveRequest): bool
    {
        if ($leaveRequest->status !== LeaveRequestStatus::PENDING) {
            return false;
        }

        $targetEmployee = $leaveRequest->employee;

        if ($targetEmployee === null || $targetEmployee->user_id === $user->id) {
            return false;
        }

        $step = $leaveRequest->currentApproval();

        if ($step === null || $step->status !== ApprovalStatus::PENDING) {
            return false;
        }

        return match ($step->role) {
            RoleName::SUPER_ADMIN->value => $user->hasRole(RoleName::SUPER_ADMIN->value),
            RoleName::HR_MANAGER->value => $user->hasRole(RoleName::HR_MANAGER->value),
            RoleName::DEPT_MANAGER->value => $user->hasRole(RoleName::DEPT_MANAGER->value)
                && $user->employee !== null
                && $user->employee->department_id === $targetEmployee->department_id,
            RoleName::DIVISION_HEAD->value => $user->hasRole(RoleName::DIVISION_HEAD->value)
                && $user->employee !== null
                && $user->employee->division_id !== null
                && $user->employee->division_id === $targetEmployee->division_id,
            default => false,
        };
    }

    public function cancel(User $user, LeaveRequest $leaveRequest): bool
    {
        return $leaveRequest->employee?->user_id === $user->id
            && $leaveRequest->status === LeaveRequestStatus::PENDING;
    }

    public function viewAttachment(User $user, LeaveRequest $leaveRequest): bool
    {
        return $this->view($user, $leaveRequest);
    }
}
