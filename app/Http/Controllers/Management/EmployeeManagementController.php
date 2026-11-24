<?php

declare(strict_types=1);

namespace App\Http\Controllers\Management;

use App\Actions\Auth\CreateUserInvitationAction;
use App\Actions\Employee\CreateEmployeeAccountAction;
use App\Enums\EmploymentType;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Management\StoreEmployeeRequest;
use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
use App\Models\WorkSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class EmployeeManagementController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Employee::class);

        $employees = Employee::query()
            ->visibleTo($request->user())
            ->with(['user.roles', 'department', 'division', 'workSchedule'])
            ->orderBy('employee_number')
            ->paginate(15);

        return view('management.employees.index', [
            'employees' => $employees,
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
