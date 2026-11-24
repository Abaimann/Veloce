<?php

declare(strict_types=1);

namespace App\Http\Controllers\Management;

use App\Actions\Auth\CreateUserInvitationAction;
use App\Actions\Employee\CreateEmployeeAccountAction;
use App\Enums\EmploymentType;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Management\StoreEmployeeRequest;
use App\Http\Requests\Management\UpdateEmployeeRequest;
use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
use App\Models\WorkSchedule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class EmployeeManagementController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Employee::class);

        $actor = $request->user();
        $canFilterDepartment = $actor->hasAnyRole([
            RoleName::SUPER_ADMIN->value,
            RoleName::HR_MANAGER->value,
        ]);
        $canFilterDivision = $canFilterDepartment || $actor->hasRole(RoleName::DEPT_MANAGER->value);

        $search = is_string($request->query('q')) ? trim($request->query('q')) : '';

        $rawDepartmentId = $request->query('department_id');
        $selectedDepartmentId = $canFilterDepartment
            && is_scalar($rawDepartmentId)
            && ctype_digit((string) $rawDepartmentId)
            && (int) $rawDepartmentId > 0
            ? (int) $rawDepartmentId
            : null;

        $rawDivisionId = $request->query('division_id');
        $selectedDivisionFilter = null;
        if ($canFilterDivision && is_string($rawDivisionId)) {
            if ($rawDivisionId === 'none') {
                $selectedDivisionFilter = 'none';
            } elseif (ctype_digit($rawDivisionId) && (int) $rawDivisionId > 0) {
                $selectedDivisionFilter = (int) $rawDivisionId;
            }
        }

        $filterableRoles = [
            RoleName::SUPER_ADMIN,
            RoleName::HR_MANAGER,
            RoleName::PAYROLL,
            RoleName::DEPT_MANAGER,
            RoleName::DIVISION_HEAD,
            RoleName::EMPLOYEE,
        ];
        $rawRole = $request->query('role');
        $selectedRole = is_string($rawRole) ? RoleName::tryFrom($rawRole) : null;
        if ($selectedRole !== null && ! in_array($selectedRole, $filterableRoles, true)) {
            $selectedRole = null;
        }

        $rawEmploymentType = $request->query('employment_type');
        $selectedEmploymentType = is_string($rawEmploymentType) && $rawEmploymentType !== ''
            ? EmploymentType::tryFrom($rawEmploymentType)
            : null;

        $rawStatus = $request->query('status');
        $selectedStatus = in_array($rawStatus, ['active', 'pending_activation', 'inactive'], true)
            ? $rawStatus
            : null;

        $employees = Employee::query()
            ->visibleTo($actor)
            ->with(['user.roles', 'department', 'division', 'workSchedule'])
            ->when($search !== '', static function (Builder $query) use ($search): void {
                $pattern = '%'.Str::lower($search).'%';

                $query->where(static function (Builder $inner) use ($pattern): void {
                    $inner->whereRaw('LOWER(employees.employee_number) LIKE ?', [$pattern])
                        ->orWhereRaw("LOWER(COALESCE(employees.position_title, '')) LIKE ?", [$pattern])
                        ->orWhereHas('user', static function (Builder $userQuery) use ($pattern): void {
                            $userQuery->whereRaw('LOWER(name) LIKE ?', [$pattern])
                                ->orWhereRaw('LOWER(email) LIKE ?', [$pattern]);
                        });
                });
            })
            ->when(
                $selectedDepartmentId !== null,
                static fn (Builder $query): Builder => $query->where('department_id', $selectedDepartmentId),
            )
            ->when(
                $selectedDivisionFilter === 'none',
                static fn (Builder $query): Builder => $query->whereNull('division_id'),
            )
            ->when(
                is_int($selectedDivisionFilter),
                static fn (Builder $query): Builder => $query->where('division_id', $selectedDivisionFilter),
            )
            ->when($selectedRole !== null, static function (Builder $query) use ($selectedRole): void {
                $query->whereHas('user.roles', static function (Builder $roleQuery) use ($selectedRole): void {
                    $roleQuery->where('name', $selectedRole->value);
                });
            })
            ->when(
                $selectedEmploymentType !== null,
                static fn (Builder $query): Builder => $query->where('employment_type', $selectedEmploymentType->value),
            )
            ->when($selectedStatus === 'active', static function (Builder $query): void {
                $query->where('is_active', true)
                    ->whereHas('user', static function (Builder $userQuery): void {
                        $userQuery->where('is_active', true)->whereNotNull('password');
                    });
            })
            ->when($selectedStatus === 'pending_activation', static function (Builder $query): void {
                $query->whereHas('user', static function (Builder $userQuery): void {
                    $userQuery->whereNull('password');
                });
            })
            ->when($selectedStatus === 'inactive', static function (Builder $query): void {
                $query->where(static function (Builder $inner): void {
                    $inner->where('is_active', false)
                        ->orWhereHas('user', static function (Builder $userQuery): void {
                            $userQuery->where('is_active', false);
                        });
                });
            })
            ->orderBy('employee_number')
            ->paginate(15)
            ->withQueryString();

        $departments = $canFilterDepartment
            ? Department::query()->active()->orderBy('name')->get(['id', 'code', 'name'])
            : collect();

        $divisions = $canFilterDivision
            ? Division::query()
                ->active()
                ->when(
                    ! $canFilterDepartment && $actor->employee?->department_id !== null,
                    static fn (Builder $query): Builder => $query->where('department_id', $actor->employee->department_id),
                )
                ->when(
                    ! $canFilterDepartment && $actor->employee?->department_id === null,
                    static fn (Builder $query): Builder => $query->whereRaw('1 = 0'),
                )
                ->orderBy('name')
                ->get(['id', 'department_id', 'code', 'name'])
            : collect();

        $hasActiveFilters = $search !== ''
            || $selectedDepartmentId !== null
            || $selectedDivisionFilter !== null
            || $selectedRole !== null
            || $selectedEmploymentType !== null
            || $selectedStatus !== null;

        return view('management.employees.index', [
            'employees' => $employees,
            'departments' => $departments,
            'divisions' => $divisions,
            'filterableRoles' => $filterableRoles,
            'employmentTypes' => EmploymentType::cases(),
            'canFilterDepartment' => $canFilterDepartment,
            'canFilterDivision' => $canFilterDivision,
            'search' => $search,
            'selectedDepartmentId' => $selectedDepartmentId,
            'selectedDivisionFilter' => $selectedDivisionFilter,
            'selectedRole' => $selectedRole,
            'selectedEmploymentType' => $selectedEmploymentType,
            'selectedStatus' => $selectedStatus,
            'hasActiveFilters' => $hasActiveFilters,
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Employee::class);

        $actor = $request->user();
        $isSuperAdmin = $actor->hasRole(RoleName::SUPER_ADMIN->value);
        $isHrManager = $actor->hasRole(RoleName::HR_MANAGER->value);

        if ($isSuperAdmin || $isHrManager) {
            $departments = Department::query()->active()->orderBy('name')->get();
            $divisions = Division::query()->active()->orderBy('name')->get();
            $supervisors = Employee::query()->active()->with('user')->orderBy('employee_number')->get();
            $allowedRoles = $isSuperAdmin
                ? RoleName::cases()
                : array_values(array_filter(
                    RoleName::cases(),
                    static fn (RoleName $role): bool => $role !== RoleName::SUPER_ADMIN
                ));
            $lockedDepartmentId = null;
        } else {
            $actorDepartmentId = $actor->employee?->department_id;
            $departments = $actorDepartmentId !== null
                ? Department::query()->active()->whereKey($actorDepartmentId)->orderBy('name')->get()
                : collect();
            $divisions = $actorDepartmentId !== null
                ? Division::query()->active()->where('department_id', $actorDepartmentId)->orderBy('name')->get()
                : collect();
            $supervisors = $actorDepartmentId !== null
                ? Employee::query()->active()->where('department_id', $actorDepartmentId)->with('user')->orderBy('employee_number')->get()
                : collect();
            $allowedRoles = [
                RoleName::EMPLOYEE,
                RoleName::DIVISION_HEAD,
            ];
            $lockedDepartmentId = $actorDepartmentId;
        }

        $workSchedules = WorkSchedule::query()->active()->orderBy('name')->get();

        return view('management.employees.create', [
            'departments' => $departments,
            'divisions' => $divisions,
            'workSchedules' => $workSchedules,
            'employmentTypes' => EmploymentType::cases(),
            'allowedRoles' => $allowedRoles,
            'supervisors' => $supervisors,
            'lockedDepartmentId' => $lockedDepartmentId,
        ]);
    }

    public function store(
        StoreEmployeeRequest $request,
        CreateEmployeeAccountAction $action,
    ): RedirectResponse {
        $validated = $request->validated();

        Gate::authorize('createInScope', [
            Employee::class,
            (int) $validated['department_id'],
            (string) $validated['role'],
        ]);

        $result = $action->execute($request->user(), $validated);
        $employee = $result['employee']->loadMissing('user');
        $invitedName = $employee->user->name;

        return redirect()
            ->route('management.employees.index')
            ->with([
                'status' => __('master.employee_invited', ['name' => $invitedName]),
                'invitation_url' => $result['invitation_url'],
                'invited_name' => $invitedName,
            ]);
    }

    public function edit(Request $request, Employee $employee): View
    {
        $employee->loadMissing(['user.roles', 'department', 'division']);
        Gate::authorize('update', $employee);

        $actor = $request->user();
        $isHrOrAdmin = $actor->hasAnyRole([RoleName::SUPER_ADMIN->value, RoleName::HR_MANAGER->value]);

        if ($isHrOrAdmin) {
            $departments = Department::query()->active()->orderBy('name')->get();
            $divisions = Division::query()->active()->orderBy('name')->get();
            $supervisors = Employee::query()->active()->whereKeyNot($employee->id)->with('user')->orderBy('employee_number')->get();
        } else {
            $actorDepartmentId = $actor->employee?->department_id;
            $departments = $actorDepartmentId !== null
                ? Department::query()->active()->whereKey($actorDepartmentId)->orderBy('name')->get()
                : collect();
            $divisions = $actorDepartmentId !== null
                ? Division::query()->active()->where('department_id', $actorDepartmentId)->orderBy('name')->get()
                : collect();
            $supervisors = $actorDepartmentId !== null
                ? Employee::query()->active()->where('department_id', $actorDepartmentId)->whereKeyNot($employee->id)->with('user')->orderBy('employee_number')->get()
                : collect();
        }

        return view('management.employees.edit', [
            'employee' => $employee,
            'departments' => $departments,
            'divisions' => $divisions,
            'workSchedules' => WorkSchedule::query()->active()->orderBy('name')->get(),
            'employmentTypes' => EmploymentType::cases(),
            'supervisors' => $supervisors,
            'canEditSalary' => $isHrOrAdmin,
        ]);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $validated = $request->validated();
        $actor = $request->user();
        $isHrOrAdmin = $actor->hasAnyRole([RoleName::SUPER_ADMIN->value, RoleName::HR_MANAGER->value]);

        if (! $isHrOrAdmin && (int) $validated['department_id'] !== (int) $employee->department_id) {
            abort(403);
        }

        DB::transaction(function () use ($employee, $validated, $isHrOrAdmin, $request): void {
            $employee->loadMissing('user');
            $isActive = $request->boolean('is_active', true);

            $employee->user->update([
                'name' => trim((string) $validated['name']),
                'is_active' => $isActive,
            ]);

            $payload = [
                'employee_number' => trim((string) $validated['employee_number']),
                'department_id' => (int) $validated['department_id'],
                'division_id' => ! empty($validated['division_id']) ? (int) $validated['division_id'] : null,
                'supervisor_id' => ! empty($validated['supervisor_id']) ? (int) $validated['supervisor_id'] : null,
                'work_schedule_id' => (int) $validated['work_schedule_id'],
                'employment_type' => $validated['employment_type'],
                'position_title' => ! empty($validated['position_title']) ? trim((string) $validated['position_title']) : null,
                'join_date' => $validated['join_date'],
                'contract_start_date' => $validated['contract_start_date'] ?? $validated['join_date'],
                'contract_end_date' => ! empty($validated['contract_end_date']) ? $validated['contract_end_date'] : null,
                'is_active' => $isActive,
            ];

            if ($isHrOrAdmin) {
                $payload['base_salary'] = (int) ($validated['base_salary'] ?? $employee->base_salary);
                $payload['fixed_allowance'] = (int) ($validated['fixed_allowance'] ?? $employee->fixed_allowance);
            }

            $employee->update($payload);
        });

        return redirect()
            ->route('management.employees.index')
            ->with('status', 'Data karyawan dan masa kontrak berhasil diperbarui.');
    }

    public function regenerateInvitation(
        Request $request,
        Employee $employee,
        CreateUserInvitationAction $inviteAction,
    ): RedirectResponse {
        $employee->loadMissing('user.roles');

        Gate::authorize('regenerateInvitation', $employee);

        $result = $inviteAction->execute($employee->user, $request->user());
        $invitedName = $employee->user->name;

        return redirect()
            ->route('management.employees.index')
            ->with([
                'status' => __('master.invitation_regenerated', ['name' => $invitedName]),
                'invitation_url' => $result['url'],
                'invited_name' => $invitedName,
            ]);
    }
}
