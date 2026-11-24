@php
    use App\Enums\AttendanceStatus;
    use App\Enums\EmploymentType;

    $profileUrl = Route::has('profile.show') ? route('profile.show') : null;

    $employmentAccent = static fn (EmploymentType $type): array => match ($type) {
        EmploymentType::STAFF => ['bg' => 'bg-brand-navy-soft', 'text' => 'text-brand-ink'],
        EmploymentType::INTERN => ['bg' => 'bg-info-soft', 'text' => 'text-info-ink'],
        EmploymentType::EXECUTIVE => ['bg' => 'bg-violet-soft', 'text' => 'text-violet-ink'],
    };

    $statusTextTone = static fn (AttendanceStatus $status): string => match ($status) {
        AttendanceStatus::PRESENT => 'text-success-ink',
        AttendanceStatus::LATE => 'text-warning-ink',
        AttendanceStatus::WFH => 'text-info-ink',
        AttendanceStatus::LEAVE, AttendanceStatus::SICK, AttendanceStatus::PERMISSION => 'text-violet-ink',
    };
@endphp

<x-app-layout
    title="Dashboard Payroll"
    subtitle="Perhitungan Gaji Sistem &amp; Rekap Kehadiran — {{ $monthLabel }}"
    :unread-notifications-count="$unreadNotificationsCount"
