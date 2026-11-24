<?php

declare(strict_types=1);

namespace App\Http\Controllers\Leave;

use App\Actions\Leave\BuildApprovalStepsAction;
use App\Actions\Leave\CancelLeaveRequestAction;
use App\Actions\Leave\SubmitLeaveRequestAction;
use App\Enums\LeaveRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\StoreLeaveRequest;
use App\Models\Holiday;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class LeaveRequestController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', LeaveRequest::class);

        $employee = $request->user()?->employee;

        if ($employee === null) {
            abort(403);
        }

        $timezone = (string) config('attendance.timezone', 'Asia/Jakarta');
        $currentYear = CarbonImmutable::now($timezone)->year;

        $statusParam = $request->query('status');
        $selectedStatus = is_string($statusParam)
            ? LeaveRequestStatus::tryFrom($statusParam)
            : null;

        $leaveRequests = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->with(['leaveType', 'approvals.approver'])
            ->when(
                $selectedStatus !== null,
                static fn ($query) => $query->where('status', $selectedStatus->value),
            )
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $leaveBalance = LeaveBalance::query()
            ->where('employee_id', $employee->id)
            ->where('year', $currentYear)
            ->first();

        return view('leave.index', [
            'employee' => $employee,
            'leaveRequests' => $leaveRequests,
            'leaveBalance' => $leaveBalance,
            'currentYear' => $currentYear,
            'selectedStatus' => $selectedStatus,
            'statuses' => LeaveRequestStatus::cases(),
            'timezone' => $timezone,
        ]);
    }

    public function create(Request $request, BuildApprovalStepsAction $buildApprovalSteps): View
    {
        Gate::authorize('create', LeaveRequest::class);

        $employee = $request->user()?->employee;

        if ($employee === null) {
            abort(403);
        }

        $employee->loadMissing(['user.roles', 'department', 'division', 'workSchedule']);

        $timezone = (string) config('attendance.timezone', 'Asia/Jakarta');
        $nowWib = CarbonImmutable::now($timezone);
        $currentYear = $nowWib->year;

        $leaveTypes = LeaveType::query()
            ->active()
            ->orderBy('id')
            ->get();

        $leaveBalance = LeaveBalance::query()
            ->where('employee_id', $employee->id)
            ->where('year', $currentYear)
            ->first();

        $approvalPreviews = $leaveTypes->mapWithKeys(function (LeaveType $leaveType) use ($buildApprovalSteps, $employee): array {
            $baseSteps = $this->formatPreviewSteps(
                $buildApprovalSteps->execute($employee, $leaveType, 1),
            );

            $threshold = $leaveType->hr_approval_after_days;
            $extendedSteps = $threshold !== null
                ? $this->formatPreviewSteps($buildApprovalSteps->execute($employee, $leaveType, $threshold + 1))
                : $baseSteps;

            return [
                $leaveType->id => [
                    'base' => $baseSteps,
                    'extended' => $extendedSteps,
                    'hr_after_days' => $threshold,
                ],
            ];
        })->all();

        $defaultLeaveType = $leaveTypes->first();
        $defaultPreviewSteps = $defaultLeaveType !== null
            ? ($approvalPreviews[$defaultLeaveType->id]['base'] ?? [])
            : [];

        $holidayDates = Holiday::query()
            ->whereYear('date', '>=', $currentYear)
            ->whereYear('date', '<=', $currentYear + 1)
            ->orderBy('date')
            ->get(['date'])
            ->map(static fn (Holiday $holiday): string => $holiday->date->toDateString())
            ->values()
            ->all();

        return view('leave.create', [
            'employee' => $employee,
            'leaveTypes' => $leaveTypes,
            'leaveBalance' => $leaveBalance,
            'currentYear' => $currentYear,
            'todayDate' => $nowWib->toDateString(),
            'approvalPreviews' => $approvalPreviews,
            'defaultPreviewSteps' => $defaultPreviewSteps,
            'workDays' => $employee->workSchedule?->work_days ?? [1, 2, 3, 4, 5],
            'holidayDates' => $holidayDates,
        ]);
    }

    public function store(StoreLeaveRequest $request, SubmitLeaveRequestAction $submitAction): RedirectResponse
    {
        $employee = $request->user()?->employee;

        if ($employee === null) {
            abort(403);
        }

        $leaveType = LeaveType::query()
            ->active()
            ->findOrFail($request->validated('leave_type_id'));

        $timezone = (string) config('attendance.timezone', 'Asia/Jakarta');

        $leaveRequest = $submitAction->execute(
            $employee,
            $leaveType,
            CarbonImmutable::parse((string) $request->validated('start_date'), $timezone),
            CarbonImmutable::parse((string) $request->validated('end_date'), $timezone),
            (string) $request->validated('reason'),
            $request->file('attachment'),
        );

        return redirect()
            ->route('leave.show', $leaveRequest)
            ->with('status', __('leave.messages.submitted', ['type' => $leaveType->name]));
    }

    public function show(Request $request, LeaveRequest $leaveRequest): View
    {
        $leaveRequest->load([
            'employee.user',
            'employee.department',
            'employee.division',
            'leaveType',
            'approvals.approver',
        ]);

        Gate::authorize('view', $leaveRequest);

        return view('leave.show', [
            'leaveRequest' => $leaveRequest,
        ]);
    }

    public function cancel(
        Request $request,
        LeaveRequest $leaveRequest,
        CancelLeaveRequestAction $cancelAction,
    ): RedirectResponse {
        $leaveRequest->loadMissing('employee');
        Gate::authorize('cancel', $leaveRequest);

        $cancelAction->execute($request->user(), $leaveRequest);

        return redirect()
            ->route('leave.show', $leaveRequest)
            ->with('status', __('leave.messages.cancelled'));
    }

    /**
     * @param  list<array{step: int, role: string}>  $steps
     * @return list<array{step: int, role: string, label: string}>
     */
    private function formatPreviewSteps(array $steps): array
    {
        return array_map(
            static fn (array $step): array => [
                'step' => (int) $step['step'],
                'role' => (string) $step['role'],
                'label' => __('ui.roles.'.$step['role']),
            ],
            $steps,
        );
    }
}
