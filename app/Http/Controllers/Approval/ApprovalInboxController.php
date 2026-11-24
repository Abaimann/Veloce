<?php

declare(strict_types=1);

namespace App\Http\Controllers\Approval;

use App\Actions\Leave\ApproveLeaveRequestAction;
use App\Actions\Leave\RejectLeaveRequestAction;
use App\Enums\LeaveRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\DecideLeaveApprovalRequest;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestApproval;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApprovalInboxController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        if ($user === null || ! ($user->primaryRole()?->isApprover() ?? false)) {
            abort(403);
        }

        $pendingRequests = LeaveRequest::query()
            ->pendingForApprover($user)
            ->with(['employee.user', 'employee.department', 'employee.division', 'leaveType', 'approvals.approver'])
            ->latest()
            ->get();

        $historyApprovals = LeaveRequestApproval::query()
            ->where('approver_id', $user->id)
            ->with(['leaveRequest.employee.user', 'leaveRequest.employee.department', 'leaveRequest.leaveType', 'leaveRequest.approvals'])
            ->latest('acted_at')
            ->limit(15)
            ->get();

        return view('approvals.index', [
            'pendingRequests' => $pendingRequests,
            'historyApprovals' => $historyApprovals,
            'timezone' => (string) config('attendance.timezone', 'Asia/Jakarta'),
        ]);
    }

    public function approve(
        DecideLeaveApprovalRequest $request,
        LeaveRequest $leaveRequest,
        ApproveLeaveRequestAction $action,
    ): RedirectResponse {
        $updated = $action->execute($request->user(), $leaveRequest, $request->validated('note'));

        $message = $updated->status === LeaveRequestStatus::APPROVED
            ? __('leave.messages.approved_final')
            : __('leave.messages.approved_step');

        return redirect()
            ->route('approvals.index')
            ->with('status', $message);
    }

    public function reject(
        DecideLeaveApprovalRequest $request,
        LeaveRequest $leaveRequest,
        RejectLeaveRequestAction $action,
    ): RedirectResponse {
        $action->execute($request->user(), $leaveRequest, $request->validated('note'));

        return redirect()
            ->route('approvals.index')
            ->with('status', __('leave.messages.rejected'));
    }
}
