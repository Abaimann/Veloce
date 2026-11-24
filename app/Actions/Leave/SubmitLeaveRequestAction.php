<?php

declare(strict_types=1);

namespace App\Actions\Leave;

use App\Enums\ApprovalStatus;
use App\Enums\AttendanceStatus;
use App\Enums\LeaveRequestStatus;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestApproval;
use App\Models\LeaveType;
use App\Notifications\LeaveSubmittedForApprovalNotification;
use App\Support\Attendance\WorkdayCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class SubmitLeaveRequestAction
{
    public function __construct(
        private readonly WorkdayCalculator $workdayCalculator,
        private readonly BuildApprovalStepsAction $buildApprovalSteps,
    ) {}

    /**
     * Memvalidasi pengajuan izin/cuti/WFH, menyimpan lampiran privat, dan membuat langkah persetujuan awal.
     *
     * @throws ValidationException
     */
    public function execute(
        Employee $employee,
        LeaveType $leaveType,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
        string $reason,
        ?UploadedFile $attachment = null,
    ): LeaveRequest {
        $startDay = $startDate->startOfDay();
        $endDay = $endDate->startOfDay();

        if ($endDay->lt($startDay)) {
            throw ValidationException::withMessages([
                'end_date' => __('validation.after_or_equal', [
                    'attribute' => __('validation.attributes.end_date'),
                    'date' => $startDay->toDateString(),
                ]),
            ]);
        }

        if (config('attendance.require_same_year_leave_request', true) && $startDay->year !== $endDay->year) {
            throw ValidationException::withMessages([
                'end_date' => __('leave.messages.cross_year_not_allowed'),
            ]);
        }

        $employee->loadMissing(['workSchedule', 'user']);

        $totalWorkDays = $this->workdayCalculator->countWorkdays($employee, $startDay, $endDay);

        if ($totalWorkDays < 1) {
            throw ValidationException::withMessages([
                'start_date' => __('leave.messages.no_workdays'),
            ]);
        }

        if ($leaveType->requires_attachment && $attachment === null) {
            throw ValidationException::withMessages([
                'attachment' => __('leave.messages.attachment_required', [
                    'type' => $leaveType->name,
                ]),
            ]);
        }

        return DB::transaction(function () use (
            $employee,
            $leaveType,
            $startDay,
            $endDay,
            $totalWorkDays,
            $reason,
            $attachment,
        ): LeaveRequest {
            // Kunci baris karyawan agar pengajuan ganda pada rentang tanggal yang sama terurut aman.
            Employee::query()->whereKey($employee->id)->lockForUpdate()->first();

            $hasOverlap = LeaveRequest::query()
                ->where('employee_id', $employee->id)
                ->whereIn('status', [
                    LeaveRequestStatus::PENDING->value,
                    LeaveRequestStatus::APPROVED->value,
                ])
                ->whereDate('start_date', '<=', $endDay->toDateString())
                ->whereDate('end_date', '>=', $startDay->toDateString())
                ->exists();

            if ($hasOverlap) {
                throw ValidationException::withMessages([
                    'start_date' => __('leave.messages.overlap_exists'),
                ]);
            }

            $hasAttendanceConflict = Attendance::query()
                ->where('employee_id', $employee->id)
                ->whereDate('work_date', '>=', $startDay->toDateString())
                ->whereDate('work_date', '<=', $endDay->toDateString())
                ->where(static function (Builder $query): void {
                    $query->whereNotNull('check_in_at')
                        ->orWhereIn('status', [
                            AttendanceStatus::LEAVE->value,
                            AttendanceStatus::SICK->value,
                            AttendanceStatus::PERMISSION->value,
                        ]);
                })
                ->exists();

            if ($hasAttendanceConflict) {
                throw ValidationException::withMessages([
                    'start_date' => __('leave.messages.attendance_exists'),
                ]);
            }

            if ($leaveType->deducts_balance) {
                $balance = LeaveBalance::query()->firstOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'year' => $startDay->year,
                    ],
                    [
                        'entitled_days' => $employee->employment_type->defaultAnnualLeaveDays(),
                        'used_days' => 0,
                    ],
                );

                $lockedBalance = LeaveBalance::query()
                    ->whereKey($balance->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $available = $lockedBalance->remainingDays();

                if ($available < $totalWorkDays) {
                    throw ValidationException::withMessages([
                        'total_work_days' => __('leave.messages.insufficient_balance', [
                            'available' => $available,
                            'required' => $totalWorkDays,
                        ]),
                    ]);
                }
            }

            $disk = (string) config('attendance.private_disk', 'local');
            $attachmentPath = $attachment !== null ? $this->storeAttachment($attachment, $disk) : null;

            try {
                $steps = $this->buildApprovalSteps->execute($employee, $leaveType, $totalWorkDays);

                $leaveRequest = LeaveRequest::query()->create([
                    'employee_id' => $employee->id,
                    'leave_type_id' => $leaveType->id,
                    'start_date' => $startDay->toDateString(),
                    'end_date' => $endDay->toDateString(),
                    'total_work_days' => $totalWorkDays,
                    'reason' => trim($reason),
                    'attachment_path' => $attachmentPath,
                    'status' => LeaveRequestStatus::PENDING,
                    'current_step' => 1,
                ]);

                foreach ($steps as $stepData) {
                    LeaveRequestApproval::query()->create([
                        'leave_request_id' => $leaveRequest->id,
                        'step' => $stepData['step'],
                        'role' => $stepData['role'],
                        'status' => ApprovalStatus::PENDING,
                    ]);
                }

                $leaveRequest->load(['employee.user', 'leaveType', 'approvals']);

                $firstStep = $steps[0] ?? null;

                if ($firstStep !== null) {
                    $approvers = $this->buildApprovalSteps->resolveApproversForRole($firstStep['role'], $employee);

                    if ($approvers->isNotEmpty()) {
                        Notification::send(
                            $approvers,
                            new LeaveSubmittedForApprovalNotification($leaveRequest, $firstStep['step']),
                        );
                    }
                }
            } catch (Throwable $exception) {
                if ($attachmentPath !== null) {
                    Storage::disk($disk)->delete($attachmentPath);
                }

                throw $exception;
            }

            activity()
                ->causedBy($employee->user)
                ->performedOn($leaveRequest)
                ->withProperties([
                    'leave_type_code' => $leaveType->code,
                    'start_date' => $startDay->toDateString(),
                    'end_date' => $endDay->toDateString(),
                    'total_work_days' => $totalWorkDays,
                ])
                ->log('Mengajukan izin/cuti/WFH');

            return $leaveRequest;
        });
    }

    private function storeAttachment(UploadedFile $attachment, string $disk): string
    {
        $now = CarbonImmutable::now((string) config('attendance.timezone', 'Asia/Jakarta'));
        $extension = strtolower($attachment->getClientOriginalExtension() ?: ($attachment->extension() ?: 'bin'));
        $directory = sprintf('leave-attachments/%s/%s', $now->format('Y'), $now->format('m'));
        $filename = Str::uuid()->toString().'.'.$extension;

        $storedPath = $attachment->storeAs($directory, $filename, $disk);

        if ($storedPath === false) {
            throw new RuntimeException('Gagal menyimpan lampiran pengajuan ke penyimpanan privat.');
        }

        return $storedPath;
    }
}
