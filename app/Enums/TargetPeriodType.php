<?php

declare(strict_types=1);

namespace App\Enums;

enum TargetPeriodType: string
{
    case MONTHLY = 'monthly';
    case WEEKLY = 'weekly';
    case DAILY = 'daily';

    public function label(): string
    {
        return match ($this) {
            self::MONTHLY => 'Bulanan',
            self::WEEKLY => 'Mingguan',
            self::DAILY => 'Harian',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::MONTHLY => 'navy',
            self::WEEKLY => 'violet',
            self::DAILY => 'info',
        };
    }
}
