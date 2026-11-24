@php
    $modeTitle = match ($mode) {
        'check_out' => 'Absen Pulang',
        'completed' => 'Absensi Selesai',
        'blocked' => 'Absensi Tidak Tersedia',
        default => 'Absen Masuk',
    };
@endphp

<x-app-layout title="Absen Selfie" subtitle="{{ $modeTitle }} · {{ $employee->user?->name }}" :hide-bottom-nav="true">
    <div
        x-data="attendanceCamera(@js([
            'checkInUrl' => route('attendance.check-in'),
            'checkOutUrl' => route('attendance.check-out'),
            'mode' => $mode,
            'offices' => $officesPayload,
            'hasApprovedWfh' => $hasApprovedWfh,
            'enforceGeofenceOnCheckout' => $enforceGeofenceOnCheckout,
            'csrfToken' => csrf_token(),
            'maxWidth' => $selfieMaxWidth,
            'jpegQuality' => $selfieJpegQuality,
            'maxAcceptableAccuracy' => $maxAcceptableAccuracy,
        ]))"
        class="mx-auto flex w-full max-w-lg flex-col gap-3 pb-safe"
    >
        {{-- Bar Atas Fokus Kamera --}}
        <div class="flex items-center justify-between gap-2">
            <a
                href="{{ route('dashboard') }}"
                class="inline-flex min-h-11 items-center gap-1.5 rounded-2xl border border-border bg-card px-3.5 py-2 text-xs font-semibold text-brand-navy transition-colors duration-150 hover:bg-brand-navy-soft"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
                <span>{{ __('ui.actions.back') }}</span>
            </a>

            <div class="flex items-center gap-2">
                <div
                    x-data="serverClock({{ $serverNow->getTimestampMs() }})"
                    class="rounded-2xl border border-border bg-card px-3 py-1.5 text-right"
                >
                    <p class="text-xs font-bold tabular-nums text-brand-ink" x-text="timeText">
                        {{ $serverNow->format('H:i:s') }} WIB
                    </p>
                </div>

                <a
                    href="{{ route('attendance.history') }}"
                    class="inline-flex min-h-11 items-center rounded-2xl border border-border bg-card px-3 py-2 text-xs font-semibold text-text transition-colors duration-150 hover:bg-surface"
                >
                    {{ __('ui.nav.history') }}
                </a>
            </div>
        </div>

        @if ($mode === 'completed')
            <x-card class="space-y-4 text-center">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-success-soft text-success-ink">
                    <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>

                <div>
                    <x-badge :variant="$todayAttendance->status->badgeVariant()">
                        {{ $todayAttendance->status->label() }}
                    </x-badge>
                    <h2 class="mt-2 text-base font-bold text-brand-ink">Absensi Hari Ini Sudah Lengkap</h2>
                    <p class="mt-1 text-xs leading-relaxed text-muted">
                        {{ __('attendance.messages.already_checked_out') }}
                    </p>
                </div>

                <dl class="grid grid-cols-2 gap-3 rounded-2xl bg-surface p-3.5 text-left text-xs">
                    <div>
                        <dt class="text-muted">Jam Masuk</dt>
                        <dd class="mt-0.5 font-bold tabular-nums text-brand-ink">
                            {{ $todayAttendance->check_in_at?->setTimezone($timezone)->format('H:i') ?? '-' }} WIB
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted">Jam Pulang</dt>
                        <dd class="mt-0.5 font-bold tabular-nums text-brand-ink">
                            {{ $todayAttendance->check_out_at?->setTimezone($timezone)->format('H:i') ?? '-' }} WIB
                        </dd>
                    </div>
                </dl>

                <div class="flex flex-col gap-2 sm:flex-row">
                    <x-button href="{{ route('attendance.history') }}" variant="secondary" fullWidth>
                        Lihat Riwayat Absensi
                    </x-button>
                    <x-button href="{{ route('dashboard') }}" variant="primary" fullWidth>
                        Kembali ke Beranda
                    </x-button>
                </div>
            </x-card>
        @elseif ($mode === 'blocked')
            <x-card class="space-y-4 text-center">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-warning-soft text-warning-ink">
                    <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                    </svg>
                </div>

                <div>
                    @if ($todayAttendance && $todayAttendance->status->blocksSelfieCheckIn())
                        <x-badge :variant="$todayAttendance->status->badgeVariant()">
                            {{ $todayAttendance->status->label() }}
                        </x-badge>
                    @else
                        <x-badge variant="warning">Tidak Dapat Absen</x-badge>
                    @endif

                    <h2 class="mt-2 text-base font-bold text-brand-ink">Absen Selfie Tidak Diperlukan</h2>
                    <p class="mt-1 text-xs leading-relaxed text-muted">
                        {{ $blockedReason }}
                    </p>
                </div>

                <div class="flex flex-col gap-2 sm:flex-row">
                    <x-button href="{{ route('attendance.history') }}" variant="secondary" fullWidth>
                        Lihat Riwayat Absensi
                    </x-button>
                    <x-button href="{{ route('dashboard') }}" variant="primary" fullWidth>
                        Kembali ke Beranda
                    </x-button>
                </div>
            </x-card>
        @else
            {{-- Info Fase Absensi & Jadwal --}}
            <div class="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-border bg-card px-4 py-2.5">
                <div class="flex items-center gap-2">
                    <x-badge :variant="$mode === 'check_out' ? 'navy' : 'success'">
                        {{ $modeTitle }}
                    </x-badge>
                    @if ($hasApprovedWfh)
                        <x-badge variant="info">WFH Disetujui</x-badge>
                    @endif
                </div>

                @if ($mode === 'check_out' && $todayAttendance?->check_in_at)
                    <p class="text-xs font-medium text-muted">
                        Masuk:
                        <span class="font-bold tabular-nums text-brand-ink">
                            {{ $todayAttendance->check_in_at->setTimezone($timezone)->format('H:i') }} WIB
                        </span>
                    </p>
                @elseif ($employee->workSchedule)
                    <p class="text-xs font-medium text-muted">
                        Jadwal:
                        <span class="font-semibold tabular-nums text-text">
                            {{ substr((string) $employee->workSchedule->check_in_time, 0, 5) }}–{{ substr((string) $employee->workSchedule->check_out_time, 0, 5) }} WIB
                        </span>
                    </p>
                @endif
            </div>

            {{-- Viewport Kamera Full-Screen Mobile-First --}}
            <div class="relative aspect-[3/4] w-full overflow-hidden rounded-3xl border border-border bg-brand-ink shadow-lg sm:aspect-[4/3]">
                <video
                    x-ref="video"
                    autoplay
                    playsinline
                    muted
                    x-show="!previewUrl && !cameraError"
                    class="h-full w-full -scale-x-100 object-cover"
                ></video>

                <img
                    x-show="previewUrl"
                    x-cloak
                    :src="previewUrl"
                    alt="Pratinjau foto selfie absensi"
                    class="h-full w-full object-cover"
                >

                {{-- Panduan Oval Wajah + Overlay --}}
                <div
                    x-show="!previewUrl && !cameraError"
                    class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center bg-overlay/25 p-4"
                    aria-hidden="true"
                >
                    <div class="relative flex h-64 w-48 items-center justify-center rounded-full border-2 border-dashed border-on-brand/70 sm:h-68 sm:w-52">
                        <svg class="h-full w-full text-on-brand/85" viewBox="0 0 200 260" fill="none">
                            <ellipse
                                cx="100"
                                cy="130"
                                rx="86"
                                ry="116"
                                stroke="currentColor"
                                stroke-width="2.5"
                                stroke-dasharray="10 8"
                            />
                        </svg>
                    </div>
                    <span class="mt-3 rounded-full bg-overlay px-3.5 py-1 text-[11px] font-semibold text-on-brand">
                        Posisikan wajah di dalam bingkai oval
                    </span>
                </div>

                {{-- Chip Status Lokasi Live di Atas Viewport --}}
                <div class="absolute top-3 right-3 left-3 z-10 flex flex-wrap items-center justify-between gap-2">
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-semibold shadow-sm"
                        :class="{
                            'bg-success-soft text-success-ink border-brand-green/30': locationChipTone === 'success',
                            'bg-warning-soft text-warning-ink border-warning/30': locationChipTone === 'warning',
                            'bg-info-soft text-info-ink border-info/30': locationChipTone === 'info',
                            'bg-danger-soft text-danger-ink border-danger/30': locationChipTone === 'danger',
                        }"
                    >
                        <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21s-6-5.33-6-10a6 6 0 1112 0c0 4.67-6 10-6 10z" />
                            <circle cx="12" cy="11" r="2.2" />
                        </svg>
                        <span x-text="locationChipText">Mencari lokasi GPS...</span>
                    </span>

                    <span
                        x-show="accuracy !== null"
                        x-cloak
                        class="rounded-full bg-overlay px-2.5 py-1 text-[11px] font-semibold tabular-nums text-on-brand"
                        x-text="'GPS ±' + Math.round(accuracy) + ' m'"
                    ></span>
                </div>

                {{-- Overlay Memuat Kamera --}}
                <div
                    x-show="cameraLoading && !cameraError && !previewUrl"
                    class="absolute inset-0 z-20 flex flex-col items-center justify-center gap-2 bg-overlay text-on-brand"
                >
                    <svg class="h-8 w-8 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="9" class="opacity-25" />
                        <path stroke-linecap="round" d="M21 12a9 9 0 00-9-9" />
                    </svg>
                    <p class="text-xs font-semibold">Mengaktifkan kamera depan...</p>
                </div>

                {{-- Overlay Error Kamera --}}
                <div
                    x-show="cameraError"
                    x-cloak
                    class="absolute inset-0 z-20 flex flex-col items-center justify-center bg-overlay p-6 text-center"
                >
                    <div class="w-full max-w-sm space-y-3 rounded-2xl border border-danger/30 bg-card p-5 text-left shadow-lg">
                        <div class="flex items-start gap-2.5">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-danger-soft text-danger-ink">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 8a2 2 0 012-2h2l1.5-2h7L17 6h2a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V8z" />
                                    <path stroke-linecap="round" d="M4 4l16 16" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-sm font-bold text-danger-ink">Kamera Tidak Dapat Diakses</h2>
                                <p class="mt-1 text-xs leading-relaxed text-text" x-text="cameraError"></p>
                            </div>
                        </div>

                        <x-button
                            type="button"
                            variant="primary"
                            size="sm"
                            fullWidth
                            x-on:click="startCamera()"
                        >
                            Coba Lagi
                        </x-button>
                    </div>
                </div>
            </div>

            {{-- Kanvas Tersembunyi untuk Capture & Resize Foto --}}
            <canvas x-ref="canvas" class="hidden" aria-hidden="true"></canvas>

            {{-- Peringatan GPS / Akurasi Rendah / Error Lokasi --}}
            <div
                x-show="locationError"
                x-cloak
                class="space-y-2.5 rounded-2xl border border-danger/30 bg-danger-soft p-3.5 text-xs text-danger-ink"
                role="alert"
            >
                <p class="font-bold">Lokasi GPS Diperlukan</p>
                <p class="leading-relaxed" x-text="locationError"></p>
                <x-button
                    type="button"
                    variant="secondary"
                    size="sm"
                    x-on:click="startGeolocation()"
                >
                    Perbarui Lokasi
                </x-button>
            </div>

            <div
                x-show="!locationError && hasLowAccuracy"
                x-cloak
                class="rounded-2xl border border-warning/30 bg-warning-soft px-3.5 py-2.5 text-xs text-warning-ink"
            >
                Akurasi GPS saat ini rendah (lebih dari {{ $maxAcceptableAccuracy }} m). Absensi tetap dapat dikirim, namun akan ditandai untuk tinjauan HR.
            </div>

            <div
                x-show="!locationError && !hasApprovedWfh && nearestOffice && !isInsideOffice && lat !== null"
                x-cloak
                class="rounded-2xl border border-warning/30 bg-warning-soft px-3.5 py-2.5 text-xs text-warning-ink"
            >
                Anda berada di luar radius <span class="font-bold" x-text="nearestOffice?.name"></span> (batas <span x-text="nearestOffice?.radius_meters"></span> m). Pastikan berada di area kantor kecuali memiliki persetujuan WFH.
            </div>

            {{-- Pesan Error Server (422 / 429) --}}
            <div
                x-show="submitError"
                x-cloak
                class="rounded-2xl border border-danger/30 bg-danger-soft p-3.5 text-xs font-medium text-danger-ink"
                role="alert"
            >
                <p x-text="submitError"></p>
            </div>

            <div
                x-show="submitSuccess"
                x-cloak
                class="rounded-2xl border border-brand-green/30 bg-success-soft p-3.5 text-xs font-semibold text-success-ink"
                role="status"
            >
                <p x-text="submitSuccess"></p>
            </div>

            {{-- Kontrol Shutter atau Pratinjau Ulangi & Kirim --}}
            <div class="pt-1">
                <div x-show="!previewUrl" class="flex flex-col items-center justify-center gap-2">
                    <button
                        type="button"
                        x-on:click="capture()"
                        x-bind:disabled="!canCapture"
                        aria-label="Ambil Foto Selfie"
                        class="inline-flex h-16 w-16 cursor-pointer items-center justify-center rounded-full border-4 border-brand-navy bg-brand-green-strong text-on-brand shadow-lg transition-transform duration-150 hover:bg-brand-green active:scale-95 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 8a2 2 0 012-2h2l1.5-2h7L17 6h2a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V8z" />
                            <circle cx="12" cy="13" r="3.5" />
                        </svg>
                    </button>
                    <span class="text-xs font-medium text-muted">
                        Tekan tombol kamera untuk mengambil selfie {{ $mode === 'check_out' ? 'pulang' : 'masuk' }}
                    </span>
                </div>

                <div x-show="previewUrl" x-cloak class="grid grid-cols-2 gap-3">
                    <x-button
                        type="button"
                        variant="secondary"
                        size="lg"
                        fullWidth
                        x-on:click="retake()"
                        x-bind:disabled="submitting"
                    >
                        Ulangi
                    </x-button>

                    <x-button
                        type="button"
                        variant="checkin"
                        size="lg"
                        fullWidth
                        x-on:click="submit()"
                        x-bind:disabled="!canSubmit"
                    >
                        <svg
                            x-show="submitting"
                            x-cloak
                            class="h-4 w-4 animate-spin"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2.5"
                        >
                            <circle cx="12" cy="12" r="9" class="opacity-25" />
                            <path stroke-linecap="round" d="M21 12a9 9 0 00-9-9" />
                        </svg>
                        <span x-text="submitting ? 'Mengirim...' : 'Kirim'">Kirim</span>
                    </x-button>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
