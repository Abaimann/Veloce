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
    subtitle="Ringkasan Baca-Saja Fase 1 — {{ $monthLabel }}"
    :unread-notifications-count="$unreadNotificationsCount"
>
    <div class="space-y-4">
        {{-- Hero Jam Server WIB & Identitas Peran --}}
        <section
            x-data="serverClock({{ $nowWib->getTimestampMs() }})"
            aria-label="Informasi Waktu Server dan Peran Payroll"
            class="relative isolate overflow-hidden rounded-3xl bg-gradient-to-br from-brand-navy to-brand-ink p-5 text-on-brand shadow-lg shadow-brand-ink/15 sm:p-6"
        >
            <div class="pointer-events-none absolute -top-20 -right-20 h-56 w-56 rounded-full bg-brand-green/20 blur-3xl" aria-hidden="true"></div>
            <div class="relative flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs font-medium text-on-brand/80">Ringkasan Agregat Kepegawaian</p>
                    <h2 class="mt-0.5 truncate text-xl font-bold tracking-tight text-on-brand sm:text-2xl">{{ $user->name }}</h2>
                    <p class="mt-0.5 text-xs text-on-brand/80">{{ __('ui.company_name') }}</p>
                </div>

                <span class="rounded-full bg-on-brand/15 px-3 py-1 text-[11px] font-semibold text-on-brand ring-1 ring-on-brand/15">
                    Payroll (Baca-Saja)
                </span>
            </div>

            <div class="relative mt-5 border-t border-on-brand/15 pt-4">
                <p class="text-3xl font-bold tracking-tight text-on-brand tabular-nums sm:text-4xl" x-text="timeText">
                    {{ $nowWib->format('H:i:s') }} WIB
                </p>
                <p class="mt-1 text-xs text-on-brand/80" x-text="dateText">
                    {{ $nowWib->translatedFormat('l, d F Y') }}
                </p>
            </div>
        </section>

        {{-- Keterangan Tegas Batasan Baca-Saja Fase 1 --}}
        <div class="rounded-2xl border border-info/25 bg-info-soft/55 p-4 shadow-xs sm:p-5">
            <div class="flex items-start gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-info-soft text-info-ink">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-bold text-info-ink">Mode Baca-Saja Fase 1 (Read-Only)</p>
                    <p class="mt-0.5 text-xs leading-relaxed text-text">
                        Pada Fase 1, peran Payroll hanya memiliki akses baca-saja terhadap ringkasan jumlah karyawan aktif, departemen aktif, dan rekap agregat status absensi bulanan. Tidak tersedia aksi ubah absensi, persetujuan izin/cuti, maupun akses foto selfie dan lampiran medis.
                    </p>
                </div>
            </div>
        </div>

        {{-- Ringkasan Organisasi & Tipe Kepegawaian --}}
        <section aria-labelledby="payroll-org-heading" class="space-y-2.5">
            <h3 id="payroll-org-heading" class="text-sm font-bold text-brand-ink">
                Ringkasan Karyawan Aktif &amp; Unit Organisasi
            </h3>

            <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
                <x-card padding="p-4">
                    <p class="text-xs font-semibold text-muted">Total Karyawan Aktif</p>
                    <p class="mt-2 text-2xl font-bold tabular-nums text-brand-ink">{{ $totalActiveEmployees }}</p>
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
