<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'name',
    'attendance_status',
    'is_paid',
    'requires_attachment',
    'deducts_balance',
    'hr_approval_after_days',
    'is_active',
])]
class LeaveType extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attendance_status' => AttendanceStatus::class,
            'is_paid' => 'boolean',
            'requires_attachment' => 'boolean',
            'deducts_balance' => 'boolean',
            'hr_approval_after_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<LeaveRequest, $this>
     */
    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /**
     * @param  Builder<LeaveType>  $query
     * @return Builder<LeaveType>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isWfh(): bool
    {
        return $this->code === 'wfh';
    }
}
