<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ApprovalStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'leave_request_id',
    'step',
    'role',
    'approver_id',
    'status',
    'note',
    'acted_at',
])]
class LeaveRequestApproval extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'step' => 'integer',
            'status' => ApprovalStatus::class,
            'acted_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<LeaveRequest, $this>
     */
    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
