@php
    $formatFlag = static function (mixed $flag): string {
        if (is_array($flag) && isset($flag['message']) && is_string($flag['message']) && $flag['message'] !== '') {
            return $flag['message'];
        }

        $code = is_string($flag)
            ? $flag
            : (is_array($flag) ? (string) ($flag['code'] ?? $flag['type'] ?? '') : '');

        return match ($code) {
            'low_gps_accuracy' => __('attendance.flags.low_gps_accuracy', [
                'meters' => (int) config('attendance.max_acceptable_accuracy_meters', 100),
            ]),
            'client_clock_skew' => __('attendance.flags.client_clock_skew', [
                'minutes' => (int) ceil(((int) config('attendance.max_client_clock_skew_seconds', 300)) / 60),
            ]),
            'duplicate_selfie_hash' => __('attendance.flags.duplicate_selfie_hash'),
            default => $code !== '' ? $code : 'Anomali tercatat',
        };
    };
@endphp

<x-app-layout title="Riwayat Absensi" subtitle="Rekap kehadiran {{ $selectedMonthLabel }}">
    <div class="space-y-4">
        {{-- Filter Bulan & Aksi Absen --}}
        <x-card>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <form method="GET" action="{{ route('attendance.history') }}" class="flex flex-col gap-2 sm:flex-row sm:items-end">
                    <div class="space-y-1.5">
                        <label for="month-filter" class="block text-xs font-semibold text-text">
                            Pilih Bulan
                        </label>
                        <input
                            id="month-filter"
                            name="month"
                            type="month"
                            value="{{ $selectedMonth }}"
                            x-data
                            x-on:change="$el.form.submit()"
                            class="block min-h-12 rounded-2xl border border-border bg-card px-4 py-3 text-sm font-medium text-text shadow-xs transition-[border-color,box-shadow] duration-150 hover:border-muted/50 focus:border-brand-navy focus:outline-none focus:ring-4 focus:ring-brand-navy/10"
                        >
                    </div>

                    <x-button type="submit" variant="secondary" size="md">
                        Tampilkan
                    </x-button>
                </form>

                <x-button href="{{ route('attendance.camera') }}" variant="checkin" size="md">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8a2 2 0 012-2h2l1.5-2h7L17 6h2a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V8z" />
                        <circle cx="12" cy="13" r="3.5" />
                    </svg>
                    <span>Buka Kamera Absen</span>
                </x-button>
            </div>
        </x-card>

        {{-- Ringkasan Bulanan --}}
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <x-card padding="p-4">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs font-semibold text-muted">Hadir Tepat Waktu</p>
                    <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-success-soft text-success-ink" aria-hidden="true">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    </span>
                </div>
                <p class="mt-2 text-3xl font-bold tabular-nums text-success-ink">{{ $summary['present'] }}</p>
                <p class="mt-1 text-[11px] text-muted">{{ __('attendance.statuses.present') }}</p>
            </x-card>

            <x-card padding="p-4">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs font-semibold text-muted">Terlambat</p>
                    <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-warning-soft text-warning-ink" aria-hidden="true">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 7v5l3 2"/></svg>
                    </span>
                </div>
                <p class="mt-2 text-3xl font-bold tabular-nums text-warning-ink">{{ $summary['late'] }}</p>
                <p class="mt-1 text-[11px] text-muted">{{ __('attendance.statuses.late') }}</p>
            </x-card>

            <x-card padding="p-4">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs font-semibold text-muted">Kerja Jarak Jauh</p>
                    <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-info-soft text-info-ink" aria-hidden="true">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10.5L12 3l9 7.5V20a1 1 0 01-1 1h-5v-6H9v6H4a1 1 0 01-1-1v-9.5z"/></svg>
                    </span>
                </div>
                <p class="mt-2 text-3xl font-bold tabular-nums text-info-ink">{{ $summary['wfh'] }}</p>
                <p class="mt-1 text-[11px] text-muted">{{ __('attendance.statuses.wfh') }}</p>
            </x-card>

            <x-card padding="p-4">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs font-semibold text-muted">Cuti / Sakit / Izin</p>
                    <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-violet-soft text-violet-ink" aria-hidden="true">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="4" width="18" height="18" rx="3"/><path stroke-linecap="round" d="M16 2v4M8 2v4M3 10h18"/></svg>
                    </span>
                </div>
                <p class="mt-2 text-3xl font-bold tabular-nums text-violet-ink">{{ $summary['leave_total'] }}</p>
                <p class="mt-1 text-[11px] text-muted">Tidak hadir terjadwal</p>
            </x-card>
        </div>

        {{-- Daftar Kartu Harian Responsif --}}
        @if ($attendances->isEmpty())
            <x-empty-state
                title="Belum ada riwayat absensi"
                description="Belum ada catatan kehadiran pada bulan {{ $selectedMonthLabel }}."
            >
                <x-slot:action>
                    <x-button href="{{ route('attendance.camera') }}" variant="checkin" size="sm">
                        Absen Sekarang
                    </x-button>
                </x-slot:action>
            </x-empty-state>
        @else
            <div class="grid gap-3 md:grid-cols-2">
                @foreach ($attendances as $attendance)
                    <x-card class="flex flex-col justify-between gap-3.5">
                        <div class="space-y-3">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="text-sm font-bold text-brand-ink">
                                        {{ $attendance->work_date->translatedFormat('l, d F Y') }}
                                    </p>
                                    @if ($attendance->officeLocation)
                                        <p class="mt-0.5 truncate text-xs text-muted">
                                            {{ $attendance->officeLocation->name }}
                                            @if ($attendance->check_in_distance_meters !== null)
                                                · {{ number_format((float) $attendance->check_in_distance_meters, 0) }} m
                                            @endif
                                        </p>
                                    @endif
                                </div>

                                <x-badge :variant="$attendance->status->badgeVariant()">
                                    {{ $attendance->status->label() }}
                                </x-badge>
                            </div>

                            <dl class="grid grid-cols-2 gap-3 rounded-2xl bg-surface p-3 text-xs">
                                <div>
                                    <dt class="text-muted">Jam Masuk</dt>
                                    <dd class="mt-0.5 font-bold tabular-nums text-text">
                                        @if ($attendance->check_in_at)
                                            {{ $attendance->check_in_at->setTimezone($timezone)->format('H:i') }} WIB
                                        @else
                                            -
                                        @endif
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-muted">Jam Pulang</dt>
                                    <dd class="mt-0.5 font-bold tabular-nums text-text">
                                        @if ($attendance->check_out_at)
                                            {{ $attendance->check_out_at->setTimezone($timezone)->format('H:i') }} WIB
                                        @else
                                            -
                                        @endif
                                    </dd>
                                </div>
                            </dl>

                            @if ($attendance->hasSuspiciousFlags())
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach ($attendance->flags ?? [] as $flagKey => $flagItem)
                                        @php
                                            $resolvedFlag = is_string($flagKey) ? $flagKey : $flagItem;
                                        @endphp
                                        <x-badge variant="warning">
                                            {{ $formatFlag($resolvedFlag) }}
                                        </x-badge>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        @if ($attendance->selfie_pruned_at !== null)
                            <p class="rounded-xl bg-surface px-3 py-2 text-[11px] text-muted">
                                Foto selfie telah dibersihkan sesuai kebijakan retensi ({{ $attendance->selfie_pruned_at->setTimezone($timezone)->translatedFormat('d M Y') }}).
                            </p>
                        @elseif ($attendance->selfie_in_path || $attendance->selfie_out_path)
                            <div class="flex flex-wrap items-center gap-3 border-t border-border pt-3">
                                @if ($attendance->selfie_in_path)
                                    <a
                                        href="{{ route('media.attendance.selfie', [$attendance, 'in']) }}"
                                        target="_blank"
                                        rel="noopener"
                                        class="group flex items-center gap-2.5 rounded-xl border border-border bg-surface p-1.5 pr-3 transition-colors duration-150 hover:border-brand-navy"
                                    >
                                        <img
                                            src="{{ route('media.attendance.selfie', [$attendance, 'in']) }}"
                                            alt="Selfie masuk {{ $attendance->work_date->format('d/m/Y') }}"
                                            loading="lazy"
                                            class="h-12 w-12 rounded-lg object-cover"
                                        >
                                        <div class="text-left">
                                            <p class="text-xs font-bold text-brand-ink group-hover:text-brand-navy">Selfie Masuk</p>
                                            <p class="text-[11px] tabular-nums text-muted">
                                                {{ $attendance->check_in_at?->setTimezone($timezone)->format('H:i') ?? '-' }} WIB
                                            </p>
                                        </div>
                                    </a>
                                @endif

                                @if ($attendance->selfie_out_path)
                                    <a
                                        href="{{ route('media.attendance.selfie', [$attendance, 'out']) }}"
                                        target="_blank"
                                        rel="noopener"
                                        class="group flex items-center gap-2.5 rounded-xl border border-border bg-surface p-1.5 pr-3 transition-colors duration-150 hover:border-brand-navy"
                                    >
                                        <img
                                            src="{{ route('media.attendance.selfie', [$attendance, 'out']) }}"
                                            alt="Selfie pulang {{ $attendance->work_date->format('d/m/Y') }}"
                                            loading="lazy"
                                            class="h-12 w-12 rounded-lg object-cover"
                                        >
                                        <div class="text-left">
                                            <p class="text-xs font-bold text-brand-ink group-hover:text-brand-navy">Selfie Pulang</p>
                                            <p class="text-[11px] tabular-nums text-muted">
                                                {{ $attendance->check_out_at?->setTimezone($timezone)->format('H:i') ?? '-' }} WIB
                                            </p>
                                        </div>
                                    </a>
                                @endif
                            </div>
                        @endif
                    </x-card>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
