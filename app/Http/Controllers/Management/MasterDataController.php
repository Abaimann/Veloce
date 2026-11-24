<?php

declare(strict_types=1);

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Http\Requests\Management\StoreDepartmentRequest;
use App\Http\Requests\Management\StoreDivisionRequest;
use App\Http\Requests\Management\StoreHolidayRequest;
use App\Http\Requests\Management\StoreOfficeLocationRequest;
use App\Http\Requests\Management\StoreWorkScheduleRequest;
use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\OfficeLocation;
use App\Models\WorkSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class MasterDataController extends Controller
{
    public function index(): View
    {
        Gate::authorize('manage', Department::class);

        return view('management.master.index', [
            'departments' => Department::query()
                ->with(['divisions', 'headEmployee.user'])
                ->withCount('employees')
                ->orderBy('code')
                ->get(),
            'divisions' => Division::query()
                ->with(['department', 'headEmployee.user'])
                ->withCount('employees')
                ->orderBy('department_id')
                ->orderBy('code')
                ->get(),
            'workSchedules' => WorkSchedule::query()
                ->withCount('employees')
                ->orderBy('name')
                ->get(),
            'officeLocations' => OfficeLocation::query()
                ->orderBy('name')
                ->get(),
            'holidays' => Holiday::query()
                ->orderByDesc('date')
                ->get(),
            'employees' => Employee::query()
                ->active()
                ->with('user')
                ->orderBy('employee_number')
                ->get(),
        ]);
    }

    public function storeDepartment(StoreDepartmentRequest $request): RedirectResponse
    {
        Gate::authorize('manage', Department::class);

        Department::query()->create([
            ...$request->validated(),
            'is_active' => true,
        ]);

        return redirect()
            ->route('management.master.index')
            ->with([
                'status' => __('master.saved', ['item' => 'Departemen']),
                'master_tab' => 'departments',
            ]);
    }

    public function toggleDepartment(Department $department): RedirectResponse
    {
        Gate::authorize('manage', Department::class);

        $department->update([
            'is_active' => ! $department->is_active,
        ]);

        return redirect()
            ->route('management.master.index')
            ->with([
                'status' => __('master.updated', ['item' => 'Departemen']),
                'master_tab' => 'departments',
            ]);
    }

    public function storeDivision(StoreDivisionRequest $request): RedirectResponse
    {
        Gate::authorize('manage', Department::class);

        Division::query()->create([
            ...$request->validated(),
            'is_active' => true,
        ]);

        return redirect()
            ->route('management.master.index')
            ->with([
                'status' => __('master.saved', ['item' => 'Divisi']),
                'master_tab' => 'divisions',
            ]);
    }

    public function toggleDivision(Division $division): RedirectResponse
    {
        Gate::authorize('manage', Department::class);

        $division->update([
            'is_active' => ! $division->is_active,
        ]);

        return redirect()
            ->route('management.master.index')
            ->with([
                'status' => __('master.updated', ['item' => 'Divisi']),
                'master_tab' => 'divisions',
            ]);
    }

    public function storeWorkSchedule(StoreWorkScheduleRequest $request): RedirectResponse
    {
        Gate::authorize('manage', Department::class);

        $validated = $request->validated();
        $workDays = array_values(array_map('intval', $validated['work_days']));
        sort($workDays);

        WorkSchedule::query()->create([
            ...$validated,
            'work_days' => $workDays,
            'is_active' => true,
        ]);

        return redirect()
            ->route('management.master.index')
            ->with([
                'status' => __('master.saved', ['item' => 'Jadwal Kerja']),
                'master_tab' => 'schedules',
            ]);
    }

    public function toggleWorkSchedule(WorkSchedule $workSchedule): RedirectResponse
    {
        Gate::authorize('manage', Department::class);

        $workSchedule->update([
            'is_active' => ! $workSchedule->is_active,
        ]);

        return redirect()
            ->route('management.master.index')
            ->with([
                'status' => __('master.updated', ['item' => 'Jadwal Kerja']),
                'master_tab' => 'schedules',
            ]);
    }

    public function storeOfficeLocation(StoreOfficeLocationRequest $request): RedirectResponse
    {
        Gate::authorize('manage', Department::class);

        OfficeLocation::query()->create([
            ...$request->validated(),
            'is_active' => true,
        ]);

        return redirect()
            ->route('management.master.index')
            ->with([
                'status' => __('master.saved', ['item' => 'Lokasi Kantor']),
                'master_tab' => 'offices',
            ]);
    }

    public function toggleOfficeLocation(OfficeLocation $officeLocation): RedirectResponse
    {
        Gate::authorize('manage', Department::class);

        $officeLocation->update([
            'is_active' => ! $officeLocation->is_active,
        ]);

        return redirect()
            ->route('management.master.index')
            ->with([
                'status' => __('master.updated', ['item' => 'Lokasi Kantor']),
                'master_tab' => 'offices',
            ]);
    }

    public function storeHoliday(StoreHolidayRequest $request): RedirectResponse
    {
        Gate::authorize('manage', Department::class);

        Holiday::query()->create($request->validated());

        return redirect()
            ->route('management.master.index')
            ->with([
                'status' => __('master.saved', ['item' => 'Hari Libur']),
                'master_tab' => 'holidays',
            ]);
    }

    public function destroyHoliday(Holiday $holiday): RedirectResponse
    {
        Gate::authorize('manage', Department::class);

        $holiday->delete();

        return redirect()
            ->route('management.master.index')
            ->with([
                'status' => __('master.deleted', ['item' => 'Hari Libur']),
                'master_tab' => 'holidays',
            ]);
    }
}
