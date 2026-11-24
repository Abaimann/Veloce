<?php

declare(strict_types=1);

namespace App\Enums;

enum LeaveRequestStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return __('leave.statuses.'.$this->value);
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::APPROVED => 'success',
            self::REJECTED => 'danger',
            self::CANCELLED => 'neutral',
        };
    }
}
