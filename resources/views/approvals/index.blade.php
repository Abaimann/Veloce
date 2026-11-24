<x-app-layout
    title="Kotak Persetujuan"
    subtitle="Tinjau dan tindak lanjuti pengajuan izin, cuti, dan WFH tim Anda"
    :pending-approvals-count="$pendingRequests->count()"
>
    <div class="space-y-6">
        @if ($errors->any())
            <div class="rounded-2xl border border-danger/30 bg-danger-soft p-4 text-xs text-danger-ink" role="alert">
                <p class="font-bold">Tindakan belum dapat diproses</p>
                <ul class="mt-1.5 list-disc space-y-1 pl-4">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Bagian 1: Pengajuan Menunggu Persetujuan --}}
        <section class="space-y-3.5" aria-labelledby="pending-approvals-heading">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 id="pending-approvals-heading" class="text-base font-bold text-brand-ink sm:text-lg">
                        Menunggu Persetujuan Anda
                    </h2>
                    <p class="text-xs text-muted">
                        Daftar pengajuan yang sedang berada pada giliran tahap persetujuan Anda.
                    </p>
                </div>

                <x-badge :variant="$pendingRequests->isNotEmpty() ? 'warning' : 'success'">
                    {{ $pendingRequests->count() }} Menunggu
                </x-badge>
            </div>

            @if ($pendingRequests->isEmpty())
                <x-empty-state
                    title="Tidak ada pengajuan yang menunggu persetujuan"
                    description="Semua pengajuan izin, cuti, dan WFH pada giliran Anda telah selesai ditindaklanjuti."
                />
            @else
                <div class="space-y-4">
                    @foreach ($pendingRequests as $item)
                        @php
                            $applicant = $item->employee;
                            $applicantUser = $applicant?->user;
                            $leaveType = $item->leaveType;
                            $isSingleDay = $item->start_date->isSameDay($item->end_date);
                            $currentApproval = $item->currentApproval();
                            $totalSteps = $item->approvals->count();
                            $currentRoleLabel = $currentApproval?->role
                                ? __('ui.roles.' . $currentApproval->role)
                                : null;
                        @endphp

                        <x-card class="space-y-4 transition-colors duration-150 hover:border-brand-navy/35">
                            {{-- Header Pemohon & Tipe Pengajuan --}}
                            <div class="flex flex-col gap-3 border-b border-border pb-3.5 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0 space-y-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="text-base font-bold text-brand-ink">
                                            {{ $applicantUser?->name ?? '-' }}
                                        </h3>
                                        @if ($applicant?->employee_number)
                                            <span class="rounded-lg bg-surface px-2 py-0.5 font-mono text-[11px] font-semibold text-muted">
                                                {{ $applicant->employee_number }}
                                            </span>
                                        @endif
                                    </div>

                                    <p class="text-xs text-muted">
                                        {{ $applicant?->department?->name ?? 'Tanpa Departemen' }}
                                        @if ($applicant?->division)
                                            · {{ $applicant->division->name }}
                                        @endif
                                        @if ($applicant?->position_title)
                                            · {{ $applicant->position_title }}
                                        @endif
                                    </p>
                                </div>

                                <div class="flex flex-wrap items-center gap-1.5 sm:justify-end">
                                    <x-badge variant="navy">
                                        {{ $leaveType?->name ?? 'Pengajuan' }}
                                    </x-badge>
                                    <x-badge :variant="$item->status->badgeVariant()">
                                        {{ $item->status->label() }}
                                    </x-badge>
                                </div>
                            </div>

                            {{-- Ringkasan Tanggal, Hari Kerja & Tahap --}}
                            <dl class="grid gap-3 rounded-2xl bg-surface p-3.5 text-xs sm:grid-cols-3">
                                <div>
                                    <dt class="text-muted">Tanggal</dt>
                                    <dd class="mt-1 font-bold text-brand-ink">
                                        @if ($isSingleDay)
                                            {{ $item->start_date->translatedFormat('l, d M Y') }}
                                        @else
                                            {{ $item->start_date->translatedFormat('d M Y') }} – {{ $item->end_date->translatedFormat('d M Y') }}
                                        @endif
                                    </dd>
                                </div>

                                <div>
                                    <dt class="text-muted">Total Hari Kerja</dt>
                                    <dd class="mt-1 font-bold tabular-nums text-text">
                                        {{ $item->total_work_days }} hari kerja
                                    </dd>
                                </div>

                                <div>
                                    <dt class="text-muted">Tahap Aktif &amp; Waktu Pengajuan</dt>
                                    <dd class="mt-1 font-semibold text-warning-ink">
                                        Tahap {{ $item->current_step }}/{{ max(1, $totalSteps) }}
                                        @if ($currentRoleLabel)
                                            · {{ $currentRoleLabel }}
                                        @endif
                                    </dd>
                                    <dd class="mt-0.5 text-[11px] tabular-nums text-muted">
                                        Diajukan {{ $item->created_at?->setTimezone($timezone)->translatedFormat('d M Y, H:i') }} WIB
                                    </dd>
                                </div>
                            </dl>

                            {{-- Alasan Pengajuan --}}
                            <div class="space-y-1.5">
                                <p class="text-xs font-semibold tracking-wide text-muted uppercase">Alasan Pengajuan</p>
                                <p class="rounded-2xl border border-border bg-surface px-3.5 py-2.5 text-sm leading-relaxed text-text whitespace-pre-line">{{ $item->reason }}</p>
                            </div>

                            {{-- Tautan Lampiran & Detail Lengkap --}}
                            <div class="flex flex-wrap items-center gap-2">
                                @if ($item->attachment_path)
                                    @can('viewAttachment', $item)
                                        <x-button
                                            href="{{ route('media.leave.attachment', $item) }}"
                                            target="_blank"
                                            rel="noopener"
                                            variant="secondary"
                                            size="sm"
                                        >
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                            </svg>
                                            <span>Lihat Lampiran</span>
                                        </x-button>
                                    @endcan
                                @endif

                                @can('view', $item)
                                    <x-button href="{{ route('leave.show', $item) }}" variant="ghost" size="sm">
                                        <span>Lihat Detail Pengajuan</span>
                                    </x-button>
                                @endcan
                            </div>

                            {{-- Linimasa Persetujuan (Collapsible) --}}
                            <div x-data="{ timelineOpen: false }" class="rounded-2xl border border-border bg-surface/60">
                                <button
                                    type="button"
                                    x-on:click="timelineOpen = !timelineOpen"
                                    :aria-expanded="timelineOpen"
                                    class="flex min-h-11 w-full cursor-pointer items-center justify-between gap-2 px-4 py-2.5 text-left text-xs font-semibold text-brand-ink transition-colors duration-150 hover:bg-surface"
                                >
                                    <span>Linimasa Persetujuan ({{ $totalSteps }} tahap)</span>
                                    <svg
                                        class="h-4 w-4 shrink-0 text-muted transition-transform duration-150"
                                        :class="timelineOpen ? 'rotate-180' : ''"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        aria-hidden="true"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>

                                <div x-show="timelineOpen" x-cloak class="border-t border-border p-3.5">
                                    <x-approval-timeline
                                        :approvals="$item->approvals"
                                        :current-step="$item->current_step"
                                    />
                                </div>
                            </div>

                            {{-- Form Keputusan: Catatan Opsional + Setujui / Tolak --}}
                            <form method="POST" action="{{ route('approvals.approve', $item) }}" class="space-y-3 border-t border-border pt-4">
                                @csrf

                                <x-input
                                    id="approval-note-{{ $item->id }}"
                                    name="note"
                                    label="Catatan Keputusan (Opsional)"
                                    placeholder="Tambahkan catatan persetujuan atau alasan penolakan (maks. 500 karakter)..."
                                    maxlength="500"
                                    x-on:keydown.enter.prevent
                                />

                                <div class="flex flex-col-reverse gap-2.5 sm:flex-row sm:justify-end">
                                    <x-button
                                        type="submit"
                                        formaction="{{ route('approvals.reject', $item) }}"
                                        variant="danger-outline"
                                        size="md"
                                        class="w-full sm:w-auto"
                                    >
                                        {{ __('ui.actions.reject') }}
                                    </x-button>

                                    <x-button
                                        type="submit"
                                        formaction="{{ route('approvals.approve', $item) }}"
                                        variant="checkin"
                                        size="md"
                                        class="w-full sm:w-auto"
                                    >
                                        {{ __('ui.actions.approve') }}
                                    </x-button>
                                </div>
                            </form>
                        </x-card>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- Bagian 2: Riwayat Tindakan Saya --}}
        <section class="space-y-3.5" aria-labelledby="approval-history-heading">
            <div>
                <h2 id="approval-history-heading" class="text-base font-bold text-brand-ink sm:text-lg">
                    Riwayat Tindakan Saya
                </h2>
                <p class="text-xs text-muted">
                    15 keputusan persetujuan atau penolakan terakhir yang pernah Anda proses.
                </p>
            </div>

            @if ($historyApprovals->isEmpty())
                <x-empty-state
                    title="Belum ada riwayat tindakan"
                    description="Keputusan persetujuan maupun penolakan yang Anda lakukan akan tampil di sini."
                />
            @else
                {{-- Kartu Mobile --}}
                <div class="space-y-3 md:hidden">
                    @foreach ($historyApprovals as $approval)
                        @php
                            $leaveRequest = $approval->leaveRequest;
                            $applicant = $leaveRequest?->employee;
                            $applicantUser = $applicant?->user;
                            $roleValue = (string) $approval->role;
                            $isSingleDay = $leaveRequest !== null && $leaveRequest->start_date->isSameDay($leaveRequest->end_date);
                        @endphp

                        <x-card class="space-y-3">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-bold text-brand-ink">
                                        {{ $applicantUser?->name ?? '-' }}
                                    </p>
                                    <p class="text-xs text-muted">
                                        {{ $leaveRequest?->leaveType?->name ?? 'Pengajuan' }}
                                        @if ($leaveRequest)
                                            · {{ $leaveRequest->total_work_days }} hari kerja
                                        @endif
                                    </p>
                                </div>

                                <x-badge :variant="$approval->status->badgeVariant()">
                                    {{ $approval->status->label() }}
                                </x-badge>
                            </div>

                            <dl class="grid grid-cols-2 gap-2.5 rounded-2xl bg-surface p-3 text-xs">
                                <div>
                                    <dt class="text-muted">Rentang Tanggal</dt>
                                    <dd class="mt-0.5 font-semibold text-text">
                                        @if ($leaveRequest === null)
                                            -
                                        @elseif ($isSingleDay)
                                            {{ $leaveRequest->start_date->translatedFormat('d M Y') }}
                                        @else
                                            {{ $leaveRequest->start_date->translatedFormat('d M Y') }} – {{ $leaveRequest->end_date->translatedFormat('d M Y') }}
                                        @endif
                                    </dd>
                                </div>

                                <div>
                                    <dt class="text-muted">Tahap &amp; Waktu Aksi</dt>
                                    <dd class="mt-0.5 font-semibold text-text">
                                        Tahap {{ $approval->step }} · {{ __('ui.roles.' . $roleValue) }}
                                    </dd>
                                    <dd class="mt-0.5 text-[11px] tabular-nums text-muted">
                                        {{ $approval->acted_at?->setTimezone($timezone)->translatedFormat('d M Y, H:i') ?? '-' }} WIB
                                    </dd>
                                </div>
                            </dl>

                            @if (is_string($approval->note) && trim($approval->note) !== '')
                                <p class="rounded-xl border border-border bg-surface px-3 py-2 text-xs text-text">
                                    <span class="font-semibold text-muted">Catatan:</span> {{ $approval->note }}
                                </p>
                            @endif

                            @if ($leaveRequest !== null)
                                <div class="flex items-center justify-between gap-2 border-t border-border pt-3">
                                    <x-badge :variant="$leaveRequest->status->badgeVariant()">
                                        Status Akhir: {{ $leaveRequest->status->label() }}
                                    </x-badge>

                                    @can('view', $leaveRequest)
                                        <x-button href="{{ route('leave.show', $leaveRequest) }}" variant="secondary" size="sm">
                                            Detail
                                        </x-button>
                                    @endcan
                                </div>
                            @endif
                        </x-card>
                    @endforeach
                </div>

                {{-- Tabel Desktop --}}
                <x-card padding="p-0" class="hidden overflow-hidden md:block">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-border text-left text-sm">
                            <thead class="bg-surface text-xs font-semibold tracking-wide text-muted uppercase">
                                <tr>
                                    <th scope="col" class="px-4 py-3.5">Pemohon</th>
                                    <th scope="col" class="px-4 py-3.5">Pengajuan &amp; Tanggal</th>
                                    <th scope="col" class="px-4 py-3.5">Keputusan Saya</th>
                                    <th scope="col" class="px-4 py-3.5">Catatan</th>
                                    <th scope="col" class="px-4 py-3.5">Status Pengajuan</th>
                                    <th scope="col" class="px-4 py-3.5 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border bg-card">
                                @foreach ($historyApprovals as $approval)
                                    @php
                                        $leaveRequest = $approval->leaveRequest;
                                        $applicant = $leaveRequest?->employee;
                                        $applicantUser = $applicant?->user;
                                        $roleValue = (string) $approval->role;
                                        $isSingleDay = $leaveRequest !== null && $leaveRequest->start_date->isSameDay($leaveRequest->end_date);
                                    @endphp

                                    <tr class="transition-colors duration-150 hover:bg-surface/60">
                                        <td class="px-4 py-3.5 align-top">
                                            <p class="font-bold text-brand-ink">{{ $applicantUser?->name ?? '-' }}</p>
                                            <p class="mt-0.5 text-xs text-muted">
                                                {{ $applicant?->employee_number ?? '-' }}
                                                @if ($applicant?->department)
                                                    · {{ $applicant->department->name }}
                                                @endif
                                            </p>
                                        </td>

                                        <td class="px-4 py-3.5 align-top">
                                            <p class="font-semibold text-text">{{ $leaveRequest?->leaveType?->name ?? '-' }}</p>
                                            <p class="mt-0.5 text-xs text-muted">
                                                @if ($leaveRequest === null)
                                                    -
                                                @elseif ($isSingleDay)
                                                    {{ $leaveRequest->start_date->translatedFormat('d M Y') }}
                                                @else
                                                    {{ $leaveRequest->start_date->translatedFormat('d M Y') }} – {{ $leaveRequest->end_date->translatedFormat('d M Y') }}
                                                @endif
                                                @if ($leaveRequest)
                                                    ({{ $leaveRequest->total_work_days }} hari kerja)
                                                @endif
                                            </p>
                                        </td>

                                        <td class="px-4 py-3.5 align-top">
                                            <div class="space-y-1">
                                                <x-badge :variant="$approval->status->badgeVariant()">
                                                    {{ $approval->status->label() }}
                                                </x-badge>
                                                <p class="text-xs text-muted">
                                                    Tahap {{ $approval->step }} · {{ __('ui.roles.' . $roleValue) }}
                                                </p>
                                                <p class="text-[11px] tabular-nums text-muted">
                                                    {{ $approval->acted_at?->setTimezone($timezone)->translatedFormat('d M Y, H:i') ?? '-' }} WIB
                                                </p>
                                            </div>
                                        </td>

                                        <td class="max-w-xs px-4 py-3.5 align-top text-xs text-text">
                                            {{ is_string($approval->note) && trim($approval->note) !== '' ? $approval->note : '-' }}
                                        </td>

                                        <td class="px-4 py-3.5 align-top">
                                            @if ($leaveRequest)
                                                <x-badge :variant="$leaveRequest->status->badgeVariant()">
                                                    {{ $leaveRequest->status->label() }}
                                                </x-badge>
                                            @else
                                                <span class="text-xs text-muted">-</span>
                                            @endif
                                        </td>

                                        <td class="px-4 py-3.5 text-right align-top">
                                            @if ($leaveRequest !== null && auth()->user()?->can('view', $leaveRequest))
                                                <x-button href="{{ route('leave.show', $leaveRequest) }}" variant="secondary" size="sm">
                                                    Detail
                                                </x-button>
                                            @else
                                                <span class="text-xs text-muted">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-card>
            @endif
        </section>
    </div>
</x-app-layout>
