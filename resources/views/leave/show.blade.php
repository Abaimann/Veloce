@php
    $timezone = (string) config('attendance.timezone', 'Asia/Jakarta');
    $user = auth()->user();
    $employee = $leaveRequest->employee;
    $isSingleDay = $leaveRequest->start_date->isSameDay($leaveRequest->end_date);

    $attachmentPath = $leaveRequest->attachment_path;
    $attachmentExtension = is_string($attachmentPath)
        ? strtolower(pathinfo($attachmentPath, PATHINFO_EXTENSION))
        : '';
    $isImageAttachment = in_array($attachmentExtension, ['jpg', 'jpeg', 'png', 'webp'], true);
    $backUrl = $user?->employee !== null ? route('leave.index') : route('dashboard');
@endphp

<x-app-layout title="Detail Pengajuan {{ $leaveRequest->leaveType->name }}" subtitle="{{ $employee->user?->name ?? '-' }} · {{ $employee->employee_number }}">
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <x-button href="{{ $backUrl }}" variant="ghost" size="sm">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
                <span>Kembali ke Daftar Izin</span>
            </x-button>

            <x-badge :variant="$leaveRequest->status->badgeVariant()">
                {{ $leaveRequest->status->label() }}
            </x-badge>
        </div>

        {{-- Rincian Lengkap Pengajuan --}}
        <x-card class="space-y-4">
            <div class="flex flex-col gap-2 border-b border-border pb-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <h2 class="text-lg font-bold text-brand-ink">
                        {{ $leaveRequest->leaveType->name }}
                    </h2>
                    <p class="mt-0.5 text-xs text-muted">
                        {{ $employee->user?->name ?? '-' }} ({{ $employee->employee_number }})
                        @if ($employee->department)
                            · {{ $employee->department->name }}
                        @endif
                        @if ($employee->division)
                            · {{ $employee->division->name }}
                        @endif
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <x-badge variant="navy">
                        {{ $leaveRequest->total_work_days }} hari kerja
                    </x-badge>
                    <x-badge variant="{{ $leaveRequest->leaveType->is_paid ? 'success' : 'neutral' }}">
                        {{ $leaveRequest->leaveType->is_paid ? 'Berbayar' : 'Tanpa Gaji' }}
                    </x-badge>
                </div>
            </div>

            <dl class="grid gap-3 rounded-2xl bg-surface p-4 text-xs sm:grid-cols-2">
                <div>
                    <dt class="text-muted">Rentang Tanggal</dt>
                    <dd class="mt-1 font-bold text-brand-ink">
                        @if ($isSingleDay)
                            {{ $leaveRequest->start_date->translatedFormat('l, d F Y') }}
                        @else
                            {{ $leaveRequest->start_date->translatedFormat('d M Y') }} – {{ $leaveRequest->end_date->translatedFormat('d M Y') }}
                        @endif
                    </dd>
                </div>

                <div>
                    <dt class="text-muted">Durasi Efektif</dt>
                    <dd class="mt-1 font-bold tabular-nums text-text">
                        {{ $leaveRequest->total_work_days }} hari kerja
                    </dd>
                </div>

                <div>
                    <dt class="text-muted">Waktu Pengajuan</dt>
                    <dd class="mt-1 font-semibold tabular-nums text-text">
                        {{ $leaveRequest->created_at?->setTimezone($timezone)->translatedFormat('d M Y, H:i') }} WIB
                    </dd>
                </div>

                <div>
                    @if ($leaveRequest->cancelled_at)
                        <dt class="text-muted">Waktu Dibatalkan</dt>
                        <dd class="mt-1 font-semibold tabular-nums text-text">
                            {{ $leaveRequest->cancelled_at->setTimezone($timezone)->translatedFormat('d M Y, H:i') }} WIB
                        </dd>
                    @elseif ($leaveRequest->finalized_at)
                        <dt class="text-muted">Waktu Keputusan Final</dt>
                        <dd class="mt-1 font-semibold tabular-nums text-text">
                            {{ $leaveRequest->finalized_at->setTimezone($timezone)->translatedFormat('d M Y, H:i') }} WIB
                        </dd>
                    @else
                        <dt class="text-muted">Tahap Aktif Saat Ini</dt>
                        <dd class="mt-1 font-semibold text-warning-ink">
                            @php
                                $activeStep = $leaveRequest->currentApproval();
                            @endphp
                            @if ($activeStep)
                                Tahap {{ $activeStep->step }} · {{ __('ui.roles.' . $activeStep->role) }}
                            @else
                                Menunggu diproses
                            @endif
                        </dd>
                    @endif
                </div>
            </dl>

            <div class="space-y-1.5">
                <h3 class="text-xs font-semibold tracking-wide text-muted uppercase">Alasan Pengajuan</h3>
                <p class="rounded-2xl border border-border bg-surface p-4 text-sm leading-relaxed text-text whitespace-pre-line">{{ $leaveRequest->reason }}</p>
            </div>

            @if ($attachmentPath)
                <div class="space-y-2">
                    <h3 class="text-xs font-semibold tracking-wide text-muted uppercase">Lampiran Pendukung</h3>

                    @if ($isImageAttachment)
                        <div class="space-y-2.5 rounded-2xl border border-border bg-surface p-3.5">
                            <a
                                href="{{ route('media.leave.attachment', $leaveRequest) }}"
                                target="_blank"
                                rel="noopener"
                                class="block overflow-hidden rounded-xl border border-border bg-card"
                            >
                                <img
                                    src="{{ route('media.leave.attachment', $leaveRequest) }}"
                                    alt="Lampiran pengajuan {{ $leaveRequest->leaveType->name }}"
                                    class="max-h-80 w-full object-contain"
                                >
                            </a>
                            <div class="flex justify-end">
                                <x-button
                                    href="{{ route('media.leave.attachment', $leaveRequest) }}"
                                    target="_blank"
                                    rel="noopener"
                                    variant="secondary"
                                    size="sm"
                                >
                                    Buka Lampiran Penuh
                                </x-button>
                            </div>
                        </div>
                    @else
                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-border bg-surface p-4">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-info-soft text-info-ink">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-brand-ink">Dokumen Lampiran</p>
                                    <p class="text-xs text-muted">Berkas privat terlampir pada pengajuan ini</p>
                                </div>
                            </div>

                            <x-button
                                href="{{ route('media.leave.attachment', $leaveRequest) }}"
                                target="_blank"
                                rel="noopener"
                                variant="secondary"
                                size="sm"
                            >
                                Lihat Lampiran
                            </x-button>
                        </div>
                    @endif
                </div>
            @endif

            @can('cancel', $leaveRequest)
                <div class="flex justify-end border-t border-border pt-4">
                    <form method="POST" action="{{ route('leave.cancel', $leaveRequest) }}">
                        @csrf
                        <x-button type="submit" variant="danger-outline" size="md">
                            Batalkan Pengajuan
                        </x-button>
                    </form>
                </div>
            @endcan
        </x-card>

        {{-- Linimasa Persetujuan Vertikal --}}
        <x-card class="space-y-4">
            <div>
                <h2 class="text-base font-bold text-brand-ink">Linimasa Persetujuan</h2>
                <p class="mt-0.5 text-xs text-muted">Progres persetujuan berjenjang sesuai struktur organisasi.</p>
            </div>

            <x-approval-timeline
                :approvals="$leaveRequest->approvals"
                :current-step="$leaveRequest->current_step"
            />
        </x-card>
    </div>
</x-app-layout>
