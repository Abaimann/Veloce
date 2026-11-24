<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payroll;

use App\Actions\Payroll\CalculateMonthlyPayrollAction;
use App\Enums\PayrollPeriodStatus;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PayrollController extends Controller
{
    public function index(Request $request, CalculateMonthlyPayrollAction $calculateAction): View
    {
        $user = $request->user();

        if ($user === null || ! $user->hasAnyRole([
            RoleName::SUPER_ADMIN->value,
            RoleName::HR_MANAGER->value,
            RoleName::PAYROLL->value,
        ])) {
            abort(403);
        }

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

        $period = PayrollPeriod::query()
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        // Hitung otomatis secara transparan oleh sistem bila periode belum pernah di-generate.
        if ($period === null) {
            $period = $calculateAction->execute($year, $month, $user);
        }

        $search = is_string($request->query('q')) ? trim($request->query('q')) : '';
        $rawDepartmentId = $request->query('department_id');
        $selectedDepartmentId = is_scalar($rawDepartmentId) && ctype_digit((string) $rawDepartmentId) && (int) $rawDepartmentId > 0
            ? (int) $rawDepartmentId
            : null;

        $itemsQuery = PayrollItem::query()
            ->where('payroll_period_id', $period->id)
            ->with(['employee.user', 'employee.department', 'employee.division'])
            ->when($search !== '', static function (Builder $query) use ($search): void {
                $pattern = '%'.Str::lower($search).'%';
                $query->whereHas('employee', static function (Builder $emp) use ($pattern): void {
                    $emp->whereRaw('LOWER(employee_number) LIKE ?', [$pattern])
                        ->orWhereHas('user', static function (Builder $u) use ($pattern): void {
                            $u->whereRaw('LOWER(name) LIKE ?', [$pattern]);
                        });
                });
            })
            ->when($selectedDepartmentId !== null, static function (Builder $query) use ($selectedDepartmentId): void {
                $query->whereHas('employee', static fn (Builder $emp): Builder => $emp->where('department_id', $selectedDepartmentId));
            });

        $allItems = (clone $itemsQuery)->get();
        $items = $itemsQuery->orderBy('id')->paginate(20)->withQueryString();

        $summary = [
            'employee_count' => $allItems->count(),
            'total_gross' => $allItems->sum(static fn (PayrollItem $i): int => $i->base_salary + $i->fixed_allowance),
            'total_deduction' => $allItems->sum(static fn (PayrollItem $i): int => $i->late_deduction + $i->absence_deduction),
            'total_net' => $allItems->sum('net_salary'),
        ];

        $departments = Department::query()->active()->orderBy('name')->get(['id', 'code', 'name']);

        return view('payroll.index', [
            'period' => $period,
            'items' => $items,
            'summary' => $summary,
            'departments' => $departments,
            'selectedMonth' => sprintf('%04d-%02d', $year, $month),
            'selectedDepartmentId' => $selectedDepartmentId,
            'search' => $search,
            'lateDeductionRate' => (int) config('attendance.payroll.late_deduction_per_incident', 50000),
        ]);
    }

    public function recalculate(Request $request, CalculateMonthlyPayrollAction $calculateAction): RedirectResponse
    {
        $user = $request->user();

        if ($user === null || ! $user->hasAnyRole([
            RoleName::SUPER_ADMIN->value,
            RoleName::HR_MANAGER->value,
            RoleName::PAYROLL->value,
        ])) {
            abort(403);
        }

        $validated = $request->validate([
            'month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        [$year, $month] = array_map('intval', explode('-', (string) $validated['month']));

        $period = $calculateAction->execute($year, $month, $user);

        return redirect()
            ->route('payroll.index', ['month' => $validated['month']])
            ->with('status', "Perhitungan gaji sistem periode {$period->label} berhasil diperbarui.");
    }

    public function finalize(Request $request, PayrollPeriod $payrollPeriod): RedirectResponse
    {
        $user = $request->user();

        if ($user === null || ! $user->hasAnyRole([
            RoleName::SUPER_ADMIN->value,
            RoleName::HR_MANAGER->value,
            RoleName::PAYROLL->value,
        ])) {
            abort(403);
        }

        $payrollPeriod->update([
            'status' => PayrollPeriodStatus::FINALIZED,
            'finalized_at' => CarbonImmutable::now((string) config('attendance.timezone', 'Asia/Jakarta')),
        ]);

        return redirect()
            ->route('payroll.index', ['month' => sprintf('%04d-%02d', $payrollPeriod->year, $payrollPeriod->month)])
            ->with('status', "Periode gaji {$payrollPeriod->label} telah ditandai Final.");
    }
}
