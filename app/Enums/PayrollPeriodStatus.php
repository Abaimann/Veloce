<?php

declare(strict_types=1);

namespace App\Enums;

enum PayrollPeriodStatus: string
{
    case DRAFT = 'draft';
    case FINALIZED = 'finalized';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draf Perhitungan',
            self::FINALIZED => 'Final',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::DRAFT => 'warning',
            self::FINALIZED => 'success',
        };
    }
}
