<?php

declare(strict_types=1);

namespace App\Actions\Leave;

use App\Enums\ApprovalStatus;
use App\Enums\LeaveRequestStatus;
use App\Models\Attendance;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestApproval;
use App\Models\User;
use App\Notifications\LeaveStatusUpdatedNotification;
use App\Notifications\LeaveSubmittedForApprovalNotification;
use App\Support\Attendance\WorkdayCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

final class ApproveLeaveRequestAction
{
    public function __construct(
        private readonly WorkdayCalculator $workdayCalculator,
        private readonly ?BuildApprovalStepsAction $buildApprovalSteps = null,
    ) {}

    /**
     * Menyetujui langkah aktif pengajuan izin/cuti/WFH dan memfinalisasi saldo serta proyeksi absensi pada tahap terakhir.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(User $actor, LeaveRequest $leaveRequest, ?string $note = null): LeaveRequest
    {
        Gate::forUser($actor)->authorize('approve', $leaveRequest);

        return DB::transaction(function () use ($actor, $leaveRequest, $note): LeaveRequest {
            $lockedRequest = LeaveRequest::query()
                ->with(['employee.user', 'employee.workSchedule', 'leaveType'])
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
                'status' => ApprovalStatus::APPROVED,
                'approver_id' => $actor->id,
                'note' => $trimmedNote,
                'acted_at' => $now,
            ]);

            $nextStepNumber = $lockedRequest->current_step + 1;
            $nextStep = LeaveRequestApproval::query()
                ->where('leave_request_id', $lockedRequest->id)
                ->where('step', $nextStepNumber)
                ->lockForUpdate()
                ->first();

            $leaveType = $lockedRequest->leaveType;
            $leaveTypeName = $leaveType?->name ?? 'Pengajuan';
            $applicantUser = $lockedRequest->employee?->user;

            if ($nextStep !== null) {
                $lockedRequest->update([
                    'current_step' => $nextStepNumber,
                ]);
                $lockedRequest->unsetRelation('approvals');

                if ($lockedRequest->employee !== null) {
                    $stepsBuilder = $this->buildApprovalSteps ?? new BuildApprovalStepsAction;
                    $nextApprovers = $stepsBuilder->resolveApproversForRole($nextStep->role, $lockedRequest->employee);

                    if ($nextApprovers->isNotEmpty()) {
                        Notification::send(
                            $nextApprovers,
                            new LeaveSubmittedForApprovalNotification($lockedRequest, $nextStepNumber),
                        );
                    }
                }

                if ($applicantUser !== null) {
                    $applicantUser->notify(new LeaveStatusUpdatedNotification(
                        $lockedRequest,
                        "Persetujuan Tahap {$lockedStep->step} Disetujui",
                        "Pengajuan {$leaveTypeName} Anda telah disetujui pada tahap {$lockedStep->step} dan diteruskan ke tahap berikutnya.",
                    ));
                }
            } else {
                if ($leaveType !== null && $leaveType->deducts_balance) {
                    $balance = LeaveBalance::query()
                        ->where('employee_id', $lockedRequest->employee_id)
                        ->where('year', $lockedRequest->start_date->year)
                        ->lockForUpdate()
                        ->first();

                    $available = $balance?->remainingDays() ?? 0;

                    if ($balance === null || $available < $lockedRequest->total_work_days) {
                        throw ValidationException::withMessages([
                            'total_work_days' => __('leave.messages.insufficient_balance', [
                                'available' => $available,
                                'required' => $lockedRequest->total_work_days,
                            ]),
                        ]);
                    }

                    $balance->increment('used_days', $lockedRequest->total_work_days);
                }

                $lockedRequest->update([
                    'status' => LeaveRequestStatus::APPROVED,
                    'finalized_at' => $now,
                ]);
                $lockedRequest->unsetRelation('approvals');

                if ($leaveType !== null && ! $leaveType->isWfh() && $lockedRequest->employee !== null) {
                    $workdays = $this->workdayCalculator->workdaysInRange(
                        $lockedRequest->employee,
                        $lockedRequest->start_date,
                        $lockedRequest->end_date,
                    );

                    foreach ($workdays as $workday) {
                        $workDateString = $workday->toDateString();

                        $attendance = Attendance::query()
                            ->where('employee_id', $lockedRequest->employee_id)
                            ->whereDate('work_date', $workDateString)
                            ->lockForUpdate()
                            ->first();

                        if ($attendance !== null) {
                            $attendance->update([
                                'status' => $leaveType->attendance_status,
                                'leave_request_id' => $lockedRequest->id,
                            ]);
                        } else {
                            Attendance::query()->create([
                                'employee_id' => $lockedRequest->employee_id,
                                'work_date' => $workDateString,
                                'status' => $leaveType->attendance_status,
                                'leave_request_id' => $lockedRequest->id,
                            ]);
                        }
                    }
                }

                if ($applicantUser !== null) {
                    $applicantUser->notify(new LeaveStatusUpdatedNotification(
                        $lockedRequest,
                        "{$leaveTypeName} Disetujui",
                        "Pengajuan {$leaveTypeName} Anda ({$lockedRequest->start_date->toDateString()} s.d. {$lockedRequest->end_date->toDateString()}) telah disetujui secara final.",
                    ));
                }
            }

            activity()
                ->causedBy($actor)
                ->performedOn($lockedRequest)
                ->withProperties([
                    'step' => $lockedStep->step,
                    'role' => $lockedStep->role,
                    'is_final' => $nextStep === null,
                    'status' => $lockedRequest->status->value,
                    'note' => $trimmedNote,
                ])
                ->log($nextStep === null ? 'Menyetujui pengajuan izin/cuti/WFH secara final' : 'Menyetujui tahap persetujuan izin/cuti/WFH');

            return $lockedRequest->load(['employee.user', 'leaveType', 'approvals']);
        });
    }
}