>
    <div class="space-y-4">
        {{-- Hero Jam Server WIB & Identitas Peran --}}
        <section
            x-data="serverClock({{ $nowWib->getTimestampMs() }})"
            aria-label="Informasi Waktu Server dan Peran Payroll"
            class="timeline-hero-blueprint rounded-3xl p-5 text-on-brand sm:p-7"
        >
            <div class="relative z-10 flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="mb-2 inline-flex items-center gap-2 rounded-full border border-on-brand/20 bg-on-brand/10 px-3 py-1 text-[11px] font-bold tracking-wider uppercase text-on-brand">
                        <span class="timeline-beacon-dot h-2 w-2 rounded-full bg-brand-green"></span>
                        Kompensasi &amp; Rekap Kehadiran
                    </div>
                    <h2 class="truncate text-2xl font-extrabold tracking-tight text-on-brand sm:text-3xl">{{ $user->name }}</h2>
                    <p class="mt-0.5 text-xs font-medium text-on-brand/80 sm:text-sm">{{ __('ui.company_name') }}</p>
                </div>

                <span class="timeline-glass-tile rounded-full px-3.5 py-1.5 text-xs font-bold text-on-brand">
                    Payroll
                </span>
            </div>

            <div class="relative z-10 mt-5 border-t border-on-brand/15 pt-5">
                <div class="timeline-glass-tile inline-block rounded-2xl px-4 py-3.5">
                    <p class="text-[11px] font-bold tracking-wider uppercase text-on-brand/70">Waktu Server WIB</p>
                    <p class="mt-1 text-3xl font-extrabold tracking-tight text-on-brand tabular-nums sm:text-4xl" x-text="timeText">
                        {{ $nowWib->format('H:i:s') }} WIB
                    </p>
                    <p class="mt-0.5 text-xs text-on-brand/80" x-text="dateText">
                        {{ $nowWib->translatedFormat('l, d F Y') }}
                    </p>
                </div>
            </div>
        </section>

        {{-- Presensi Pribadi & Akses Gaji Sistem --}}
        <div class="grid gap-3 md:grid-cols-2">
            @if ($employee !== null)
                <x-card class="flex flex-col justify-between gap-3">
                    <div>
                        <p class="text-sm font-bold text-brand-ink">Presensi Harian Anda</p>
                        <p class="mt-0.5 text-xs text-muted">
                            @if ($todayAttendance === null)
                                Anda belum melakukan absen masuk hari ini.
                            @elseif ($todayAttendance->check_out_at === null)
                                Masuk pukul {{ $todayAttendance->check_in_at?->setTimezone('Asia/Jakarta')->format('H:i') }} WIB — jangan lupa absen pulang.
                            @else
                                Absen lengkap ({{ $todayAttendance->check_in_at?->setTimezone('Asia/Jakarta')->format('H:i') }} – {{ $todayAttendance->check_out_at?->setTimezone('Asia/Jakarta')->format('H:i') }} WIB).
                            @endif
                        </p>
                    </div>
                    <div>
                        @if ($todayAttendance === null)
                            <x-button href="{{ route('attendance.camera') }}" variant="checkin" size="sm">
                                Absen Masuk
                            </x-button>
                        @elseif ($todayAttendance->check_out_at === null && ! $todayAttendance->status->blocksSelfieCheckIn())
                            <x-button href="{{ route('attendance.camera') }}" variant="primary" size="sm">
                                Absen Pulang
                            </x-button>
                        @else
                            <x-button href="{{ route('attendance.history') }}" variant="secondary" size="sm">
                                Riwayat Absensi
                            </x-button>
                        @endif
                    </div>
                </x-card>
            @endif

            <x-card class="flex flex-col justify-between gap-3 border-brand-green/30 bg-brand-green-soft/35">
                <div>
                    <p class="text-sm font-bold text-success-ink">Gaji Terhitung Sistem ({{ $monthLabel }})</p>
                    <p class="mt-1 text-xl font-bold text-brand-ink tabular-nums">
                        @if ($payrollTotalNet !== null)
                            Rp {{ number_format($payrollTotalNet, 0, ',', '.') }}
                        @else
                            Siap Dihitung Otomatis
                        @endif
                    </p>
                    <p class="mt-0.5 text-xs text-muted">
                        Kalkulasi otomatis dari gaji pokok, tunjangan, dan rekap telat/alpha bulanan.
                    </p>
                </div>
                <div>
                    <x-button href="{{ route('payroll.index') }}" variant="checkin" size="sm">
                        Lihat Rincian Gaji Sistem
                    </x-button>
                </div>
            </x-card>
        </div>

        {{-- Ringkasan Organisasi & Tipe Kepegawaian --}}
        <section aria-labelledby="payroll-org-heading" class="space-y-2.5">
            <h3 id="payroll-org-heading" class="text-sm font-bold text-brand-ink">
                Ringkasan Karyawan Aktif &amp; Unit Organisasi
            </h3>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                <x-card padding="p-3.5 sm:p-4">
                    <p class="text-[11px] font-semibold text-muted sm:text-xs">Total Karyawan Aktif</p>
                    <p class="mt-1.5 text-xl font-bold tabular-nums text-brand-ink sm:mt-2 sm:text-2xl">{{ $totalActiveEmployees }}</p>
                    <span class="mt-2 inline-flex rounded-full bg-brand-navy-soft px-2.5 py-0.5 text-[11px] font-semibold text-brand-ink">
                        Seluruh Tipe
                    </span>
                </x-card>

                <x-card padding="p-4">
                    <p class="text-xs font-semibold text-muted">Departemen Aktif</p>
                    <p class="mt-2 text-2xl font-bold tabular-nums text-brand-ink">{{ $activeDepartmentsCount }}</p>
                    <span class="mt-2 inline-flex rounded-full bg-surface px-2.5 py-0.5 text-[11px] font-semibold text-muted">
                        {{ $activeDivisionsCount }} Divisi Aktif
                    </span>
                </x-card>

                @foreach ($employmentTypes as $employmentType)
                    @php
                        $tone = $employmentAccent($employmentType);
                    @endphp
                    <x-card padding="p-4">
                        <p class="text-xs font-semibold text-muted">{{ $employmentType->label() }}</p>
                        <p class="mt-2 text-2xl font-bold tabular-nums text-brand-ink">
                            {{ $employmentCounts[$employmentType->value] ?? 0 }}
                        </p>
                        <span class="mt-2 inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $tone['bg'] }} {{ $tone['text'] }}">
                            {{ strtoupper($employmentType->value) }}
                        </span>
                    </x-card>
                @endforeach
            </div>
        </section>

        {{-- Rekap Status Absensi Bulan WIB Berjalan --}}
        <section aria-labelledby="payroll-attendance-heading" class="space-y-2.5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 id="payroll-attendance-heading" class="text-sm font-bold text-brand-ink">
                    Rekap Status Absensi Bulan {{ $monthLabel }}
                </h3>
                <x-badge variant="navy">
                    {{ $totalMonthlyAttendanceRecords }} Catatan Tercatat
                </x-badge>
            </div>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                @foreach ($attendanceStatuses as $status)
                    <x-card padding="p-4" class="flex flex-col justify-between gap-2">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-xs font-semibold text-muted">{{ $status->label() }}</p>
                        </div>
                        <p class="text-2xl font-bold tabular-nums {{ $statusTextTone($status) }}">
                            {{ $monthlyAttendanceSummary[$status->value] ?? 0 }}
                        </p>
                        <div>
                            <x-badge :variant="$status->badgeVariant()">
                                {{ $status->label() }}
                            </x-badge>
                        </div>
                    </x-card>
                @endforeach
            </div>
        </section>

        @if ($profileUrl)
            <x-card>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-bold text-brand-ink">Keamanan Akun Payroll</p>
                        <p class="mt-0.5 text-xs text-muted">Perbarui kata sandi akun Anda secara berkala melalui halaman profil.</p>
                    </div>
                    <x-button href="{{ $profileUrl }}" variant="secondary" size="sm" class="shrink-0">
                        Buka Profil Akun
                    </x-button>
                </div>
            </x-card>
        @endif
    </div>
</x-app-layout>
