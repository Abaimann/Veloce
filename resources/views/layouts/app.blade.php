@props([
    'title' => config('app.name'),
    'subtitle' => null,
    'pendingApprovalsCount' => null,
    'unreadNotificationsCount' => null,
    'hideBottomNav' => false,
])

@php
    $user = auth()->user();
    $primaryRole = $user && method_exists($user, 'primaryRole') ? $user->primaryRole() : null;
    $isApprover = $user && method_exists($user, 'hasAnyRole')
        ? $user->hasAnyRole(['division_head', 'dept_manager', 'hr_manager', 'super_admin'])
        : false;
    $isManagerOrHr = $user && method_exists($user, 'hasAnyRole')
        ? $user->hasAnyRole(['division_head', 'dept_manager', 'hr_manager', 'super_admin'])
        : false;
    $isPayroll = $user && method_exists($user, 'hasRole')
        ? $user->hasRole('payroll')
        : false;
    $isSuperAdmin = $user && method_exists($user, 'hasRole')
        ? $user->hasRole('super_admin')
        : false;
    $initials = collect(explode(' ', trim((string) ($user?->name ?? 'V'))))
        ->filter()
        ->take(2)
        ->map(static fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
@endphp

<!DOCTYPE html>
<html lang="id" class="h-full antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="">
    @if (file_exists(public_path('manifest.webmanifest')))
        <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    @endif
    @if (file_exists(public_path('icons/icon-192.png')))
        <link rel="icon" type="image/png" href="{{ asset('icons/icon-192.png') }}">
        <link rel="apple-touch-icon" href="{{ asset('icons/icon-192.png') }}">
    @endif

    <title>{{ $title }} — {{ config('app.name') }}</title>

    <script>
        try {
            if (window.localStorage.getItem('veloce_sidebar_collapsed') === '1') {
                document.documentElement.dataset.sidebarCollapsed = 'true';
            }
        } catch (e) {}
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-surface text-text">
    <x-toast />

    <div class="min-h-screen lg:flex" x-data="sidebarShell()">
        {{-- Desktop Left Sidebar --}}
        <aside class="sidebar-desktop hidden lg:sticky lg:top-0 lg:z-20 lg:flex lg:h-screen lg:w-64 lg:shrink-0 lg:flex-col lg:border-r lg:border-border lg:bg-card">
            <button
                type="button"
                x-on:click="toggle()"
                x-bind:aria-expanded="(!collapsed).toString()"
                x-bind:aria-label="collapsed ? 'Perluas sidebar' : 'Kecilkan sidebar'"
                x-bind:title="collapsed ? 'Perluas sidebar' : 'Kecilkan sidebar'"
                class="absolute top-[58px] -right-3.5 z-30 flex h-7 w-7 cursor-pointer items-center justify-center rounded-full border border-border bg-card text-muted shadow-sm transition-all duration-200 hover:scale-105 hover:border-brand-navy/35 hover:text-brand-ink hover:shadow"
            >
                <svg
                    class="h-4 w-4 transition-transform duration-300"
                    :class="collapsed ? 'rotate-180' : ''"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2.2"
                    aria-hidden="true"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
            </button>

            <div class="flex h-full w-full flex-col overflow-hidden">
                <div class="sidebar-brand-header flex h-[72px] shrink-0 items-center border-b border-border px-5">
                    <a
                        href="{{ route('dashboard') }}"
                        class="sidebar-brand-clip flex items-center overflow-hidden"
                        x-bind:title="collapsed ? '{{ config('app.name') }}' : null"
                    >
                        <x-brand-logo size="md" />
                    </a>
                </div>

                <nav class="flex-1 space-y-1 overflow-x-hidden overflow-y-auto p-3.5 text-sm" aria-label="Navigasi Utama">
                    <a
                        href="{{ route('dashboard') }}"
                        x-bind:title="collapsed ? '{{ __('ui.nav.home') }}' : null"
                        class="sidebar-nav-item flex min-h-11 items-center gap-3 rounded-2xl px-4 py-2.5 font-semibold {{ request()->routeIs('dashboard') ? 'bg-brand-navy text-on-brand shadow-xs' : 'text-muted hover:bg-surface hover:text-text' }}"
                    >
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10.5L12 3l9 7.5V20a1 1 0 01-1 1h-5v-6H9v6H4a1 1 0 01-1-1v-9.5z"/></svg>
                        <span class="sidebar-label truncate whitespace-nowrap">{{ __('ui.nav.home') }}</span>
                    </a>

                    @if (Route::has('attendance.camera') && ! $isPayroll && $user?->employee)
                        <a
                            href="{{ route('attendance.camera') }}"
                            x-bind:title="collapsed ? '{{ __('ui.nav.attendance') }}' : null"
                            class="sidebar-nav-item flex min-h-11 items-center gap-3 rounded-2xl px-4 py-2.5 font-semibold {{ request()->routeIs('attendance.camera') ? 'bg-brand-green-strong text-on-brand shadow-xs' : 'text-muted hover:bg-brand-green-soft/60 hover:text-success-ink' }}"
                        >
                            <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8a2 2 0 012-2h2l1.5-2h7L17 6h2a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/><circle cx="12" cy="13" r="3.5"/></svg>
                            <span class="sidebar-label truncate whitespace-nowrap">{{ __('ui.nav.attendance') }}</span>
                        </a>
                    @endif

                    @if (Route::has('leave.index') && ! $isPayroll && $user?->employee)
                        <a
                            href="{{ route('leave.index') }}"
                            x-bind:title="collapsed ? '{{ __('ui.nav.leave') }}' : null"
                            class="sidebar-nav-item flex min-h-11 items-center gap-3 rounded-2xl px-4 py-2.5 font-semibold {{ request()->routeIs('leave.*') ? 'bg-brand-navy text-on-brand shadow-xs' : 'text-muted hover:bg-surface hover:text-text' }}"
                        >
                            <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><rect x="3" y="4" width="18" height="18" rx="3"/><path stroke-linecap="round" d="M16 2v4M8 2v4M3 10h18"/></svg>
                            <span class="sidebar-label truncate whitespace-nowrap">{{ __('ui.nav.leave') }}</span>
                        </a>
                    @endif

                    @if (Route::has('attendance.history') && ! $isPayroll && $user?->employee)
                        <a
                            href="{{ route('attendance.history') }}"
                            x-bind:title="collapsed ? '{{ __('ui.nav.history') }}' : null"
                            class="sidebar-nav-item flex min-h-11 items-center gap-3 rounded-2xl px-4 py-2.5 font-semibold {{ request()->routeIs('attendance.history') ? 'bg-brand-navy text-on-brand shadow-xs' : 'text-muted hover:bg-surface hover:text-text' }}"
                        >
                            <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span class="sidebar-label truncate whitespace-nowrap">{{ __('ui.nav.history') }}</span>
                        </a>
                    @endif

                    @if ($isApprover && Route::has('approvals.index'))
                        <a
                            href="{{ route('approvals.index') }}"
                            x-bind:title="collapsed ? '{{ __('ui.nav.approvals') }}' : null"
                            class="sidebar-nav-item flex min-h-11 items-center gap-3 rounded-2xl px-4 py-2.5 font-semibold {{ request()->routeIs('approvals.*') ? 'bg-brand-navy text-on-brand shadow-xs' : 'text-muted hover:bg-surface hover:text-text' }}"
                        >
                            <span class="relative shrink-0">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                @if ($pendingApprovalsCount > 0)
                                    <span class="sidebar-badge-dot absolute -top-1.5 -right-2 inline-flex min-w-4 items-center justify-center rounded-full bg-brand-green-strong px-1 text-[10px] font-bold text-on-brand">
                                        {{ $pendingApprovalsCount }}
                                    </span>
                                @endif
                            </span>
                            <span class="sidebar-label flex-1 truncate whitespace-nowrap">{{ __('ui.nav.approvals') }}</span>
                            @if ($pendingApprovalsCount > 0)
                                <span class="sidebar-label ml-auto rounded-full bg-brand-green-strong px-2 py-0.5 text-xs font-bold text-on-brand">{{ $pendingApprovalsCount }}</span>
                            @endif
                        </a>
                    @endif

                    @if ($isManagerOrHr && Route::has('management.attendances.index'))
                        <a
                            href="{{ route('management.attendances.index') }}"
                            x-bind:title="collapsed ? '{{ __('ui.nav.team_attendance') }}' : null"
                            class="sidebar-nav-item flex min-h-11 items-center gap-3 rounded-2xl px-4 py-2.5 font-semibold {{ request()->routeIs('management.attendances.*') ? 'bg-brand-navy text-on-brand shadow-xs' : 'text-muted hover:bg-surface hover:text-text' }}"
                        >
                            <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-5-3.87M9 20H4v-2a4 4 0 015-3.87m6-4.13a4 4 0 11-8 0 4 4 0 018 0zm6 2a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <span class="sidebar-label truncate whitespace-nowrap">{{ __('ui.nav.team_attendance') }}</span>
                        </a>
                    @endif

                    @if ($user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['dept_manager', 'hr_manager', 'super_admin']) && Route::has('management.employees.index'))
                        <a
                            href="{{ route('management.employees.index') }}"
                            x-bind:title="collapsed ? '{{ __('ui.nav.employees') }}' : null"
                            class="sidebar-nav-item flex min-h-11 items-center gap-3 rounded-2xl px-4 py-2.5 font-semibold {{ request()->routeIs('management.employees.*') ? 'bg-brand-navy text-on-brand shadow-xs' : 'text-muted hover:bg-surface hover:text-text' }}"
                        >
                            <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            <span class="sidebar-label truncate whitespace-nowrap">{{ __('ui.nav.employees') }}</span>
                        </a>
                    @endif

                    @if ($isSuperAdmin && Route::has('management.master.index'))
                        <a
                            href="{{ route('management.master.index') }}"
                            x-bind:title="collapsed ? '{{ __('ui.nav.master_data') }}' : null"
                            class="sidebar-nav-item flex min-h-11 items-center gap-3 rounded-2xl px-4 py-2.5 font-semibold {{ request()->routeIs('management.master.*') ? 'bg-brand-navy text-on-brand shadow-xs' : 'text-muted hover:bg-surface hover:text-text' }}"
                        >
                            <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                            <span class="sidebar-label truncate whitespace-nowrap">{{ __('ui.nav.master_data') }}</span>
                        </a>
                    @endif

                    @if (Route::has('profile.show'))
                        <a
                            href="{{ route('profile.show') }}"
                            x-bind:title="collapsed ? '{{ __('ui.nav.profile') }}' : null"
                            class="sidebar-nav-item flex min-h-11 items-center gap-3 rounded-2xl px-4 py-2.5 font-semibold {{ request()->routeIs('profile.*') ? 'bg-brand-navy text-on-brand shadow-xs' : 'text-muted hover:bg-surface hover:text-text' }}"
                        >
                            <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><circle cx="12" cy="8" r="4"/><path stroke-linecap="round" d="M5 20a7 7 0 0114 0"/></svg>
                            <span class="sidebar-label truncate whitespace-nowrap">{{ __('ui.nav.profile') }}</span>
                        </a>
                    @endif
                </nav>

                <div class="border-t border-border p-3.5">
                    <div
                        class="sidebar-user-row mb-3 flex items-center gap-3 px-1.5"
                        x-bind:title="collapsed ? '{{ $user?->name }} — {{ $primaryRole?->label() ?? $user?->email }}' : null"
                    >
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-navy-soft text-xs font-bold text-brand-ink">
                            {{ $initials }}
                        </span>
                        <div class="sidebar-label min-w-0">
                            <p class="truncate text-sm font-bold text-text">{{ $user?->name }}</p>
                            <p class="truncate text-xs text-muted">{{ $primaryRole?->label() ?? $user?->email }}</p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button
                            type="submit"
                            aria-label="{{ __('ui.actions.logout') }}"
                            x-bind:title="collapsed ? '{{ __('ui.actions.logout') }}' : null"
                            class="sidebar-logout-btn inline-flex h-10 w-full cursor-pointer items-center justify-center gap-2 rounded-xl border border-border bg-card px-3 text-xs font-semibold text-text shadow-xs hover:bg-surface"
                        >
                            <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H9m4 4v1a2 2 0 01-2 2H6a2 2 0 01-2-2V7a2 2 0 012-2h5a2 2 0 012 2v1" />
                            </svg>
                            <span class="sidebar-label truncate whitespace-nowrap">{{ __('ui.actions.logout') }}</span>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        {{-- Area Konten Utama --}}
        <div class="flex min-w-0 flex-1 flex-col">
            <header class="relative isolate overflow-hidden bg-gradient-to-r from-brand-navy to-brand-ink text-on-brand pt-safe shadow-sm">
                <div class="pointer-events-none absolute -top-12 -right-12 h-40 w-40 rounded-full bg-brand-green/20 blur-2xl" aria-hidden="true"></div>

                <div class="relative flex w-full items-center justify-between gap-3 px-4 py-3.5 sm:px-6 lg:px-8">
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="lg:hidden">
                            <x-brand-logo boxed size="sm" />
                        </div>
                        <div class="min-w-0">
                            <h1 class="truncate text-base font-bold tracking-tight text-on-brand sm:text-lg">{{ $title }}</h1>
                            @if ($subtitle)
                                <p class="truncate text-xs text-on-brand/80">{{ $subtitle }}</p>
                            @endif
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        @if (Route::has('notifications.index'))
                            <a
                                href="{{ route('notifications.index') }}"
                                class="relative inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-on-brand/10 text-on-brand ring-1 ring-on-brand/15 transition-colors duration-150 hover:bg-on-brand/20"
                                aria-label="{{ __('ui.nav.notifications') }}"
                            >
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                </svg>
                                @if ($unreadNotificationsCount > 0)
                                    <span class="absolute top-1.5 right-1.5 inline-flex min-w-4 items-center justify-center rounded-full bg-brand-green px-1 text-[10px] font-bold text-on-brand">
                                        {{ $unreadNotificationsCount > 9 ? '9+' : $unreadNotificationsCount }}
                                    </span>
                                @endif
                            </a>
                        @endif

                        <form method="POST" action="{{ route('logout') }}" class="lg:hidden">
                            @csrf
                            <button
                                type="submit"
                                class="inline-flex h-11 items-center justify-center rounded-2xl bg-on-brand/10 px-3 text-xs font-semibold text-on-brand ring-1 ring-on-brand/15 transition-colors duration-150 hover:bg-on-brand/20"
                            >
                                {{ __('ui.actions.logout') }}
                            </button>
                        </form>
                    </div>
                </div>
            </header>

            <main class="w-full flex-1 px-4 py-5 sm:px-6 lg:px-8 {{ $hideBottomNav ? '' : 'mb-safe-nav lg:pb-8' }}">
                {{ $slot }}
            </main>
        </div>
    </div>

    {{-- Bottom Tab Bar (Mobile-First) --}}
    @if (! $hideBottomNav)
        <nav
            class="fixed right-0 bottom-0 left-0 z-40 border-t border-border bg-card/95 shadow-lg backdrop-blur-md pb-safe lg:hidden"
            aria-label="Menu Bawah Mobile"
        >
            <div class="mx-auto flex max-w-md items-end justify-between gap-0.5 px-1.5 pt-1.5">
                <a
                    href="{{ route('dashboard') }}"
                    class="flex min-h-11 min-w-0 flex-1 flex-col items-center justify-center gap-0.5 rounded-xl px-1 py-1 text-[10px] font-semibold {{ request()->routeIs('dashboard') ? 'text-brand-navy' : 'text-muted' }}"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10.5L12 3l9 7.5V20a1 1 0 01-1 1h-5v-6H9v6H4a1 1 0 01-1-1v-9.5z"/></svg>
                    <span class="truncate">{{ __('ui.nav.home') }}</span>
                    @if (request()->routeIs('dashboard'))
                        <span class="h-1 w-4 rounded-full bg-brand-green"></span>
                    @endif
                </a>

                @if (Route::has('leave.index') && ! $isPayroll && $user?->employee)
                    <a
                        href="{{ route('leave.index') }}"
                        class="flex min-h-11 min-w-0 flex-1 flex-col items-center justify-center gap-0.5 rounded-xl px-1 py-1 text-[10px] font-semibold {{ request()->routeIs('leave.*') ? 'text-brand-navy' : 'text-muted' }}"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="3"/><path stroke-linecap="round" d="M16 2v4M8 2v4M3 10h18"/></svg>
                        <span class="truncate">{{ __('ui.nav.leave') }}</span>
                        @if (request()->routeIs('leave.*'))
                            <span class="h-1 w-4 rounded-full bg-brand-green"></span>
                        @endif
                    </a>
                @endif

                @if (Route::has('attendance.camera') && ! $isPayroll && $user?->employee)
                    <a
                        href="{{ route('attendance.camera') }}"
                        class="-mt-5 flex min-h-14 min-w-15 shrink-0 flex-col items-center justify-center rounded-2xl bg-brand-green-strong px-3.5 py-2 text-[11px] font-bold text-on-brand shadow-lg ring-4 ring-card transition-transform duration-150 active:scale-95"
                    >
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8a2 2 0 012-2h2l1.5-2h7L17 6h2a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/><circle cx="12" cy="13" r="3.5"/></svg>
                        <span class="mt-0.5">{{ __('ui.nav.attendance') }}</span>
                    </a>
                @endif

                @if ($isApprover && Route::has('approvals.index'))
                    <a
                        href="{{ route('approvals.index') }}"
                        class="relative flex min-h-11 min-w-0 flex-1 flex-col items-center justify-center gap-0.5 rounded-xl px-1 py-1 text-[10px] font-semibold {{ request()->routeIs('approvals.*') ? 'text-brand-navy' : 'text-muted' }}"
                    >
                        <div class="relative">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            @if ($pendingApprovalsCount > 0)
                                <span class="absolute -top-1.5 -right-2 inline-flex min-w-4 items-center justify-center rounded-full bg-brand-green-strong px-1 text-[10px] font-bold text-on-brand">
                                    {{ $pendingApprovalsCount }}
                                </span>
                            @endif
                        </div>
                        <span class="truncate">{{ __('ui.nav.approvals') }}</span>
                        @if (request()->routeIs('approvals.*'))
                            <span class="h-1 w-4 rounded-full bg-brand-green"></span>
                        @endif
                    </a>
                @endif

                @if (Route::has('attendance.history') && ! $isPayroll && $user?->employee)
                    <a
                        href="{{ route('attendance.history') }}"
                        class="flex min-h-11 min-w-0 flex-1 flex-col items-center justify-center gap-0.5 rounded-xl px-1 py-1 text-[10px] font-semibold {{ request()->routeIs('attendance.history') ? 'text-brand-navy' : 'text-muted' }}"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span class="truncate">{{ __('ui.nav.history') }}</span>
                        @if (request()->routeIs('attendance.history'))
                            <span class="h-1 w-4 rounded-full bg-brand-green"></span>
                        @endif
                    </a>
                @endif

                @if (Route::has('profile.show'))
                    <a
                        href="{{ route('profile.show') }}"
                        class="flex min-h-11 min-w-0 flex-1 flex-col items-center justify-center gap-0.5 rounded-xl px-1 py-1 text-[10px] font-semibold {{ request()->routeIs('profile.*') ? 'text-brand-navy' : 'text-muted' }}"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path stroke-linecap="round" d="M5 20a7 7 0 0114 0"/></svg>
                        <span class="truncate">{{ __('ui.nav.profile') }}</span>
                        @if (request()->routeIs('profile.*'))
                            <span class="h-1 w-4 rounded-full bg-brand-green"></span>
                        @endif
                    </a>
                @endif
            </div>
        </nav>
    @endif
</body>
</html>
