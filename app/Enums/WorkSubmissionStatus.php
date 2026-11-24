<?php

declare(strict_types=1);

namespace App\Enums;

enum WorkSubmissionStatus: string
{
    case PENDING_REVIEW = 'pending_review';
    case APPROVED = 'approved';
    case REVISION_REQUESTED = 'revision_requested';

    public function label(): string
    {
        return match ($this) {
            self::PENDING_REVIEW => 'Menunggu Konfirmasi',
            self::APPROVED => 'Disetujui & Dicentang',
            self::REVISION_REQUESTED => 'Perlu Revisi',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::PENDING_REVIEW => 'warning',
            self::APPROVED => 'success',
            self::REVISION_REQUESTED => 'danger',
        };
    }
}
