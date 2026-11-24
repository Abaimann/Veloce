<?php

declare(strict_types=1);

namespace App\Actions\Leave;

use App\Enums\ApprovalStatus;
use App\Enums\LeaveRequestStatus;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestApproval;
use App\Models\User;
use App\Notifications\LeaveStatusUpdatedNotification;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class RejectLeaveRequestAction
{
    /**
     * Menolak pengajuan izin/cuti/WFH pada tahap aktif dan mengirim notifikasi ke pemohon.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(User $actor, LeaveRequest $leaveRequest, ?string $note = null): LeaveRequest
    {
        Gate::forUser($actor)->authorize('approve', $leaveRequest);

        return DB::transaction(function () use ($actor, $leaveRequest, $note): LeaveRequest {
            $lockedRequest = LeaveRequest::query()
                ->with(['employee.user', 'leaveType'])
                ->whereKey($leaveRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRequest->status !== LeaveRequestStatus::PENDING) {
                throw ValidationException::withMessages([
                    'leave_request' => __('leave.messages.already_finalized'),
                ]);
            }

            if ($lockedRequest->employee?->user_id === $actor->id) {
                throw ValidationException::withMessages([
                    'leave_request' => __('leave.messages.cannot_self_approve'),
                ]);
            }

            $lockedStep = LeaveRequestApproval::query()
                ->where('leave_request_id', $lockedRequest->id)
                ->where('step', $lockedRequest->current_step)
                ->lockForUpdate()
                ->first();

            if ($lockedStep === null || $lockedStep->status !== ApprovalStatus::PENDING) {
                throw ValidationException::withMessages([
                    'leave_request' => __('leave.messages.already_finalized'),
                ]);
            }

            $lockedRequest->setRelation('approvals', new Collection([$lockedStep]));

            if (! Gate::forUser($actor)->allows('approve', $lockedRequest)) {
                throw ValidationException::withMessages([
                    'leave_request' => __('leave.messages.already_finalized'),
                ]);
            }

            $now = CarbonImmutable::now((string) config('attendance.timezone', 'Asia/Jakarta'));
            $trimmedNote = $note !== null && trim($note) !== '' ? trim($note) : null;

            $lockedStep->update([
                'status' => ApprovalStatus::REJECTED,
                'approver_id' => $actor->id,
                'note' => $trimmedNote,
                'acted_at' => $now,
            ]);

            $lockedRequest->update([
                'status' => LeaveRequestStatus::REJECTED,
                'finalized_at' => $now,
            ]);
            $lockedRequest->unsetRelation('approvals');

            $applicantUser = $lockedRequest->employee?->user;

            if ($applicantUser !== null) {
                $leaveTypeName = $lockedRequest->leaveType?->name ?? 'Pengajuan';
                $noteSuffix = $trimmedNote !== null ? " Catatan: {$trimmedNote}" : '';

                $applicantUser->notify(new LeaveStatusUpdatedNotification(
                    $lockedRequest,
                    "{$leaveTypeName} Ditolak",
                    "Pengajuan {$leaveTypeName} Anda ({$lockedRequest->start_date->toDateString()} s.d. {$lockedRequest->end_date->toDateString()}) ditolak.{$noteSuffix}",
                ));
            }

            activity()
                ->causedBy($actor)
                ->performedOn($lockedRequest)
                ->withProperties([
                    'step' => $lockedStep->step,
                    'role' => $lockedStep->role,
                    'status' => LeaveRequestStatus::REJECTED->value,
                    'note' => $trimmedNote,
                ])
                ->log('Menolak pengajuan izin/cuti/WFH');

            return $lockedRequest->load(['employee.user', 'leaveType', 'approvals']);
        });
    }
}
