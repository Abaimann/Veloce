<?php

declare(strict_types=1);

namespace App\Actions\Leave;

use App\Enums\LeaveRequestStatus;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class CancelLeaveRequestAction
{
    /**
     * Membatalkan pengajuan izin/cuti/WFH milik pemohon selama statusnya masih menunggu persetujuan.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(User $actor, LeaveRequest $leaveRequest): LeaveRequest
    {
        Gate::forUser($actor)->authorize('cancel', $leaveRequest);

        return DB::transaction(function () use ($actor, $leaveRequest): LeaveRequest {
            $lockedRequest = LeaveRequest::query()
                ->with(['employee.user', 'leaveType'])
                ->whereKey($leaveRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRequest->employee?->user_id !== $actor->id) {
                throw new AuthorizationException;
            }

            if ($lockedRequest->status !== LeaveRequestStatus::PENDING) {
                throw ValidationException::withMessages([
                    'leave_request' => __('leave.messages.cannot_cancel'),
                ]);
            }

            $now = CarbonImmutable::now((string) config('attendance.timezone', 'Asia/Jakarta'));

            $lockedRequest->update([
                'status' => LeaveRequestStatus::CANCELLED,
                'cancelled_at' => $now,
            ]);

            activity()
                ->causedBy($actor)
                ->performedOn($lockedRequest)
                ->withProperties([
                    'status' => LeaveRequestStatus::CANCELLED->value,
                    'cancelled_at' => $now->toIso8601String(),
                ])
                ->log('Membatalkan pengajuan izin/cuti/WFH');

            return $lockedRequest->load(['employee.user', 'leaveType', 'approvals']);
        });
    }
}
