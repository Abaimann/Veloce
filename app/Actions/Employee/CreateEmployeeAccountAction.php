<?php

declare(strict_types=1);

namespace App\Actions\Employee;

use App\Actions\Auth\CreateUserInvitationAction;
use App\Enums\EmploymentType;
use App\Enums\RoleName;
use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CreateEmployeeAccountAction
{
    public function __construct(
        private readonly CreateUserInvitationAction $createInvitation,
    ) {}

    /**
     * Membuat akun pengguna tanpa kata sandi awal beserta profil karyawan dan tautan undangan aktivasi.
     *
     * @param  array<string, mixed>  $payload
     * @return array{employee: Employee, invitation_url: string}
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(User $actor, array $payload): array
    {
        $departmentId = (int) $payload['department_id'];
        $divisionId = $this->nullableInt($payload['division_id'] ?? null);
        $supervisorId = $this->nullableInt($payload['supervisor_id'] ?? null);
        $roleValue = $payload['role'] instanceof RoleName
            ? $payload['role']->value
            : (string) $payload['role'];

        $this->authorizeInScope($actor, $departmentId, $roleValue);

        $employmentType = $this->resolveEmploymentType($payload['employment_type']);
        $this->validateRoleAndHierarchy($roleValue, $departmentId, $divisionId, $supervisorId);

        return DB::transaction(function () use (
            $actor,
            $payload,
            $departmentId,
            $divisionId,
            $supervisorId,
            $roleValue,
            $employmentType,
        ): array {
            // Pembuat akun tidak pernah menetapkan atau mengetahui kata sandi awal pengguna.
            $user = User::query()->create([
                'name' => trim((string) $payload['name']),
                'email' => Str::lower(trim((string) $payload['email'])),
                'password' => null,
                'is_active' => true,
                'email_verified_at' => null,
            ]);

            $user->syncRoles([$roleValue]);

            $positionTitle = isset($payload['position_title']) && trim((string) $payload['position_title']) !== ''
                ? trim((string) $payload['position_title'])
                : null;

            $employee = Employee::query()->create([
                'user_id' => $user->id,
                'employee_number' => trim((string) $payload['employee_number']),
                'department_id' => $departmentId,
                'division_id' => $divisionId,
                'supervisor_id' => $supervisorId,
                'employment_type' => $employmentType,
                'position_title' => $positionTitle,
                'work_schedule_id' => (int) $payload['work_schedule_id'],
                'join_date' => $payload['join_date'],
                'contract_start_date' => $payload['contract_start_date'] ?? $payload['join_date'],
                'contract_end_date' => $payload['contract_end_date'] ?? null,
                'base_salary' => (int) ($payload['base_salary'] ?? 0),
                'fixed_allowance' => (int) ($payload['fixed_allowance'] ?? 0),
                'is_active' => true,
            ]);

            $this->assignOrganizationHeadIfVacant($roleValue, $departmentId, $divisionId, $employee);

            $currentYear = CarbonImmutable::now((string) config('attendance.timezone', 'Asia/Jakarta'))->year;
            LeaveBalance::query()->firstOrCreate(
                [
                    'employee_id' => $employee->id,
                    'year' => $currentYear,
                ],
                [
                    'entitled_days' => $employmentType->defaultAnnualLeaveDays(),
                    'used_days' => 0,
                ],
            );

            $invite = $this->createInvitation->execute($user, $actor);

            activity()
                ->causedBy($actor)
                ->performedOn($employee)
                ->withProperties([
                    'user_id' => $user->id,
                    'role' => $roleValue,
                    'department_id' => $departmentId,
                    'division_id' => $divisionId,
                ])
                ->log('Membuat akun karyawan baru dan tautan undangan aktivasi');

            return [
                'employee' => $employee->load(['user', 'department', 'division']),
                'invitation_url' => $invite['url'],
            ];
        });
    }

    /**
     * @throws AuthorizationException
     */
    private function authorizeInScope(User $actor, int $departmentId, string $roleValue): void
    {
        if (Gate::forUser($actor)->allows('createInScope', [Employee::class, $departmentId, $roleValue])) {
            return;
        }

        $message = $actor->hasRole(RoleName::DEPT_MANAGER->value)
            && $actor->employee?->department_id !== $departmentId
            ? __('master.department_scope_denied')
            : __('master.role_assignment_denied');

        throw new AuthorizationException($message);
    }

    /**
     * @throws ValidationException
     */
    private function resolveEmploymentType(mixed $value): EmploymentType
    {
        if ($value instanceof EmploymentType) {
            return $value;
        }

        $resolved = EmploymentType::tryFrom((string) $value);

        if ($resolved === null) {
            throw ValidationException::withMessages([
                'employment_type' => __('validation.in', [
                    'attribute' => __('validation.attributes.employment_type'),
                ]),
            ]);
        }

        return $resolved;
    }

    /**
     * @throws ValidationException
     */
    private function validateRoleAndHierarchy(
        string $roleValue,
        int $departmentId,
        ?int $divisionId,
        ?int $supervisorId,
    ): void {
        if (RoleName::tryFrom($roleValue) === null) {
            throw ValidationException::withMessages([
                'role' => __('validation.in', [
                    'attribute' => __('validation.attributes.role'),
                ]),
            ]);
        }

        if (! Department::query()->whereKey($departmentId)->exists()) {
            throw ValidationException::withMessages([
                'department_id' => __('validation.exists', [
                    'attribute' => __('validation.attributes.department_id'),
                ]),
            ]);
        }

        if ($divisionId !== null) {
            $divisionBelongsToDepartment = Division::query()
                ->whereKey($divisionId)
                ->where('department_id', $departmentId)
                ->exists();

            if (! $divisionBelongsToDepartment) {
                throw ValidationException::withMessages([
                    'division_id' => __('validation.exists', [
                        'attribute' => __('validation.attributes.division_id'),
                    ]),
                ]);
            }
        }

        if ($supervisorId !== null && ! Employee::query()->whereKey($supervisorId)->exists()) {
            throw ValidationException::withMessages([
                'supervisor_id' => __('validation.exists', ['attribute' => 'atasan']),
            ]);
        }
    }

    private function assignOrganizationHeadIfVacant(
        string $roleValue,
        int $departmentId,
        ?int $divisionId,
        Employee $employee,
    ): void {
        if ($roleValue === RoleName::DEPT_MANAGER->value) {
            $department = Department::query()
                ->whereKey($departmentId)
                ->lockForUpdate()
                ->first();

            if ($department !== null && $department->head_employee_id === null) {
                $department->update(['head_employee_id' => $employee->id]);
            }
        }

        if ($roleValue === RoleName::DIVISION_HEAD->value && $divisionId !== null) {
            $division = Division::query()
                ->whereKey($divisionId)
                ->lockForUpdate()
                ->first();

            if ($division !== null && $division->head_employee_id === null) {
                $division->update(['head_employee_id' => $employee->id]);
            }
        }
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
