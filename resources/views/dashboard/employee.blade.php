@php
    use App\Enums\AttendanceStatus;
    use App\Enums\EmploymentType;

    $hourWib = (int) $nowWib->format('G');
    $greeting = match (true) {
        $hourWib < 11 => 'Selamat Pagi',
        $hourWib < 15 => 'Selamat Siang',
        $hourWib < 18 => 'Selamat Sore',
        default => 'Selamat Malam',
    };

    $primaryRole = $user->primaryRole();
    $unitParts = $employee !== null
        ? array_values(array_filter([
            $employee->department?->name,
            $employee->division?->name,
        ]))
        : [];
    $unitLabel = count($unitParts) > 0 ? implode(' — ', $unitParts) : null;

    $scheduleText = $employee?->workSchedule
        ? substr((string) $employee->workSchedule->check_in_time, 0, 5)
            . ' – '
            . substr((string) $employee->workSchedule->check_out_time, 0, 5)
            . ' WIB'
        : null;

    $approvalsUrl = Route::has('approvals.index') ? route('approvals.index') : '#';
    $teamAttendanceUrl = Route::has('management.attendances.index') ? route('management.attendances.index') : null;
    $canManageEmployees = $user->hasAnyRole(['dept_manager', 'hr_manager']);
    $canViewTeamAttendance = $user->hasAnyRole(['division_head', 'dept_manager', 'hr_manager']);

    $ctaMode = 'none';
    $statusText = 'Profil kepegawaian belum ditautkan';
    $statusBadgeText = 'Info Akun';
    $statusVariant = 'neutral';
    $blockedDescription = null;

    if ($employee !== null) {
        if ($todayAttendance !== null && $todayAttendance->status->blocksSelfieCheckIn()) {
            $statusLabel = $todayAttendance->status->label();
            $statusText = "Tercatat {$statusLabel}";
            $statusBadgeText = $statusLabel;
            $statusVariant = $todayAttendance->status->badgeVariant();
            $ctaMode = 'blocked';
            $blockedDescription = __('attendance.messages.blocked_by_leave', ['status' => $statusLabel]);
        } elseif ($approvedBlockingLeave !== null) {
            $blockingStatus = $approvedBlockingLeave->leaveType?->attendance_status;
            $statusLabel = $blockingStatus?->label() ?? ($approvedBlockingLeave->leaveType?->name ?? 'Izin');
            $statusText = "Tercatat {$statusLabel}";
            $statusBadgeText = $statusLabel;
            $statusVariant = $blockingStatus?->badgeVariant() ?? 'violet';
            $ctaMode = 'blocked';
            $blockedDescription = __('attendance.messages.blocked_by_leave', ['status' => $statusLabel]);
        } elseif (! $employee->is_active) {
            $statusText = 'Kepegawaian Nonaktif';
            $statusBadgeText = 'Nonaktif';
            $statusVariant = 'danger';
            $ctaMode = 'blocked';
            $blockedDescription = 'Status kepegawaian Anda saat ini nonaktif. Hubungi HR Manager untuk informasi lebih lanjut.';
        } elseif ($todayAttendance !== null && $todayAttendance->check_in_at !== null && $todayAttendance->check_out_at !== null) {
            $inTime = $todayAttendance->check_in_at->setTimezone($timezone)->format('H:i');
            $outTime = $todayAttendance->check_out_at->setTimezone($timezone)->format('H:i');
            $statusText = "Selesai Hari Ini {$inTime} – {$outTime} WIB";
            $statusBadgeText = $todayAttendance->status->label();
            $statusVariant = $todayAttendance->status->badgeVariant();
            $ctaMode = 'completed';
        } elseif ($todayAttendance !== null && $todayAttendance->check_in_at !== null) {
            $inTime = $todayAttendance->check_in_at->setTimezone($timezone)->format('H:i');
            $statusText = match ($todayAttendance->status) {
                AttendanceStatus::LATE => "Terlambat — Masuk {$inTime} WIB",
                AttendanceStatus::WFH => "WFH — Masuk pukul {$inTime} WIB",
                default => "Masuk pukul {$inTime} WIB",
            };
            $statusBadgeText = $todayAttendance->status->label();
            $statusVariant = $todayAttendance->status->badgeVariant();
            $ctaMode = 'check_out';
        } else {
            $statusText = $hasApprovedWfh ? 'WFH — Belum Absen Masuk' : 'Belum Absen Masuk';
            $statusBadgeText = $hasApprovedWfh ? 'WFH' : 'Belum Masuk';
            $statusVariant = $hasApprovedWfh ? 'info' : 'warning';
            $ctaMode = 'check_in';
        }
    }
@endphp

