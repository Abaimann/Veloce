<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

class LeaveStatusUpdatedNotification extends Notification
{
    public function __construct(
        public readonly LeaveRequest $leaveRequest,
        public readonly string $headline,
        public readonly string $message,
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
        $url = Route::has('leave.show')
            ? route('leave.show', $this->leaveRequest, false)
            : '/izin/'.$this->leaveRequest->id;

        return [
            'leave_request_id' => $this->leaveRequest->id,
            'status' => $this->leaveRequest->status->value,
            'title' => $this->headline,
            'message' => $this->message,
            'url' => $url,
        ];
    }
}
