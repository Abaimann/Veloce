<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Profile\UpdatePasswordRequest;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();
        $user->loadMissing([
            'roles',
            'employee.department',
            'employee.division',
            'employee.supervisor.user',
            'employee.workSchedule',
        ]);

        $timezone = (string) config('attendance.timezone', 'Asia/Jakarta');
        $nowWib = CarbonImmutable::now($timezone);
        $employee = $user->employee;

        $leaveBalance = $employee !== null
            ? LeaveBalance::query()
                ->where('employee_id', $employee->id)
                ->where('year', $nowWib->year)
                ->first()
            : null;

        $isApprover = $user->primaryRole()?->isApprover() ?? false;
        $pendingApprovalsCount = $isApprover
            ? LeaveRequest::pendingForApprover($user)->count()
            : 0;

        $unreadNotificationsCount = $user->unreadNotifications()->count();

        return view('profile.show', [
            'user' => $user,
            'employee' => $employee,
            'leaveBalance' => $leaveBalance,
            'currentYear' => $nowWib->year,
            'timezone' => $timezone,
            'pendingApprovalsCount' => $pendingApprovalsCount,
            'unreadNotificationsCount' => $unreadNotificationsCount,
        ]);
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->update([
            'password' => (string) $request->validated('password'),
        ]);

        $request->session()->regenerate();

        activity()
            ->causedBy($user)
            ->performedOn($user)
            ->withProperties([
                'ip_address' => $request->ip(),
            ])
            ->log('Memperbarui kata sandi akun');

        return redirect()
            ->route('profile.show')
            ->with('status', __('auth.password_updated'));
    }
}