<x-app-layout
    title="Beranda"
    :subtitle="$unitLabel ?? __('ui.company_name')"
    :pending-approvals-count="$pendingApprovalsCount"
    :unread-notifications-count="$unreadNotificationsCount"
>
    <div class="space-y-4">
        {{-- Kartu Status Hari Ini + Jam WIB Real-Time --}}
        <div class="space-y-3">
            <section
                x-data="serverClock({{ $nowWib->getTimestampMs() }})"
                aria-label="Status Kehadiran Hari Ini"
                class="relative isolate overflow-hidden rounded-3xl bg-gradient-to-br from-brand-navy to-brand-ink p-5 text-on-brand shadow-lg shadow-brand-ink/15 sm:p-6"
            >
                <div class="pointer-events-none absolute -top-20 -right-20 h-56 w-56 rounded-full bg-brand-green/20 blur-3xl" aria-hidden="true"></div>
                <svg class="pointer-events-none absolute -right-12 -bottom-14 h-48 w-48 text-on-brand/10" viewBox="0 0 200 200" fill="none" aria-hidden="true">
                    <circle cx="100" cy="100" r="45" stroke="currentColor" stroke-width="1.2" />
                    <circle cx="100" cy="100" r="75" stroke="currentColor" stroke-width="1.2" />
                    <circle cx="100" cy="100" r="98" stroke="currentColor" stroke-width="1.2" />
                </svg>

                <div class="relative flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs font-medium text-on-brand/80">{{ $greeting }},</p>
                        <h2 class="mt-0.5 truncate text-xl font-bold tracking-tight text-on-brand sm:text-2xl">
                            {{ $user->name }}
                        </h2>
                        @if ($unitLabel)
                            <p class="mt-0.5 text-xs text-on-brand/80">{{ $unitLabel }}</p>
                        @elseif ($primaryRole)
                            <p class="mt-0.5 text-xs text-on-brand/80">{{ $primaryRole->label() }}</p>
                        @endif
                    </div>

                    @if ($primaryRole)
                        <span class="rounded-full bg-on-brand/15 px-3 py-1 text-[11px] font-semibold text-on-brand ring-1 ring-on-brand/15">
                            {{ $primaryRole->label() }}
                        </span>
                    @endif
                </div>

                <div class="relative mt-5 border-t border-on-brand/15 pt-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p class="text-3xl font-bold tracking-tight text-on-brand tabular-nums sm:text-4xl" x-text="timeText">
                                {{ $nowWib->format('H:i:s') }} WIB
                            </p>
                            <p class="mt-1 text-xs text-on-brand/80" x-text="dateText">
                                {{ $nowWib->translatedFormat('l, d F Y') }}
                            </p>
                        </div>

                        @if ($scheduleText)
                            <div class="rounded-2xl bg-on-brand/10 px-3.5 py-2 text-xs ring-1 ring-on-brand/10">
                                <span class="text-on-brand/75">Jadwal Hari Ini:</span>
                                <span class="ml-1 font-bold tabular-nums text-on-brand">{{ $scheduleText }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="relative mt-4 flex flex-wrap items-center justify-between gap-2 rounded-2xl bg-on-brand/10 px-4 py-3 ring-1 ring-on-brand/15 backdrop-blur-xs">
                    <div class="min-w-0">
                        <p class="text-[11px] font-medium text-on-brand/75">Status Hari Ini</p>
                        <p class="mt-0.5 text-sm font-bold text-on-brand">{{ $statusText }}</p>
                    </div>
                    <x-badge :variant="$statusVariant">{{ $statusBadgeText }}</x-badge>
                </div>
            </section>

            {{-- Tombol Aksi Utama Besar (Kontras Maksimal di Bawah Kartu Navy) --}}
            @if ($employee === null)
                <div class="rounded-2xl border border-warning/25 bg-warning-soft/50 p-4 shadow-xs sm:p-5">
                    <div class="flex items-start gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-warning-soft text-warning-ink">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-bold text-warning-ink">Profil Karyawan Belum Ditautkan</p>
                            <p class="mt-0.5 text-xs leading-relaxed text-text">
                                Akun Anda belum memiliki data kepegawaian. Hubungi HR Manager atau Super Admin agar fitur absensi dan pengajuan izin dapat digunakan.
                            </p>
                        </div>
                    </div>
                </div>
            @elseif ($ctaMode === 'check_in')
                <x-button href="{{ route('attendance.camera') }}" variant="checkin" size="lg" fullWidth>
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8a2 2 0 012-2h2l1.5-2h7L17 6h2a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V8z" />
                        <circle cx="12" cy="13" r="3.5" />
                    </svg>
                    <span>Absen Masuk</span>
                </x-button>
            @elseif ($ctaMode === 'check_out')
                <x-button href="{{ route('attendance.camera') }}" variant="primary" size="lg" fullWidth>
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    <span>Absen Pulang</span>
                </x-button>
            @elseif ($ctaMode === 'completed')
                <div class="rounded-2xl border border-brand-green/30 bg-success-soft/45 p-4 shadow-xs sm:p-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-start gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-success-soft text-success-ink">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-success-ink">Absensi Hari Ini Lengkap</p>
                                <p class="mt-0.5 text-xs text-text">
                                    Terima kasih, catatan masuk dan pulang Anda hari ini sudah tersimpan.
                                </p>
                            </div>
                        </div>
                        <x-button href="{{ route('attendance.history') }}" variant="secondary" size="sm" class="shrink-0">
                            Lihat Riwayat
                        </x-button>
                    </div>
                </div>
            @elseif ($ctaMode === 'blocked')
                <x-card>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-brand-ink">Absensi Selfie Tidak Diperlukan</p>
                            <p class="mt-0.5 text-xs leading-relaxed text-muted">{{ $blockedDescription }}</p>
                        </div>
                        <x-button href="{{ route('leave.index') }}" variant="secondary" size="sm" class="shrink-0">
                            Lihat Izin &amp; Cuti
                        </x-button>
                    </div>
                </x-card>
            @endif
        </div>

        {{-- Shortcut Persetujuan untuk Approver --}}
        @if ($isApprover)
            <div class="rounded-2xl border p-4 shadow-xs sm:p-5 {{ $pendingApprovalsCount > 0 ? 'border-brand-green/35 bg-brand-green-soft/45' : 'border-border bg-card' }}">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-start gap-3">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl {{ $pendingApprovalsCount > 0 ? 'bg-brand-green-strong text-on-brand' : 'bg-brand-navy-soft text-brand-navy' }}">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-sm font-bold text-brand-ink">Persetujuan Pengajuan Tim</h3>
                                @if ($pendingApprovalsCount > 0)
                                    <x-badge variant="success">{{ $pendingApprovalsCount }} Menunggu</x-badge>
                                @else
                                    <x-badge variant="neutral">Tidak Ada Antrean</x-badge>
                                @endif
                            </div>
                            <p class="mt-0.5 text-xs text-muted">
                                @if ($pendingApprovalsCount > 0)
                                    Terdapat {{ $pendingApprovalsCount }} pengajuan cuti, sakit, izin, atau WFH yang menunggu keputusan Anda.
                                @else
                                    Semua pengajuan yang menjadi wewenang Anda sudah selesai diproses.
                                @endif
                            </p>
                        </div>
                    </div>

                    <x-button
                        href="{{ $approvalsUrl }}"
                        :variant="$pendingApprovalsCount > 0 ? 'checkin' : 'secondary'"
                        size="sm"
                        class="shrink-0"
                    >
                        Buka Persetujuan
                    </x-button>
                </div>
            </div>
        @endif

        @if ($employee !== null)
            {{-- 4 Kartu Ringkasan Bulan Ini --}}
            <section aria-labelledby="monthly-summary-heading" class="space-y-2.5">
                <div class="flex items-center justify-between">
                    <h3 id="monthly-summary-heading" class="text-sm font-bold text-brand-ink">
                        Ringkasan Bulan {{ $monthLabel }}
                    </h3>
                    <a href="{{ route('attendance.history') }}" class="text-xs font-semibold text-brand-navy hover:underline">
                        Lihat Detail
                    </a>
                </div>

                <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <x-card padding="p-4">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-xs font-semibold text-muted">Hadir</p>
                            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-success-soft text-success-ink" aria-hidden="true">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            </span>
                        </div>
                        <p class="mt-2 text-3xl font-bold tabular-nums text-success-ink">
                            {{ $monthlySummary['hadir'] }}
                        </p>
                        <p class="mt-1 text-[11px] text-muted">
                            @if ($monthlySummary['wfh'] > 0)
                                {{ $monthlySummary['present'] }} kantor, {{ $monthlySummary['wfh'] }} WFH
                            @else
                                Tepat waktu &amp; WFH
                            @endif
                        </p>
                    </x-card>

                    <x-card padding="p-4">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-xs font-semibold text-muted">Terlambat</p>
                            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-warning-soft text-warning-ink" aria-hidden="true">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 7v5l3 2"/></svg>
                            </span>
                        </div>
                        <p class="mt-2 text-3xl font-bold tabular-nums text-warning-ink">
                            {{ $monthlySummary['terlambat'] }}
                        </p>
                        <p class="mt-1 text-[11px] text-muted">Melewati batas toleransi</p>
                    </x-card>

                    <x-card padding="p-4">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-xs font-semibold text-muted">Izin / Cuti</p>
                            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-info-soft text-info-ink" aria-hidden="true">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="4" width="18" height="18" rx="3"/><path stroke-linecap="round" d="M16 2v4M8 2v4M3 10h18"/></svg>
                            </span>
                        </div>
                        <p class="mt-2 text-3xl font-bold tabular-nums text-info-ink">
                            {{ $monthlySummary['izin_cuti'] }}
                        </p>
                        <p class="mt-1 text-[11px] text-muted">
                            {{ $monthlySummary['leave'] }} cuti, {{ $monthlySummary['permission'] }} izin
                        </p>
                    </x-card>

                    <x-card padding="p-4">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-xs font-semibold text-muted">Sakit</p>
                            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-violet-soft text-violet-ink" aria-hidden="true">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m6-6H6"/></svg>
                            </span>
                        </div>
                        <p class="mt-2 text-3xl font-bold tabular-nums text-violet-ink">
                            {{ $monthlySummary['sakit'] }}
                        </p>
                        <p class="mt-1 text-[11px] text-muted">Izin sakit tercatat</p>
                    </x-card>
                </div>
            </section>

            {{-- Saldo Cuti Tahunan & Akses Cepat --}}
            <div class="grid gap-3 md:grid-cols-2">
                <x-card class="flex flex-col justify-between gap-4">
                    <div>
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-xs font-semibold tracking-wide text-muted uppercase">
                                Saldo Cuti Tahunan {{ $nowWib->year }}
                            </span>
                            <x-badge :variant="$remainingLeaveDays > 0 ? 'success' : 'neutral'">
                                {{ $remainingLeaveDays }} Hari Tersisa
                            </x-badge>
                        </div>

                        <div class="mt-2 flex items-baseline gap-2">
                            <span class="text-3xl font-bold tabular-nums text-brand-ink">{{ $remainingLeaveDays }}</span>
                            <span class="text-xs text-muted">
                                @if ($leaveBalance && $leaveBalance->entitled_days > 0)
                                    dari {{ $leaveBalance->entitled_days }} hari hak cuti ({{ $leaveBalance->used_days }} terpakai)
                                @elseif ($employee->employment_type === EmploymentType::INTERN)
                                    hari (kuota cuti tahunan tidak berlaku untuk magang)
                                @else
                                    hari tersedia tahun ini
                                @endif
                            </span>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2 border-t border-border pt-3">
                        <x-button href="{{ route('leave.create') }}" variant="checkin" size="sm" class="flex-1">
                            Ajukan Izin / Cuti
                        </x-button>
                        <x-button href="{{ route('leave.index') }}" variant="secondary" size="sm" class="flex-1">
                            Daftar Pengajuan
                        </x-button>
                    </div>
                </x-card>

                <x-card class="flex flex-col justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-bold text-brand-ink">Tautan Cepat</h3>
                        <p class="mt-0.5 text-xs text-muted">Akses cepat layanan harian dan pengelolaan unit kerja.</p>
                    </div>

                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <a
                            href="{{ route('attendance.history') }}"
                            class="flex min-h-11 items-center justify-between rounded-xl border border-border bg-surface px-3.5 py-2.5 text-xs font-semibold text-brand-ink transition-colors duration-150 hover:border-brand-navy hover:bg-brand-navy-soft/40"
                        >
                            <span>Riwayat Absensi</span>
                            <svg class="h-4 w-4 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </a>

                        <a
                            href="{{ route('leave.create') }}"
                            class="flex min-h-11 items-center justify-between rounded-xl border border-border bg-surface px-3.5 py-2.5 text-xs font-semibold text-brand-ink transition-colors duration-150 hover:border-brand-navy hover:bg-brand-navy-soft/40"
                        >
                            <span>Ajukan Cuti / WFH</span>
                            <svg class="h-4 w-4 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </a>

                        @if ($canViewTeamAttendance && $teamAttendanceUrl)
                            <a
                                href="{{ $teamAttendanceUrl }}"
                                class="flex min-h-11 items-center justify-between rounded-xl border border-border bg-surface px-3.5 py-2.5 text-xs font-semibold text-brand-ink transition-colors duration-150 hover:border-brand-navy hover:bg-brand-navy-soft/40"
                            >
                                <span>Absensi Tim</span>
                                <svg class="h-4 w-4 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                </svg>
                            </a>
                        @endif

                        @if ($canManageEmployees)
                            <a
                                href="{{ route('management.employees.index') }}"
                                class="flex min-h-11 items-center justify-between rounded-xl border border-border bg-surface px-3.5 py-2.5 text-xs font-semibold text-brand-ink transition-colors duration-150 hover:border-brand-navy hover:bg-brand-navy-soft/40"
                            >
                                <span>Data Karyawan</span>
                                <svg class="h-4 w-4 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                </svg>
                            </a>
                        @endif
                    </div>
                </x-card>
            </div>
        @endif
    </div>
</x-app-layout>
