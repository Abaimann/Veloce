<?php

declare(strict_types=1);

namespace App\Enums;

enum EmploymentType: string
{
    case STAFF = 'staff';
    case INTERN = 'intern';
    case EXECUTIVE = 'executive';

    public function label(): string
    {
        return __('ui.employment_types.'.$this->value);
    }

    public function defaultAnnualLeaveDays(): int
    {
        return (int) config('attendance.default_annual_leave_days.'.$this->value, 0);
    }
}
