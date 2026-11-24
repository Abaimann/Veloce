<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\User;

class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([
            RoleName::SUPER_ADMIN->value,
            RoleName::HR_MANAGER->value,
            RoleName::DEPT_MANAGER->value,
            RoleName::DIVISION_HEAD->value,
        ]);
    }

    public function view(User $user, Employee $employee): bool
    {
        if ($user->hasAnyRole([RoleName::SUPER_ADMIN->value, RoleName::HR_MANAGER->value])) {
            return true;
        }

        $viewerEmployee = $user->employee;

        if ($viewerEmployee === null) {
            return false;
        }

        if ($user->hasRole(RoleName::DEPT_MANAGER->value)) {
            return $viewerEmployee->department_id === $employee->department_id;
        }

        if ($user->hasRole(RoleName::DIVISION_HEAD->value)) {
            if ($employee->id === $viewerEmployee->id) {
                return true;
            }

            return $viewerEmployee->division_id !== null
                && $employee->division_id === $viewerEmployee->division_id;
        }

        if ($user->hasRole(RoleName::EMPLOYEE->value)) {
            return $employee->id === $viewerEmployee->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole([
            RoleName::SUPER_ADMIN->value,
            RoleName::HR_MANAGER->value,
            RoleName::DEPT_MANAGER->value,
        ]);
    }

    public function createInScope(User $user, int $departmentId, ?string $role = null): bool
    {
        if ($user->hasRole(RoleName::SUPER_ADMIN->value)) {
            return true;
        }

        if ($user->hasRole(RoleName::HR_MANAGER->value)) {
            return $role === null || $role !== RoleName::SUPER_ADMIN->value;
        }

        if ($user->hasRole(RoleName::DEPT_MANAGER->value)) {
            if ($user->employee === null || $user->employee->department_id !== $departmentId) {
                return false;
            }

            return $role === null || in_array($role, [
                RoleName::EMPLOYEE->value,
                RoleName::DIVISION_HEAD->value,
            ], true);
        }

        return false;
    }

    public function regenerateInvitation(User $user, Employee $employee): bool
    {
        if (! $this->create($user)) {
            return false;
        }

        if ($user->hasRole(RoleName::SUPER_ADMIN->value)) {
            return true;
        }

        if ($user->hasRole(RoleName::HR_MANAGER->value)) {
            return ! ($employee->user?->hasRole(RoleName::SUPER_ADMIN->value) ?? false);
        }

        if ($user->hasRole(RoleName::DEPT_MANAGER->value)) {
            return $user->employee !== null
                && $user->employee->department_id === $employee->department_id;
        }

        return false;
    }

    public function update(User $user, Employee $employee): bool
    {
        if ($user->hasRole(RoleName::SUPER_ADMIN->value)) {
            return true;
        }

        if ($user->hasRole(RoleName::HR_MANAGER->value)) {
            return ! ($employee->user?->hasRole(RoleName::SUPER_ADMIN->value) ?? false);
        }

        if ($user->hasRole(RoleName::DEPT_MANAGER->value)) {
            return $user->employee !== null
                && $user->employee->department_id === $employee->department_id
                && ! ($employee->user?->hasAnyRole([RoleName::SUPER_ADMIN->value, RoleName::HR_MANAGER->value]) ?? false);
        }

        return false;
    }
}
