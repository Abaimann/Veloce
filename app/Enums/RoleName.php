<?php

declare(strict_types=1);

namespace App\Enums;

enum RoleName: string
{
    case SUPER_ADMIN = 'super_admin';
    case PAYROLL = 'payroll';
    case HR_MANAGER = 'hr_manager';
    case DEPT_MANAGER = 'dept_manager';
    case DIVISION_HEAD = 'division_head';
    case EMPLOYEE = 'employee';

    public function label(): string
    {
        return __('ui.roles.'.$this->value);
    }

    public function isApprover(): bool
    {
        return in_array($this, [
            self::DIVISION_HEAD,
            self::DEPT_MANAGER,
            self::HR_MANAGER,
            self::SUPER_ADMIN,
        ], true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $role): string => $role->value, self::cases());
    }
}
