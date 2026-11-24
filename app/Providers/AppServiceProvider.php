<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Attendance;
use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\OfficeLocation;
use App\Models\WorkSchedule;
use App\Policies\AttendancePolicy;
use App\Policies\EmployeePolicy;
use App\Policies\LeaveRequestPolicy;
use App\Policies\MasterDataPolicy;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\View\View as ViewInstance;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Carbon::setLocale((string) config('app.locale', 'id'));
        CarbonImmutable::setLocale((string) config('app.locale', 'id'));
        Date::use(CarbonImmutable::class);

        Blade::component('layouts.guest', 'guest-layout');
        Blade::component('layouts.app', 'app-layout');

        Gate::policy(Attendance::class, AttendancePolicy::class);
        Gate::policy(Employee::class, EmployeePolicy::class);
        Gate::policy(LeaveRequest::class, LeaveRequestPolicy::class);
        Gate::policy(Department::class, MasterDataPolicy::class);
        Gate::policy(Division::class, MasterDataPolicy::class);
        Gate::policy(WorkSchedule::class, MasterDataPolicy::class);
        Gate::policy(OfficeLocation::class, MasterDataPolicy::class);
        Gate::policy(Holiday::class, MasterDataPolicy::class);

        View::composer('layouts.app', function (ViewInstance $view): void {
            $user = Auth::user();

            if ($user === null) {
                return;
            }

            $data = $view->getData();

            if (! isset($data['pendingApprovalsCount'])) {
                $pending = Schema::hasTable('leave_requests') && $user->primaryRole()?->isApprover()
                    ? LeaveRequest::query()->pendingForApprover($user)->count()
                    : 0;
                $view->with('pendingApprovalsCount', $pending);
            }

            if (! isset($data['unreadNotificationsCount'])) {
                $unread = Schema::hasTable('notifications')
                    ? $user->unreadNotifications()->count()
                    : 0;
                $view->with('unreadNotificationsCount', $unread);
            }
        });

        RateLimiter::for('login', function (Request $request): Limit {
            $email = Str::lower((string) $request->input('email', ''));

            return Limit::perMinute(5)
                ->by($email.'|'.$request->ip())
                ->response(function (Request $request, array $headers) {
                    $retryAfter = (int) ($headers['Retry-After'] ?? 60);

                    return back()
                        ->withInput($request->only('email', 'remember'))
                        ->withErrors([
                            'email' => __('auth.throttle', ['seconds' => $retryAfter]),
                        ]);
                });
        });

        RateLimiter::for('attendance', function (Request $request): Limit {
            $key = $request->user()?->getAuthIdentifier() ?: $request->ip();

            return Limit::perMinute(10)
                ->by((string) $key)
                ->response(function (Request $request) {
                    if ($request->expectsJson()) {
                        return response()->json([
                            'message' => __('attendance.messages.rate_limited'),
                        ], 429);
                    }

                    return back()->with('error', __('attendance.messages.rate_limited'));
                });
        });
    }
}
