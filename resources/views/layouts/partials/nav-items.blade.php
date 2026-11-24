@php
    $mobile = $mobile ?? false;
    $headingClass = $mobile
        ? 'mt-3 mb-1 px-3 text-[10px] font-extrabold tracking-[0.14em] uppercase text-on-brand/45'
        : 'sidebar-section-heading px-3 text-[10px] font-extrabold tracking-[0.14em] uppercase text-on-brand/45';
    $itemBase = $mobile
        ? 'flex min-h-11 items-center gap-3 rounded-2xl px-3.5 py-2.5 font-semibold'
        : 'sidebar-nav-item flex min-h-11 items-center gap-3 rounded-2xl px-3.5 py-2.5 font-semibold';
    $labelClass = $mobile ? 'truncate whitespace-nowrap' : 'sidebar-label truncate whitespace-nowrap';
@endphp

<p class="{{ $headingClass }} {{ $mobile ? 'mt-0' : '' }}">
    Operasional
</p>

<a
    href="{{ route('dashboard') }}"
    @if (! $mobile) x-bind:title="collapsed ? '{{ __('ui.nav.home') }}' : null" @endif
    class="{{ $itemBase }} {{ request()->routeIs('dashboard') ? 'veloce-nav-active' : 'veloce-nav-idle' }}"
>
    <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10.5L12 3l9 7.5V20a1 1 0 01-1 1h-5v-6H9v6H4a1 1 0 01-1-1v-9.5z"/></svg>
    <span class="{{ $labelClass }}">{{ __('ui.nav.home') }}</span>
</a>

@if (Route::has('attendance.camera') && $user?->employee)
    <a
        href="{{ route('attendance.camera') }}"
        @if (! $mobile) x-bind:title="collapsed ? '{{ __('ui.nav.attendance') }}' : null" @endif
        class="{{ $itemBase }} {{ request()->routeIs('attendance.camera') ? 'veloce-nav-checkin' : 'veloce-nav-idle' }}"
    >
        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8a2 2 0 012-2h2l1.5-2h7L17 6h2a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/><circle cx="12" cy="13" r="3.5"/></svg>
        <span class="{{ $labelClass }}">{{ __('ui.nav.attendance') }}</span>
    </a>
@endif

@if (Route::has('leave.index') && $user?->employee)
    <a
        href="{{ route('leave.index') }}"
        @if (! $mobile) x-bind:title="collapsed ? '{{ __('ui.nav.leave') }}' : null" @endif
        class="{{ $itemBase }} {{ request()->routeIs('leave.*') ? 'veloce-nav-active' : 'veloce-nav-idle' }}"
    >
        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><rect x="3" y="4" width="18" height="18" rx="3"/><path stroke-linecap="round" d="M16 2v4M8 2v4M3 10h18"/></svg>
        <span class="{{ $labelClass }}">{{ __('ui.nav.leave') }}</span>
    </a>
@endif

@if (Route::has('attendance.history') && $user?->employee)
    <a
        href="{{ route('attendance.history') }}"
        @if (! $mobile) x-bind:title="collapsed ? '{{ __('ui.nav.history') }}' : null" @endif
        class="{{ $itemBase }} {{ request()->routeIs('attendance.history') ? 'veloce-nav-active' : 'veloce-nav-idle' }}"
    >
        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span class="{{ $labelClass }}">{{ __('ui.nav.history') }}</span>
    </a>
@endif

@if (Route::has('timeline.index') || Route::has('targets.index') || (Route::has('work.index') && $user?->employee))
    <p class="{{ $headingClass }}">
        Target &amp; Tugas
    </p>
@endif

@if (Route::has('timeline.index'))
    <a
        href="{{ route('timeline.index') }}"
        @if (! $mobile) x-bind:title="collapsed ? 'Timeline Tugas' : null" @endif
        class="{{ $itemBase }} {{ request()->routeIs('timeline.*') ? 'veloce-nav-active' : 'veloce-nav-idle' }}"
    >
        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path stroke-linecap="round" stroke-linejoin="round" d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
        <span class="{{ $labelClass }}">Timeline Tugas</span>
    </a>
@endif

@if (Route::has('targets.index'))
    <a
        href="{{ route('targets.index') }}"
        @if (! $mobile) x-bind:title="collapsed ? 'Target Kerja' : null" @endif
        class="{{ $itemBase }} {{ request()->routeIs('targets.*') ? 'veloce-nav-active' : 'veloce-nav-idle' }}"
    >
        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/></svg>
        <span class="{{ $labelClass }}">Target Kerja</span>
    </a>
@endif

@if (Route::has('work.index') && $user?->employee)
    <a
        href="{{ route('work.index') }}"
        @if (! $mobile) x-bind:title="collapsed ? 'Kerjaan Harian (Drive PT)' : null" @endif
        class="{{ $itemBase }} {{ request()->routeIs('work.*') ? 'veloce-nav-active' : 'veloce-nav-idle' }}"
    >
        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
        <span class="{{ $labelClass }}">Kerjaan Harian</span>
    </a>
