<?php

declare(strict_types=1);

namespace App\Actions\Leave;

use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class BuildApprovalStepsAction
{
    /**
     * Menyusun daftar tahapan persetujuan berjenjang sesuai hierarki peran pemohon dan durasi pengajuan.
     *
     * @return array<int, array{step: int, role: string}>
     */
    public function execute(Employee $employee, LeaveType $leaveType, int $totalWorkDays): array
    {
        $employee->loadMissing('user');
        $applicantUser = $employee->user;
        $applicantUserId = $applicantUser?->id ?? $employee->user_id;
        $role = $applicantUser?->primaryRole();

        if ($role === RoleName::HR_MANAGER) {
            return [
                ['step' => 1, 'role' => RoleName::SUPER_ADMIN->value],
            ];
        }

        if ($role === RoleName::DEPT_MANAGER) {
            return [
                ['step' => 1, 'role' => RoleName::HR_MANAGER->value],
            ];
        }

        $roles = [];
        $needsHrEscalation = $leaveType->hr_approval_after_days !== null
            && $totalWorkDays > $leaveType->hr_approval_after_days;

        if ($role === RoleName::DIVISION_HEAD) {
            $firstRole = $this->hasActiveDepartmentManager($employee->department_id, $applicantUserId)
                ? RoleName::DEPT_MANAGER->value
                : RoleName::HR_MANAGER->value;

            $roles[] = $firstRole;

            if ($needsHrEscalation && $firstRole !== RoleName::HR_MANAGER->value) {
                $roles[] = RoleName::HR_MANAGER->value;
            }

            return $this->formatSteps($roles);
        }

        if ($employee->division_id !== null
            && $this->hasActiveDivisionHead($employee->division_id, $applicantUserId)) {
            $roles[] = RoleName::DIVISION_HEAD->value;
        }

        if ($this->hasActiveDepartmentManager($employee->department_id, $applicantUserId)) {
            $roles[] = RoleName::DEPT_MANAGER->value;
        } else {
            $roles[] = RoleName::HR_MANAGER->value;
        }

        if ($needsHrEscalation && ! in_array(RoleName::HR_MANAGER->value, $roles, true)) {
            $roles[] = RoleName::HR_MANAGER->value;
        }

        return $this->formatSteps($roles);
    }

    /**
     * Mengambil kandidat user approver aktif yang berwenang memproses langkah persetujuan tertentu.
     *
     * @return Collection<int, User>
     */
    public function resolveApproversForRole(string $role, Employee $applicant): Collection
    {
        $applicantUserId = $applicant->user_id;

        if ($role === RoleName::DIVISION_HEAD->value) {
            if ($applicant->division_id === null) {
                return new Collection;
            }

            return User::query()
                ->where('is_active', true)
                ->whereKeyNot($applicantUserId)
                ->whereHas('roles', static function (Builder $query): void {
                    $query->where('name', RoleName::DIVISION_HEAD->value);
                })
                ->whereHas('employee', static function (Builder $query) use ($applicant): void {
                    $query->where('is_active', true)
                        ->where('division_id', $applicant->division_id);
                })
                ->get();
        }

        if ($role === RoleName::DEPT_MANAGER->value) {
            return User::query()
                ->where('is_active', true)
                ->whereKeyNot($applicantUserId)
                ->whereHas('roles', static function (Builder $query): void {
                    $query->where('name', RoleName::DEPT_MANAGER->value);
                })
                ->whereHas('employee', static function (Builder $query) use ($applicant): void {
                    $query->where('is_active', true)
                        ->where('department_id', $applicant->department_id);
                })
                ->get();
        }

        if ($role === RoleName::HR_MANAGER->value || $role === RoleName::SUPER_ADMIN->value) {
            return User::query()
                ->where('is_active', true)
                ->whereKeyNot($applicantUserId)
                ->whereHas('roles', static function (Builder $query) use ($role): void {
                    $query->where('name', $role);
                })
                ->get();
        }

        return new Collection;
    }

    private function hasActiveDivisionHead(int $divisionId, int $excludeUserId): bool
    {
        return Employee::query()
            ->active()
            ->where('division_id', $divisionId)
            ->where('user_id', '!=', $excludeUserId)
            ->whereHas('user', static function (Builder $userQuery): void {
                $userQuery->where('is_active', true)
                    ->whereHas('roles', static function (Builder $roleQuery): void {
                        $roleQuery->where('name', RoleName::DIVISION_HEAD->value);
                    });
            })
            ->exists();
    }

    private function hasActiveDepartmentManager(int $departmentId, int $excludeUserId): bool
    {
        return Employee::query()
            ->active()
            ->where('department_id', $departmentId)
            ->where('user_id', '!=', $excludeUserId)
            ->whereHas('user', static function (Builder $userQuery): void {
                $userQuery->where('is_active', true)
                    ->whereHas('roles', static function (Builder $roleQuery): void {
                        $roleQuery->where('name', RoleName::DEPT_MANAGER->value);
                    });
            })
            ->exists();
    }

    /**
     * @param  array<int, string>  $roles
     * @return array<int, array{step: int, role: string}>
     */
    private function formatSteps(array $roles): array
    {
        $steps = [];

        foreach (array_values($roles) as $index => $roleName) {
            $steps[] = [
                'step' => $index + 1,
                'role' => $roleName,
            ];
        }

        return $steps;
    }
}
