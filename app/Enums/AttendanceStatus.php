<?php

declare(strict_types=1);

namespace App\Enums;

enum AttendanceStatus: string
{
    case PRESENT = 'present';
    case LATE = 'late';
    case WFH = 'wfh';
    case LEAVE = 'leave';
    case SICK = 'sick';
    case PERMISSION = 'permission';

    public function label(): string
    {
        return __('attendance.statuses.'.$this->value);
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::PRESENT => 'success',
            self::LATE => 'warning',
            self::WFH => 'info',
            self::LEAVE, self::SICK, self::PERMISSION => 'violet',
        };
    }

    public function blocksSelfieCheckIn(): bool
    {
        return in_array($this, [self::LEAVE, self::SICK, self::PERMISSION], true);
    }
}
