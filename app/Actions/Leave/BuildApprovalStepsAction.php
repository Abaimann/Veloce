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
     * Menyusun daftar tahapan persetujuan absensi/cuti khusus HR:
     * hanya HR Manager (atau Super Admin khusus untuk pengajuan HR Manager).
     *
     * @return array<int, array{step: int, role: string}>
     */
    public function execute(Employee $employee, LeaveType $leaveType, int $totalWorkDays): array
    {
        $employee->loadMissing('user');
        $applicantUser = $employee->user;
        $role = $applicantUser?->primaryRole();

        if ($role === RoleName::HR_MANAGER) {
            return [
                ['step' => 1, 'role' => RoleName::SUPER_ADMIN->value],
            ];
        }

        return [
            ['step' => 1, 'role' => RoleName::HR_MANAGER->value],
        ];
    }

    /**
     * Mengambil kandidat user approver aktif yang berwenang memproses langkah persetujuan tertentu.
     *
     * @return Collection<int, User>
     */
    public function resolveApproversForRole(string $role, Employee $applicant): Collection
    {
        $applicantUserId = $applicant->user_id;

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
}
