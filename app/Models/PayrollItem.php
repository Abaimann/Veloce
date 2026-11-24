<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'payroll_period_id',
    'employee_id',
    'workdays_in_month',
    'present_days',
    'late_days',
    'late_minutes_total',
    'wfh_days',
    'paid_leave_days',
    'unpaid_leave_days',
    'absent_days',
    'base_salary',
    'fixed_allowance',
    'late_deduction',
    'absence_deduction',
    'net_salary',
    'calculation_breakdown',
])]
class PayrollItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'workdays_in_month' => 'integer',
            'present_days' => 'integer',
            'late_days' => 'integer',
            'late_minutes_total' => 'integer',
            'wfh_days' => 'integer',
            'paid_leave_days' => 'integer',
            'unpaid_leave_days' => 'integer',
            'absent_days' => 'integer',
            'base_salary' => 'integer',
            'fixed_allowance' => 'integer',
            'late_deduction' => 'integer',
            'absence_deduction' => 'integer',
            'net_salary' => 'integer',
            'calculation_breakdown' => 'array',
        ];
    }

    /**
     * @return BelongsTo<PayrollPeriod, $this>
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
