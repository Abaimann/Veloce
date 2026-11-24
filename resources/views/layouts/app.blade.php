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
        ? $user->hasAnyRole(['hr_manager', 'super_admin'])
        : false;
    $isManagerOrHr = $user && method_exists($user, 'hasAnyRole')
        ? $user->hasAnyRole(['division_head', 'dept_manager', 'hr_manager', 'super_admin'])
        : false;
    $canViewPayroll = $user && method_exists($user, 'hasAnyRole')
        ? $user->hasAnyRole(['payroll', 'hr_manager', 'super_admin'])
        : false;
    $isSuperAdmin = $user && method_exists($user, 'hasRole')
        ? $user->hasRole('super_admin')
        : false;
    $hasManagementSection = ($isApprover && Route::has('approvals.index'))
        || ($isManagerOrHr && Route::has('management.attendances.index'))
        || ($user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['dept_manager', 'hr_manager', 'super_admin']) && Route::has('management.employees.index'))
        || ($canViewPayroll && Route::has('payroll.index'))
        || ($isSuperAdmin && (Route::has('management.master.index') || Route::has('management.activity-logs.index')));

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
<body class="veloce-workspace-canvas min-h-full text-text" x-data="{ mobileMenuOpen: false }" x-on:keydown.escape.window="mobileMenuOpen = false">
    <x-toast />

    {{-- Mobile Slide-Over Navigation Drawer (Semua Role) --}}
    <div
        x-show="mobileMenuOpen"
        x-cloak
        class="fixed inset-0 z-50 flex lg:hidden"
        role="dialog"
        aria-modal="true"
        aria-label="Menu Navigasi Mobile"
    >
        <div
            x-show="mobileMenuOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            x-on:click="mobileMenuOpen = false"
            class="fixed inset-0 bg-overlay backdrop-blur-xs"
        ></div>

        <aside
            x-show="mobileMenuOpen"
            x-transition:enter="transition ease-out duration-250"
            x-transition:enter-start="-translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="-translate-x-full"
            class="veloce-sidebar-shell relative z-10 flex h-full w-72 max-w-[82vw] flex-col text-on-brand shadow-2xl"
        >
            <div class="relative z-10 flex h-[72px] shrink-0 items-center justify-between border-b border-on-brand/10 px-4">
                <a href="{{ route('dashboard') }}" class="inline-flex items-center rounded-2xl bg-card p-1.5 shadow-sm">
                    <x-brand-logo size="sm" />
                </a>
                <button
                    type="button"
                    x-on:click="mobileMenuOpen = false"
                    class="inline-flex h-10 w-10 cursor-pointer items-center justify-center rounded-xl bg-on-brand/10 text-on-brand hover:bg-on-brand/20"
                    aria-label="Tutup Menu"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <nav class="veloce-sidebar-scroll relative z-10 flex-1 space-y-1 overflow-y-auto px-3 py-3 text-sm" x-on:click="if ($event.target.closest('a')) mobileMenuOpen = false">
                @include('layouts.partials.nav-items', ['mobile' => true])
            </nav>

            <div class="relative z-10 border-t border-on-brand/10 p-3">
                <div class="mb-2.5 flex items-center gap-3 rounded-2xl border border-on-brand/10 bg-on-brand/5 p-2">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-green/25 text-xs font-extrabold text-on-brand ring-1 ring-on-brand/20">
                        {{ $initials }}
                    </span>
                    <div class="min-w-0">
                        <p class="truncate text-xs font-bold text-on-brand">{{ $user?->name }}</p>
                        <p class="truncate text-[11px] text-on-brand/65">{{ $primaryRole?->label() ?? $user?->email }}</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button
                        type="submit"
                        class="inline-flex h-10 w-full cursor-pointer items-center justify-center gap-2 rounded-xl border border-on-brand/15 bg-on-brand/8 px-3 text-xs font-semibold text-on-brand/90 hover:bg-on-brand/16"
                    >
                        <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H9m4 4v1a2 2 0 01-2 2H6a2 2 0 01-2-2V7a2 2 0 012-2h5a2 2 0 012 2v1" />
                        </svg>
                        <span>{{ __('ui.actions.logout') }}</span>
                    </button>
                </form>
            </div>
        </aside>
    </div>

    <div class="min-h-screen overflow-x-clip lg:flex" x-data="sidebarShell()">
        {{-- Desktop Left Deep-Ink Command Sidebar --}}
        <aside class="sidebar-desktop veloce-sidebar-shell hidden text-on-brand lg:sticky lg:top-0 lg:z-30 lg:flex lg:h-screen lg:w-68 lg:shrink-0 lg:flex-col">
            <button
                type="button"
                x-on:click="toggle()"
                x-bind:aria-expanded="(!collapsed).toString()"
                x-bind:aria-label="collapsed ? 'Perluas sidebar' : 'Kecilkan sidebar'"
                x-bind:title="collapsed ? 'Perluas sidebar' : 'Kecilkan sidebar'"
                class="absolute top-[62px] -right-3.5 z-40 flex h-7 w-7 cursor-pointer items-center justify-center rounded-full border border-border bg-card text-brand-ink shadow-md transition-all duration-200 hover:scale-110 hover:border-brand-green hover:text-brand-green-strong"
            >
                <svg
                    class="h-4 w-4 transition-transform duration-300"
                    :class="collapsed ? 'rotate-180' : ''"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2.3"
                    aria-hidden="true"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
            </button>

            <div class="relative z-10 flex h-full w-full flex-col overflow-hidden">
                {{-- Brand Dock --}}
                <div class="sidebar-brand-header flex h-[76px] shrink-0 items-center border-b border-on-brand/10 px-5">
                    <a
                        href="{{ route('dashboard') }}"
                        class="inline-flex items-center rounded-2xl bg-card p-1.5 shadow-sm ring-1 ring-on-brand/20 transition-transform duration-200 hover:scale-[1.02]"
                        x-bind:title="collapsed ? '{{ config('app.name') }}' : null"
                    >
                        <span class="sidebar-brand-clip flex items-center overflow-hidden">
                            <x-brand-logo size="md" />
                        </span>
                    </a>
                </div>

                <nav class="veloce-sidebar-scroll flex-1 space-y-1 overflow-x-hidden overflow-y-auto px-3 py-3 text-sm" aria-label="Navigasi Utama">
                    @include('layouts.partials.nav-items', ['mobile' => false])
                </nav>

                {{-- Glassmorphic User Dock --}}
                <div class="border-t border-on-brand/10 p-3">
                    <div
                        class="sidebar-user-row mb-2.5 flex items-center gap-3 rounded-2xl border border-on-brand/10 bg-on-brand/5 p-2"
                        x-bind:title="collapsed ? '{{ $user?->name }} — {{ $primaryRole?->label() ?? $user?->email }}' : null"
                    >
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-green/25 text-xs font-extrabold text-on-brand ring-1 ring-on-brand/20">
                            {{ $initials }}
                        </span>
                        <div class="sidebar-label min-w-0">
                            <p class="truncate text-xs font-bold text-on-brand">{{ $user?->name }}</p>
                            <p class="truncate text-[11px] text-on-brand/65">{{ $primaryRole?->label() ?? $user?->email }}</p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button
                            type="submit"
                            aria-label="{{ __('ui.actions.logout') }}"
                            x-bind:title="collapsed ? '{{ __('ui.actions.logout') }}' : null"
                            class="sidebar-logout-btn inline-flex h-10 w-full cursor-pointer items-center justify-center gap-2 rounded-xl border border-on-brand/15 bg-on-brand/8 px-3 text-xs font-semibold text-on-brand/90 hover:bg-on-brand/16 hover:text-on-brand"
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
            {{-- Header Mobile (Menu Drawer + Judul + Notif) + Desktop (Frosted Precision Glass) --}}
            <header class="veloce-topbar sticky top-0 z-20 pt-safe text-on-brand lg:text-brand-ink">
                <div class="relative flex w-full items-center justify-between gap-2.5 px-3.5 py-3 sm:px-6 lg:h-[76px] lg:px-8">
                    <div class="flex min-w-0 items-center gap-2.5 sm:gap-3">
                        <button
                            type="button"
                            x-on:click="mobileMenuOpen = true"
                            class="inline-flex h-10 w-10 shrink-0 cursor-pointer items-center justify-center rounded-2xl bg-on-brand/12 text-on-brand ring-1 ring-on-brand/15 hover:bg-on-brand/20 lg:hidden"
                            aria-label="Buka Menu"
                        >
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h10"/></svg>
                        </button>

                        <span class="hidden h-9 w-1 rounded-full bg-gradient-to-b from-brand-green to-brand-navy lg:inline-block" aria-hidden="true"></span>
                        <div class="min-w-0">
                            <h1 class="truncate text-base font-extrabold tracking-tight text-on-brand sm:text-lg lg:text-xl lg:text-brand-ink">
                                {{ $title }}
                            </h1>
                            @if ($subtitle)
                                <p class="truncate text-[11px] font-medium text-on-brand/75 sm:text-xs lg:text-muted">{{ $subtitle }}</p>
                            @endif
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        @if ($primaryRole)
                            <div class="hidden items-center gap-2 rounded-full border border-border bg-surface/90 px-3 py-1.5 text-xs font-bold text-brand-ink shadow-2xs md:inline-flex">
                                <span class="timeline-beacon-dot h-2 w-2 rounded-full bg-brand-green"></span>
                                <span>{{ $primaryRole->label() }}</span>
                            </div>
                        @endif

                        @if (Route::has('notifications.index'))
                            <a
                                href="{{ route('notifications.index') }}"
                                class="relative inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-on-brand/12 text-on-brand ring-1 ring-on-brand/15 transition-all duration-150 hover:bg-on-brand/20 lg:h-11 lg:w-11 lg:border lg:border-border lg:bg-card lg:text-brand-ink lg:ring-0 lg:shadow-xs lg:hover:border-brand-navy/35 lg:hover:bg-brand-navy-soft"
                                aria-label="{{ __('ui.nav.notifications') }}"
                            >
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                </svg>
                                @if ($unreadNotificationsCount > 0)
                                    <span class="absolute top-1.5 right-1.5 inline-flex min-w-4 items-center justify-center rounded-full bg-brand-green px-1 text-[10px] font-bold text-on-brand shadow-xs">
                                        {{ $unreadNotificationsCount > 9 ? '9+' : $unreadNotificationsCount }}
                                    </span>
                                @endif
                            </a>
                        @endif
                    </div>
                </div>
            </header>

            <main class="veloce-main veloce-page-enter w-full max-w-full flex-1 overflow-x-clip px-3.5 py-4 sm:px-6 sm:py-5 lg:px-8 lg:py-6 {{ $hideBottomNav ? '' : 'mb-safe-nav lg:pb-10' }}">
                {{ $slot }}
            </main>
        </div>
    </div>

    {{-- Floating Sculpted Bottom Dock (Mobile-First) --}}
    @if (! $hideBottomNav)
        <nav
            class="fixed right-2.5 bottom-2.5 left-2.5 z-40 rounded-3xl veloce-mobile-dock pb-safe lg:hidden"
            aria-label="Menu Bawah Mobile"
        >
            <div class="mx-auto flex max-w-md items-end justify-between gap-0.5 px-1.5 pt-1.5">
                <a
                    href="{{ route('dashboard') }}"
                    class="flex min-h-11 min-w-0 flex-1 flex-col items-center justify-center gap-0.5 rounded-2xl px-1 py-1 text-[10px] font-bold {{ request()->routeIs('dashboard') ? 'text-brand-navy' : 'text-muted' }}"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10.5L12 3l9 7.5V20a1 1 0 01-1 1h-5v-6H9v6H4a1 1 0 01-1-1v-9.5z"/></svg>
                    <span class="truncate">{{ __('ui.nav.home') }}</span>
                    @if (request()->routeIs('dashboard'))
                        <span class="h-1 w-4 rounded-full bg-brand-green"></span>
                    @endif
                </a>

                @if ($canViewPayroll && ! $user?->hasRole('hr_manager') && Route::has('payroll.index'))
                    <a
                        href="{{ route('payroll.index') }}"
                        class="flex min-h-11 min-w-0 flex-1 flex-col items-center justify-center gap-0.5 rounded-2xl px-1 py-1 text-[10px] font-bold {{ request()->routeIs('payroll.*') ? 'text-brand-navy' : 'text-muted' }}"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span class="truncate">Gaji</span>
                        @if (request()->routeIs('payroll.*'))
                            <span class="h-1 w-4 rounded-full bg-brand-green"></span>
                        @endif
                    </a>
                @elseif (Route::has('timeline.index'))
                    <a
                        href="{{ route('timeline.index') }}"
                        class="flex min-h-11 min-w-0 flex-1 flex-col items-center justify-center gap-0.5 rounded-2xl px-1 py-1 text-[10px] font-bold {{ request()->routeIs('timeline.*') ? 'text-brand-navy' : 'text-muted' }}"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
                        <span class="truncate">Timeline</span>
                        @if (request()->routeIs('timeline.*'))
                            <span class="h-1 w-4 rounded-full bg-brand-green"></span>
                        @endif
                    </a>
                @endif

                @if (Route::has('attendance.camera') && $user?->employee)
                    <a
                        href="{{ route('attendance.camera') }}"
                        class="veloce-btn-checkin -mt-5 flex min-h-14 min-w-15 shrink-0 flex-col items-center justify-center rounded-2xl px-3.5 py-2 text-[11px] font-extrabold text-on-brand ring-4 ring-card transition-transform duration-150 active:scale-95"
                    >
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8a2 2 0 012-2h2l1.5-2h7L17 6h2a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/><circle cx="12" cy="13" r="3.5"/></svg>
                        <span class="mt-0.5">{{ __('ui.nav.attendance') }}</span>
                    </a>
                @endif

                @if ($isApprover && Route::has('approvals.index'))
                    <a
                        href="{{ route('approvals.index') }}"
                        class="relative flex min-h-11 min-w-0 flex-1 flex-col items-center justify-center gap-0.5 rounded-2xl px-1 py-1 text-[10px] font-bold {{ request()->routeIs('approvals.*') ? 'text-brand-navy' : 'text-muted' }}"
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
                @elseif (Route::has('work.index') && $user?->employee)
                    <a
                        href="{{ route('work.index') }}"
                        class="flex min-h-11 min-w-0 flex-1 flex-col items-center justify-center gap-0.5 rounded-2xl px-1 py-1 text-[10px] font-bold {{ request()->routeIs('work.*') ? 'text-brand-navy' : 'text-muted' }}"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                        <span class="truncate">Drive PT</span>
                        @if (request()->routeIs('work.*'))
                            <span class="h-1 w-4 rounded-full bg-brand-green"></span>
                        @endif
                    </a>
                @endif

                @if (Route::has('profile.show'))
                    <a
                        href="{{ route('profile.show') }}"
                        class="flex min-h-11 min-w-0 flex-1 flex-col items-center justify-center gap-0.5 rounded-2xl px-1 py-1 text-[10px] font-bold {{ request()->routeIs('profile.*') ? 'text-brand-navy' : 'text-muted' }}"
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
