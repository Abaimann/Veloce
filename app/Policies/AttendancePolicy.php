<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Attendance;
use App\Models\User;

class AttendancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([
            RoleName::SUPER_ADMIN->value,
            RoleName::HR_MANAGER->value,
            RoleName::PAYROLL->value,
            RoleName::DEPT_MANAGER->value,
            RoleName::DIVISION_HEAD->value,
            RoleName::EMPLOYEE->value,
        ]);
    }

    public function view(User $user, Attendance $attendance): bool
    {
        if ($user->hasAnyRole([RoleName::SUPER_ADMIN->value, RoleName::HR_MANAGER->value])) {
            return true;
        }

        $targetEmployee = $attendance->employee;
        $viewerEmployee = $user->employee;

        if ($targetEmployee === null || $viewerEmployee === null) {
            return false;
        }

        if ($user->hasRole(RoleName::PAYROLL->value)) {
            return $targetEmployee->id === $viewerEmployee->id;
        }

        if ($user->hasRole(RoleName::DEPT_MANAGER->value)) {
            return $viewerEmployee->department_id === $targetEmployee->department_id;
        }

        if ($user->hasRole(RoleName::DIVISION_HEAD->value)) {
            if ($targetEmployee->id === $viewerEmployee->id) {
                return true;
            }

            return $viewerEmployee->division_id !== null
                && $viewerEmployee->division_id === $targetEmployee->division_id;
        }

        if ($user->hasRole(RoleName::EMPLOYEE->value)) {
            return $targetEmployee->id === $viewerEmployee->id;
        }

        return false;
    }

    /**
     * Akses foto selfie privat dibatasi hanya untuk pemilik, atasan dalam cakupan, dan HR Manager.
     */
    public function viewSelfie(User $user, Attendance $attendance): bool
    {
        $targetEmployee = $attendance->employee;

        if ($targetEmployee === null) {
            return false;
        }

        if ($targetEmployee->user_id === $user->id) {
            return true;
        }

        if ($user->hasRole(RoleName::HR_MANAGER->value)) {
            return true;
        }

        $viewerEmployee = $user->employee;

        if ($viewerEmployee === null) {
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
}
