<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

class LeaveSubmittedForApprovalNotification extends Notification
{
    public function __construct(
        public readonly LeaveRequest $leaveRequest,
        public readonly int $step,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $this->leaveRequest->loadMissing(['employee.user', 'leaveType']);

        $applicantName = $this->leaveRequest->employee?->user?->name ?? 'Karyawan';
        $leaveTypeName = $this->leaveRequest->leaveType?->name ?? 'Izin/Cuti';
        $startDate = $this->leaveRequest->start_date->toDateString();
        $endDate = $this->leaveRequest->end_date->toDateString();
        $totalWorkDays = $this->leaveRequest->total_work_days;

        $url = Route::has('approvals.index')
            ? route('approvals.index', [], false)
            : '/persetujuan';

        return [
            'leave_request_id' => $this->leaveRequest->id,
            'applicant_name' => $applicantName,
            'leave_type_name' => $leaveTypeName,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_work_days' => $totalWorkDays,
            'step' => $this->step,
            'title' => "Menunggu Persetujuan: {$leaveTypeName}",
            'message' => "{$applicantName} mengajukan {$leaveTypeName} ({$startDate} s.d. {$endDate}, {$totalWorkDays} hari kerja).",
            'url' => $url,
        ];
    }
}
