<?php

declare(strict_types=1);

namespace App\Http\Controllers\Work;

use App\Enums\RoleName;
use App\Enums\TargetPeriodType;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\DepartmentTarget;
use App\Models\Division;
use App\Models\DivisionTarget;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TargetController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $user->loadMissing(['roles', 'employee.department', 'employee.division']);

        $timezone = (string) config('attendance.timezone', 'Asia/Jakarta');
        $nowWib = CarbonImmutable::now($timezone);

        $rawMonth = $request->query('month');
        if (is_string($rawMonth) && preg_match('/^\d{4}-\d{2}$/', $rawMonth) === 1) {
            $parsed = CarbonImmutable::createFromFormat('!Y-m', $rawMonth, $timezone);
            $year = $parsed !== false ? $parsed->year : $nowWib->year;
            $month = $parsed !== false ? $parsed->month : $nowWib->month;
        } else {
            $year = $nowWib->year;
            $month = $nowWib->month;
        }

        $isAdminOrHr = $user->hasAnyRole([RoleName::SUPER_ADMIN->value, RoleName::HR_MANAGER->value]);
        $isDeptManager = $user->hasRole(RoleName::DEPT_MANAGER->value);
        $isDivisionHead = $user->hasRole(RoleName::DIVISION_HEAD->value);

        $employee = $user->employee;
        $selectedPeriod = is_string($request->query('period_type'))
            ? TargetPeriodType::tryFrom((string) $request->query('period_type'))
            : null;

        $deptTargets = DepartmentTarget::query()
            ->with([
                'department',
                'creator',
                'directDivisionTargets.division',
                'divisionTargets.division',
            ])
            ->where('year', $year)
            ->where('month', $month)
            ->when(
                ! $isAdminOrHr && $employee !== null,
                static fn (Builder $q): Builder => $q->where('department_id', $employee->department_id),
            )
            ->when(! $isAdminOrHr && $employee === null, static fn (Builder $q): Builder => $q->whereRaw('1 = 0'))
            ->latest('id')
            ->get();

        $monthStart = CarbonImmutable::create($year, $month, 1, 0, 0, 0, $timezone)->toDateString();
        $monthEnd = CarbonImmutable::create($year, $month, 1, 0, 0, 0, $timezone)->endOfMonth()->toDateString();

        $divisionTargets = DivisionTarget::query()
            ->with([
                'department',
                'division',
                'departmentTarget',
                'parentTarget',
                'childTargets',
                'creator',
            ])
            ->where(static function (Builder $q) use ($monthStart, $monthEnd): void {
                $q->whereDate('start_date', '<=', $monthEnd)
                    ->whereDate('end_date', '>=', $monthStart);
            })
            ->when($selectedPeriod !== null, static fn (Builder $q): Builder => $q->where('period_type', $selectedPeriod->value))
            ->when(
                ! $isAdminOrHr && $isDeptManager && $employee !== null,
                static fn (Builder $q): Builder => $q->where('department_id', $employee->department_id),
            )
            ->when(
                ! $isAdminOrHr && ! $isDeptManager && $employee !== null,
                static function (Builder $q) use ($employee): void {
                    if ($employee->division_id !== null) {
                        $q->where('division_id', $employee->division_id);
                    } else {
                        $q->where('department_id', $employee->department_id);
                    }
                },
            )
            ->when(! $isAdminOrHr && $employee === null, static fn (Builder $q): Builder => $q->whereRaw('1 = 0'))
            ->orderByDesc('start_date')
            ->orderBy('id')
            ->get();

        // Kandidat induk divisi (target Bulanan atau Mingguan divisi) untuk hierarki Mingguan -> Harian.
        $parentDivisionCandidates = DivisionTarget::query()
            ->with('division')
            ->whereIn('period_type', [TargetPeriodType::MONTHLY->value, TargetPeriodType::WEEKLY->value])
            ->whereDate('start_date', '<=', $monthEnd)
            ->whereDate('end_date', '>=', $monthStart)
            ->when(
                ! $isAdminOrHr && $employee !== null,
                static fn (Builder $q): Builder => $employee->division_id !== null
                    ? $q->where('division_id', $employee->division_id)
                    : $q->where('department_id', $employee->department_id),
            )
            ->when(! $isAdminOrHr && $employee === null, static fn (Builder $q): Builder => $q->whereRaw('1 = 0'))
            ->orderBy('period_type')
            ->orderBy('start_date')
            ->get();

        $availableDepartments = $isAdminOrHr
            ? Department::query()->active()->orderBy('name')->get(['id', 'code', 'name'])
            : ($employee?->department !== null ? collect([$employee->department]) : collect());

        $availableDivisions = $isAdminOrHr
            ? Division::query()->active()->orderBy('name')->get(['id', 'department_id', 'code', 'name'])
            : ($isDeptManager && $employee !== null
                ? Division::query()->active()->where('department_id', $employee->department_id)->orderBy('name')->get(['id', 'department_id', 'code', 'name'])
                : ($employee?->division !== null ? collect([$employee->division]) : collect()));

        return view('targets.index', [
            'deptTargets' => $deptTargets,
            'divisionTargets' => $divisionTargets,
            'parentDivisionCandidates' => $parentDivisionCandidates,
            'availableDepartments' => $availableDepartments,
            'availableDivisions' => $availableDivisions,
            'periodTypes' => [TargetPeriodType::MONTHLY, TargetPeriodType::WEEKLY],
            'selectedMonth' => sprintf('%04d-%02d', $year, $month),
            'selectedMonthLabel' => CarbonImmutable::create($year, $month, 1, 0, 0, 0, $timezone)->translatedFormat('F Y'),
            'selectedPeriod' => $selectedPeriod,
            'canCreateDepartmentTarget' => $isDeptManager || $isAdminOrHr,
            'canCreateDivisionTarget' => $isDivisionHead || $isAdminOrHr,
            'canEditWeights' => $isDivisionHead || $isDeptManager || $isAdminOrHr,
            'todayWib' => $nowWib->toDateString(),
        ]);
    }

    public function storeDepartmentTarget(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->loadMissing('employee');

        if (! $user->hasAnyRole([RoleName::DEPT_MANAGER->value, RoleName::HR_MANAGER->value, RoleName::SUPER_ADMIN->value])) {
            abort(403);
        }

        $isAdminOrHr = $user->hasAnyRole([RoleName::HR_MANAGER->value, RoleName::SUPER_ADMIN->value]);

        $validated = $request->validate([
            'department_id' => ['required', 'integer', Rule::exists(Department::class, 'id')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'target_value' => ['required', 'integer', 'min:1', 'max:100000'],
            'unit_label' => ['required', 'string', 'max:32'],
        ]);

        if (! $isAdminOrHr && (int) $validated['department_id'] !== (int) $user->employee?->department_id) {
            abort(403);
        }

        [$year, $month] = array_map('intval', explode('-', (string) $validated['month']));

        DepartmentTarget::query()->create([
            'department_id' => (int) $validated['department_id'],
            'created_by' => $user->id,
            'title' => trim((string) $validated['title']),
            'description' => ! empty($validated['description']) ? trim((string) $validated['description']) : null,
            'year' => $year,
            'month' => $month,
            'target_value' => (int) $validated['target_value'],
            'unit_label' => trim((string) $validated['unit_label']),
            'progress_percent' => 0,
            'status' => 'active',
        ]);

        return redirect()
            ->route('targets.index', ['month' => $validated['month']])
            ->with('status', 'Target bulanan departemen berhasil ditambahkan.');
    }

    public function storeDivisionTarget(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->loadMissing('employee');

        if (! $user->hasAnyRole([RoleName::DIVISION_HEAD->value, RoleName::HR_MANAGER->value, RoleName::SUPER_ADMIN->value])) {
            abort(403);
        }

        $isAdminOrHr = $user->hasAnyRole([RoleName::HR_MANAGER->value, RoleName::SUPER_ADMIN->value]);

        $validated = $request->validate([
            'division_id' => ['required', 'integer', Rule::exists(Division::class, 'id')],
            'department_target_id' => ['nullable', 'integer', Rule::exists(DepartmentTarget::class, 'id')],
            'parent_division_target_id' => ['nullable', 'integer', Rule::exists(DivisionTarget::class, 'id')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'period_type' => ['required', 'string', Rule::enum(TargetPeriodType::class)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'target_value' => ['required', 'integer', 'min:1', 'max:100000'],
            'weight_percent' => ['nullable', 'numeric', 'min:1', 'max:100'],
            'unit_label' => ['required', 'string', 'max:32'],
        ]);

        $division = Division::query()->findOrFail((int) $validated['division_id']);

        if (! $isAdminOrHr && $division->id !== (int) $user->employee?->division_id) {
            abort(403);
        }

        $periodType = TargetPeriodType::from((string) $validated['period_type']);

        $parentDivisionTargetId = ! empty($validated['parent_division_target_id'])
            ? (int) $validated['parent_division_target_id']
            : null;
        $parentDivisionTarget = null;

        if ($parentDivisionTargetId !== null) {
            $parentDivisionTarget = DivisionTarget::query()->find($parentDivisionTargetId);

            $validParentHierarchy = $parentDivisionTarget !== null
                && $parentDivisionTarget->department_id === $division->department_id
                && (
                    ($periodType === TargetPeriodType::DAILY && in_array($parentDivisionTarget->period_type, [TargetPeriodType::WEEKLY, TargetPeriodType::MONTHLY], true))
                    || ($periodType === TargetPeriodType::WEEKLY && $parentDivisionTarget->period_type === TargetPeriodType::MONTHLY)
                );

            if (! $validParentHierarchy) {
                $parentDivisionTarget = null;
                $parentDivisionTargetId = null;
            }
        }

        $departmentTargetId = ! empty($validated['department_target_id']) ? (int) $validated['department_target_id'] : null;
        if ($departmentTargetId !== null) {
            $deptParent = DepartmentTarget::query()->find($departmentTargetId);
            if ($deptParent === null || $deptParent->department_id !== $division->department_id) {
                $departmentTargetId = null;
            }
        }

        if ($departmentTargetId === null && $parentDivisionTarget?->department_target_id !== null) {
            $departmentTargetId = $parentDivisionTarget->department_target_id;
        }

        $weightPercent = isset($validated['weight_percent']) && $validated['weight_percent'] !== ''
            ? round((float) $validated['weight_percent'], 2)
            : 100.0;

        $created = DivisionTarget::query()->create([
            'department_id' => $division->department_id,
            'division_id' => $division->id,
            'department_target_id' => $departmentTargetId,
            'parent_division_target_id' => $parentDivisionTargetId,
            'created_by' => $user->id,
            'title' => trim((string) $validated['title']),
            'description' => ! empty($validated['description']) ? trim((string) $validated['description']) : null,
            'period_type' => $periodType,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'target_value' => (int) $validated['target_value'],
            'approved_value' => 0,
            'progress_percent' => 0,
            'weight_percent' => $weightPercent,
            'unit_label' => trim((string) $validated['unit_label']),
            'status' => 'active',
        ]);

        $created->recalculateProgress();

        $monthParam = CarbonImmutable::parse((string) $validated['start_date'])->format('Y-m');

        return redirect()
            ->route('targets.index', ['month' => $monthParam])
            ->with('status', 'Target divisi dan bobot kontribusinya berhasil disimpan.');
    }

    public function updateWeight(Request $request, DivisionTarget $divisionTarget): RedirectResponse
    {
        $user = $request->user();
        $user->loadMissing('employee');

        $isAdminOrHr = $user->hasAnyRole([RoleName::HR_MANAGER->value, RoleName::SUPER_ADMIN->value]);
        $isDeptManager = $user->hasRole(RoleName::DEPT_MANAGER->value);
        $isDivisionHead = $user->hasRole(RoleName::DIVISION_HEAD->value);

        $authorized = $isAdminOrHr
            || ($isDeptManager && $divisionTarget->department_id === (int) $user->employee?->department_id)
            || ($isDivisionHead && $divisionTarget->division_id === (int) $user->employee?->division_id);

        if (! $authorized) {
            abort(403);
        }

        $validated = $request->validate([
            'weight_percent' => ['required', 'numeric', 'min:1', 'max:100'],
        ]);

        $divisionTarget->update([
            'weight_percent' => round((float) $validated['weight_percent'], 2),
        ]);

        $divisionTarget->recalculateProgress();

        return redirect()
            ->back()
            ->with('status', "Bobot target '{$divisionTarget->title}' diperbarui ke {$divisionTarget->weight_percent}%, progres induk otomatis dihitung ulang.");
    }
}
