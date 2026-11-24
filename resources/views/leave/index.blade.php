@php
    $remainingDays = $leaveBalance?->remainingDays() ?? 0;
    $entitledDays = $leaveBalance?->entitled_days ?? 0;
    $usedDays = $leaveBalance?->used_days ?? 0;
    $usagePercent = $entitledDays > 0 ? min(100, (int) round(($usedDays / $entitledDays) * 100)) : 0;
    $isIntern = $employee->employment_type === \App\Enums\EmploymentType::INTERN;
@endphp

<x-app-layout title="Izin & Cuti" subtitle="Kelola pengajuan cuti tahunan, sakit, izin, dan WFH">
    <div class="space-y-4">
        {{-- Ringkasan Saldo Cuti Tahunan & Tombol Ajukan --}}
        <x-card>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0 flex-1 space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs font-semibold tracking-wide text-muted uppercase">
                            Saldo Cuti Tahunan {{ $currentYear }}
                        </span>
                        <x-badge variant="{{ $remainingDays > 0 ? 'success' : 'neutral' }}">
                            {{ $remainingDays }} Hari Tersisa
                        </x-badge>
                    </div>

                    @if ($entitledDays > 0)
                        <div class="flex items-baseline gap-2">
                            <span class="text-3xl font-bold tabular-nums text-brand-ink">{{ $remainingDays }}</span>
                            <span class="text-xs font-medium text-muted">
                                dari {{ $entitledDays }} hari hak cuti ({{ $usedDays }} hari terpakai)
                            </span>
                        </div>

                        <div class="h-2.5 w-full max-w-md overflow-hidden rounded-full bg-surface" aria-hidden="true">
                            <div
                                class="h-full rounded-full bg-brand-green-strong transition-[width] duration-300"
                                style="width: {{ $usagePercent }}%"
                            ></div>
                        </div>
                    @else
                        <p class="text-sm font-bold text-brand-ink">0 Hari Kuota Cuti Tahunan</p>
                        <p class="text-xs leading-relaxed text-muted">
                            {{ $isIntern
                                ? 'Status magang tidak memiliki kuota cuti tahunan berbayar. Anda tetap dapat mengajukan Sakit, Izin, Cuti Tanpa Gaji, atau WFH.'
                                : 'Kuota cuti tahunan Anda tahun ini sebesar 0 hari. Anda tetap dapat mengajukan jenis izin lain yang tersedia.' }}
                        </p>
                    @endif
                </div>

                <div class="shrink-0">
                    <x-button href="{{ route('leave.create') }}" variant="checkin" size="md" class="w-full sm:w-auto">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" />
                        </svg>
                        <span>Ajukan Izin / Cuti</span>
                    </x-button>
                </div>
            </div>
        </x-card>

        {{-- Filter Status Chip --}}
        <div class="flex flex-wrap items-center gap-2" role="navigation" aria-label="Filter status pengajuan">
            <a
                href="{{ route('leave.index') }}"
                class="inline-flex min-h-11 items-center rounded-full border px-4 py-2 text-xs font-semibold transition-colors duration-150 {{ $selectedStatus === null ? 'border-brand-navy bg-brand-navy text-on-brand' : 'border-border bg-card text-muted hover:bg-surface hover:text-text' }}"
            >
                Semua
            </a>

            @foreach ($statuses as $status)
                @php
                    $isActive = $selectedStatus === $status;
                @endphp
                <a
                    href="{{ route('leave.index', ['status' => $status->value]) }}"
                    class="inline-flex min-h-11 items-center rounded-full border px-4 py-2 text-xs font-semibold transition-colors duration-150 {{ $isActive ? 'border-brand-navy bg-brand-navy text-on-brand' : 'border-border bg-card text-muted hover:bg-surface hover:text-text' }}"
                >
                    {{ $status->label() }}
                </a>
            @endforeach
        </div>

        {{-- Daftar Kartu Pengajuan --}}
        @if ($leaveRequests->isEmpty())
            <x-empty-state
                title="{{ $selectedStatus ? 'Belum ada pengajuan berstatus ' . $selectedStatus->label() : 'Belum ada pengajuan izin atau cuti' }}"
                description="{{ $selectedStatus ? 'Coba pilih filter status lain atau buat pengajuan baru.' : 'Ajukan cuti tahunan, sakit, izin keperluan pribadi, atau WFH langsung dari sini.' }}"
            >
                <x-slot:action>
                    <div class="flex flex-wrap items-center justify-center gap-2">
                        @if ($selectedStatus)
                            <x-button href="{{ route('leave.index') }}" variant="secondary" size="sm">
                                Tampilkan Semua
                            </x-button>
                        @endif
                        <x-button href="{{ route('leave.create') }}" variant="checkin" size="sm">
                            Ajukan Izin / Cuti
                        </x-button>
                    </div>
                </x-slot:action>
            </x-empty-state>
        @else
            <div class="grid gap-3 md:grid-cols-2">
                @foreach ($leaveRequests as $item)
                    @php
                        $currentApproval = $item->currentApproval();
                        $currentRole = $currentApproval?->role;
                        $approvedStepsCount = $item->approvals
                            ->where('status', \App\Enums\ApprovalStatus::APPROVED)
                            ->count();
                        $totalStepsCount = $item->approvals->count();
                        $isSingleDay = $item->start_date->isSameDay($item->end_date);
                    @endphp

                    <x-card class="flex flex-col justify-between gap-3.5 transition-colors duration-150 hover:border-brand-navy/40">
                        <div class="space-y-3">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <h2 class="truncate text-sm font-bold text-brand-ink">
                                        {{ $item->leaveType->name }}
                                    </h2>
                                    <p class="mt-0.5 text-xs font-medium text-muted">
                                        @if ($isSingleDay)
                                            {{ $item->start_date->translatedFormat('d M Y') }}
                                        @else
                                            {{ $item->start_date->translatedFormat('d M Y') }} – {{ $item->end_date->translatedFormat('d M Y') }}
                                        @endif
                                    </p>
                                </div>

                                <x-badge :variant="$item->status->badgeVariant()">
                                    {{ $item->status->label() }}
                                </x-badge>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <x-badge variant="navy">
                                    {{ $item->total_work_days }} hari kerja
                                </x-badge>

                                @if ($item->attachment_path)
                                    <x-badge variant="info">
                                        Ada Lampiran
                                    </x-badge>
                                @endif

                                @if ($totalStepsCount > 0)
                                    <span class="text-xs font-medium text-muted">
                                        Tahap {{ $approvedStepsCount }}/{{ $totalStepsCount }}
                                    </span>
                                @endif
                            </div>

                            <p class="line-clamp-2 rounded-2xl bg-surface px-3.5 py-2.5 text-xs leading-relaxed text-text">
                                {{ $item->reason }}
                            </p>
                        </div>

                        <div class="flex items-center justify-between gap-3 border-t border-border pt-3">
                            <div class="min-w-0 text-xs text-muted">
                                @if ($item->status === \App\Enums\LeaveRequestStatus::PENDING && $currentRole)
                                    <span class="truncate">
                                        Menunggu: <strong class="font-semibold text-brand-ink">{{ __('ui.roles.' . $currentRole) }}</strong>
                                    </span>
                                @else
                                    <span>
                                        Diajukan {{ $item->created_at?->setTimezone($timezone)->translatedFormat('d M Y') }}
                                    </span>
                                @endif
                            </div>

                            <x-button href="{{ route('leave.show', $item) }}" variant="secondary" size="sm" class="shrink-0">
                                Lihat Detail
                            </x-button>
                        </div>
                    </x-card>
                @endforeach
            </div>

            @if ($leaveRequests->hasPages())
                <div class="pt-1">
                    {{ $leaveRequests->links() }}
                </div>
            @endif
        @endif
    </div>
</x-app-layout>