@endif

@if ($hasManagementSection)
    <p class="{{ $headingClass }}">
        Manajemen
    </p>
@endif

@if ($isApprover && Route::has('approvals.index'))
    <a
        href="{{ route('approvals.index') }}"
        @if (! $mobile) x-bind:title="collapsed ? '{{ __('ui.nav.approvals') }}' : null" @endif
        class="{{ $itemBase }} {{ request()->routeIs('approvals.*') ? 'veloce-nav-active' : 'veloce-nav-idle' }}"
    >
        <span class="relative shrink-0">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            @if (! $mobile && $pendingApprovalsCount > 0)
                <span class="sidebar-badge-dot absolute -top-1.5 -right-2 inline-flex min-w-4 items-center justify-center rounded-full bg-brand-green px-1 text-[10px] font-bold text-on-brand">
                    {{ $pendingApprovalsCount }}
                </span>
            @endif
        </span>
        <span class="{{ $mobile ? 'flex-1 truncate whitespace-nowrap' : 'sidebar-label flex-1 truncate whitespace-nowrap' }}">{{ __('ui.nav.approvals') }}</span>
        @if ($pendingApprovalsCount > 0)
            <span class="{{ $mobile ? 'ml-auto rounded-full bg-brand-green px-2 py-0.5 text-xs font-bold text-on-brand' : 'sidebar-label ml-auto rounded-full bg-brand-green px-2 py-0.5 text-xs font-bold text-on-brand' }}">{{ $pendingApprovalsCount }}</span>
        @endif
    </a>
@endif

@if ($isManagerOrHr && Route::has('management.attendances.index'))
    <a
        href="{{ route('management.attendances.index') }}"
        @if (! $mobile) x-bind:title="collapsed ? '{{ __('ui.nav.team_attendance') }}' : null" @endif
        class="{{ $itemBase }} {{ request()->routeIs('management.attendances.*') ? 'veloce-nav-active' : 'veloce-nav-idle' }}"
    >
        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-5-3.87M9 20H4v-2a4 4 0 015-3.87m6-4.13a4 4 0 11-8 0 4 4 0 018 0zm6 2a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        <span class="{{ $labelClass }}">{{ __('ui.nav.team_attendance') }}</span>
    </a>
@endif

@if ($user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['dept_manager', 'hr_manager', 'super_admin']) && Route::has('management.employees.index'))
    <a
        href="{{ route('management.employees.index') }}"
        @if (! $mobile) x-bind:title="collapsed ? '{{ __('ui.nav.employees') }}' : null" @endif
        class="{{ $itemBase }} {{ request()->routeIs('management.employees.*') ? 'veloce-nav-active' : 'veloce-nav-idle' }}"
    >
        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
        <span class="{{ $labelClass }}">{{ __('ui.nav.employees') }}</span>
    </a>
@endif

@if ($canViewPayroll && Route::has('payroll.index'))
    <a
        href="{{ route('payroll.index') }}"
        @if (! $mobile) x-bind:title="collapsed ? 'Gaji Sistem' : null" @endif
        class="{{ $itemBase }} {{ request()->routeIs('payroll.*') ? 'veloce-nav-active' : 'veloce-nav-idle' }}"
    >
        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span class="{{ $labelClass }}">Gaji Sistem</span>
    </a>
@endif

@if ($isSuperAdmin && Route::has('management.master.index'))
    <a
        href="{{ route('management.master.index') }}"
        @if (! $mobile) x-bind:title="collapsed ? '{{ __('ui.nav.master_data') }}' : null" @endif
        class="{{ $itemBase }} {{ request()->routeIs('management.master.*') ? 'veloce-nav-active' : 'veloce-nav-idle' }}"
    >
        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
        <span class="{{ $labelClass }}">{{ __('ui.nav.master_data') }}</span>
    </a>
@endif

@if ($isSuperAdmin && Route::has('management.activity-logs.index'))
    <a
        href="{{ route('management.activity-logs.index') }}"
        @if (! $mobile) x-bind:title="collapsed ? 'Log Aktivitas CRUD' : null" @endif
        class="{{ $itemBase }} {{ request()->routeIs('management.activity-logs.*') ? 'veloce-nav-active' : 'veloce-nav-idle' }}"
    >
        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
        <span class="{{ $labelClass }}">Log Aktivitas</span>
    </a>
@endif

@if (Route::has('profile.show'))
    <p class="{{ $headingClass }}">
        Akun
    </p>
    <a
        href="{{ route('profile.show') }}"
        @if (! $mobile) x-bind:title="collapsed ? '{{ __('ui.nav.profile') }}' : null" @endif
        class="{{ $itemBase }} {{ request()->routeIs('profile.*') ? 'veloce-nav-active' : 'veloce-nav-idle' }}"
    >
        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><circle cx="12" cy="8" r="4"/><path stroke-linecap="round" d="M5 20a7 7 0 0114 0"/></svg>
        <span class="{{ $labelClass }}">{{ __('ui.nav.profile') }}</span>
    </a>
@endif
