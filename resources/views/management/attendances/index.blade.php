@php
    $statusOptions = collect($statuses)->mapWithKeys(
        static fn (\App\Enums\AttendanceStatus $status): array => [$status->value => $status->label()]
    )->all();

    $departmentOptions = $departments->mapWithKeys(
        static fn ($department): array => [$department->id => "{$department->name} ({$department->code})"]
    )->all();

    $subtitle = $selectedDate === 'all'
        ? 'Pantau kehadiran seluruh tanggal sesuai cakupan akses Anda'
        : 'Pantau kehadiran tim tanggal ' . \Carbon\CarbonImmutable::parse($selectedDate, $timezone)->translatedFormat('l, d F Y');

    $formatFlagDetail = static function (mixed $flag, mixed $key = null): array {
        if (is_array($flag)) {
            $code = (string) ($flag['code'] ?? $flag['type'] ?? (is_string($key) ? $key : ''));
            $phase = isset($flag['phase']) && is_string($flag['phase']) ? $flag['phase'] : null;
            $message = isset($flag['message']) && is_string($flag['message']) && $flag['message'] !== ''
                ? $flag['message']
                : null;
            $meta = isset($flag['meta']) && is_array($flag['meta']) ? $flag['meta'] : [];
        } else {
            $code = is_string($flag) ? $flag : (is_string($key) ? $key : '');
            $phase = null;
            $message = null;
            $meta = [];
        }

        $fallbackMessage = match ($code) {
            'low_gps_accuracy' => __('attendance.flags.low_gps_accuracy', [
                'meters' => (int) config('attendance.max_acceptable_accuracy_meters', 100),
            ]),
            'client_clock_skew' => __('attendance.flags.client_clock_skew', [
                'minutes' => max(1, (int) round(((int) config('attendance.max_client_clock_skew_seconds', 300)) / 60)),
            ]),
            'duplicate_selfie_hash' => __('attendance.flags.duplicate_selfie_hash'),
            default => $code !== '' ? $code : 'Anomali tercatat',
        };

        $phaseLabel = match ($phase) {
            'in' => 'Masuk',
            'out' => 'Pulang',
            default => null,
        };

        $extra = null;
        if ($code === 'low_gps_accuracy' && isset($meta['accuracy'])) {
            $extra = 'akurasi ' . number_format((float) $meta['accuracy'], 0) . ' m';
        } elseif ($code === 'client_clock_skew' && isset($meta['diff_seconds'])) {
            $minutes = max(1, (int) round(((int) $meta['diff_seconds']) / 60));
            $extra = 'selisih ~' . $minutes . ' menit';
        }

        return [
            'code' => $code,
            'phaseLabel' => $phaseLabel,
            'text' => $message ?? $fallbackMessage,
            'extra' => $extra,
        ];
    };
@endphp

<x-app-layout title="Monitoring Absensi Tim" :subtitle="$subtitle">
    <div class="space-y-4">
        {{-- Filter Bar --}}
        <x-card>
            <form method="GET" action="{{ route('management.attendances.index') }}" class="space-y-4">
                <div class="grid gap-3.5 sm:grid-cols-2 {{ $canFilterDepartment ? 'lg:grid-cols-4' : 'lg:grid-cols-3' }}">
                    {{-- Filter Tanggal --}}
                    <div x-data="{ showAllDates: @js($selectedDate === 'all') }" class="space-y-1.5">
                        <div class="flex items-center justify-between gap-2">
                            <label for="filter-date" class="block text-sm font-semibold text-text">
                                Tanggal
                            </label>
                            <label class="inline-flex cursor-pointer items-center gap-1.5 text-xs font-medium text-muted">
                                <input
                                    type="checkbox"
                                    x-model="showAllDates"
                                    class="h-4 w-4 rounded border-border text-brand-navy"
                                >
                                <span>Semua tanggal</span>
                            </label>
                        </div>

                        <input
                            id="filter-date"
                            type="date"
                            name="date"
                            value="{{ $selectedDate !== 'all' ? $selectedDate : '' }}"
                            x-bind:disabled="showAllDates"
                            x-on:input="if ($event.target.value) showAllDates = false"
                            class="block w-full min-h-12 rounded-2xl border border-border bg-card px-4 py-3 text-sm text-text shadow-xs transition-[border-color,box-shadow] duration-150 hover:border-muted/50 focus:border-brand-navy focus:outline-none focus:ring-4 focus:ring-brand-navy/10 disabled:bg-surface disabled:text-muted"
                        >

                        <template x-if="showAllDates">
                            <input type="hidden" name="date" value="all">
                        </template>
                    </div>

                    {{-- Filter Status --}}
                    <x-select
                        id="filter-status"
                        name="status"
                        label="Status Kehadiran"
                        :options="$statusOptions"
                        :value="$selectedStatus?->value"
                        placeholder="Semua Status"
                    />

                    {{-- Filter Departemen (Hanya HR Manager & Super Admin) --}}
                    @if ($canFilterDepartment)
                        <x-select
                            id="filter-department"
                            name="department_id"
                            label="Departemen"
                            :options="$departmentOptions"
                            :value="$selectedDepartmentId"
                            placeholder="Semua Departemen"
                        />
                    @endif

                    {{-- Checkbox Tanda Mencurigakan --}}
                    <div class="flex flex-col justify-end">
                        <label
                            for="filter-flagged-only"
                            class="flex min-h-12 cursor-pointer items-center gap-2.5 rounded-2xl border px-3.5 py-2.5 text-xs font-semibold shadow-xs transition-colors duration-150 {{ $flaggedOnly ? 'border-warning/40 bg-warning-soft text-warning-ink' : 'border-border bg-surface text-text hover:border-brand-navy/40' }}"
                        >
                            <input
                                id="filter-flagged-only"
                                type="checkbox"
                                name="flagged_only"
                                value="1"
                                @checked($flaggedOnly)
                                class="h-4 w-4 rounded border-border text-brand-navy"
                            >
                            <span>Hanya Tanda Mencurigakan</span>
                        </label>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-border pt-3">
                    <p class="text-xs text-muted">
                        Menampilkan <strong class="font-semibold text-brand-ink">{{ $attendances->total() }}</strong> catatan absensi sesuai filter.
                    </p>

                    <div class="flex flex-wrap items-center gap-2">
                        <x-button href="{{ route('management.attendances.index') }}" variant="secondary" size="sm">
                            Reset (Hari Ini)
                        </x-button>

                        @if ($selectedDate !== 'all')
                            <x-button
                                href="{{ route('management.attendances.index', array_filter(['date' => 'all', 'status' => $selectedStatus?->value, 'department_id' => $selectedDepartmentId, 'flagged_only' => $flaggedOnly ? '1' : null])) }}"
                                variant="ghost"
                                size="sm"
                            >
                                Lihat Semua Tanggal
                            </x-button>
                        @endif

                        <x-button type="submit" variant="primary" size="sm">
                            Terapkan Filter
                        </x-button>
                    </div>
                </div>
            </form>
        </x-card>

        @if ($attendances->isEmpty())
            <x-empty-state
                title="Belum ada data absensi tim"
                description="Tidak ditemukan catatan kehadiran untuk filter tanggal, status, atau kriteria yang dipilih."
            >
                <x-slot:action>
                    <x-button href="{{ route('management.attendances.index', ['date' => 'all']) }}" variant="secondary" size="sm">
                        Tampilkan Semua Tanggal
                    </x-button>
                </x-slot:action>
            </x-empty-state>
        @else
            {{-- Kartu Mobile (md:hidden) --}}
            <div class="space-y-3 md:hidden">
                @foreach ($attendances as $item)
                    @php
                        $employee = $item->employee;
                        $employeeUser = $employee?->user;
                        $isFlagged = $item->hasSuspiciousFlags();
                        $rawFlags = $item->flags ?? [];
                    @endphp

                    <div class="space-y-3.5 rounded-2xl border bg-card p-4 shadow-xs sm:p-5 {{ $isFlagged ? 'border-warning/50' : 'border-border' }}">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-bold text-brand-ink">
                                    {{ $employeeUser?->name ?? '-' }}
                                </p>
                                <p class="text-xs text-muted">
                                    {{ $employee?->employee_number ?? '-' }}
                                    @if ($employee?->department)
                                        · {{ $employee->department->name }}
                                    @endif
                                    @if ($employee?->division)
                                        · {{ $employee->division->name }}
                                    @endif
                                </p>
                                <p class="mt-0.5 text-xs font-medium text-text">
                                    {{ $item->work_date->translatedFormat('l, d M Y') }}
                                </p>
                            </div>

                            <div class="flex shrink-0 flex-col items-end gap-1.5">
                                <x-badge :variant="$item->status->badgeVariant()">
                                    {{ $item->status->label() }}
                                </x-badge>

                                @if ($isFlagged)
                                    <x-badge variant="warning">
                                        Mencurigakan ({{ count($rawFlags) }})
                                    </x-badge>
                                @endif
                            </div>
                        </div>

                        <dl class="grid grid-cols-2 gap-3 rounded-2xl bg-surface p-3 text-xs">
                            <div>
                                <dt class="text-muted">Jam Masuk (WIB)</dt>
                                <dd class="mt-0.5 font-bold tabular-nums text-text">
                                    {{ $item->check_in_at ? $item->check_in_at->setTimezone($timezone)->format('H:i') . ' WIB' : '-' }}
                                </dd>
                                @if ($item->check_in_distance_meters !== null)
                                    <dd class="mt-0.5 text-[11px] text-muted">
                                        Jarak {{ number_format((float) $item->check_in_distance_meters, 0) }} m
                                    </dd>
                                @endif
                            </div>

                            <div>
                                <dt class="text-muted">Jam Pulang (WIB)</dt>
                                <dd class="mt-0.5 font-bold tabular-nums text-text">
                                    {{ $item->check_out_at ? $item->check_out_at->setTimezone($timezone)->format('H:i') . ' WIB' : '-' }}
                                </dd>
                                @if ($item->officeLocation)
                                    <dd class="mt-0.5 truncate text-[11px] text-muted">
                                        {{ $item->officeLocation->name }}
                                    </dd>
                                @endif
                            </div>
                        </dl>

                        @if ($isFlagged)
                            <div class="space-y-1.5 rounded-2xl border border-warning/30 bg-warning-soft/50 p-3">
                                <p class="text-xs font-bold text-warning-ink">Rincian Tanda Anomali</p>
                                <ul class="space-y-1 text-xs text-warning-ink">
                                    @foreach ($rawFlags as $flagKey => $flagItem)
                                        @php
                                            $detail = $formatFlagDetail($flagItem, $flagKey);
                                        @endphp
                                        <li class="flex items-start gap-1.5">
                                            <span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-warning"></span>
                                            <span>
                                                @if ($detail['phaseLabel'])
                                                    <strong>[{{ $detail['phaseLabel'] }}]</strong>
                                                @endif
                                                {{ $detail['text'] }}
                                                @if ($detail['extra'])
                                                    <span class="font-medium">({{ $detail['extra'] }})</span>
                                                @endif
                                                @if ($detail['code'] !== '')
                                                    <span class="font-mono text-[11px] text-muted">· {{ $detail['code'] }}</span>
                                                @endif
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @can('viewSelfie', $item)
                            @if ($item->selfie_pruned_at !== null)
                                <p class="rounded-xl bg-surface px-3 py-2 text-[11px] text-muted">
                                    Foto selfie telah dibersihkan sesuai retensi ({{ $item->selfie_pruned_at->setTimezone($timezone)->translatedFormat('d M Y') }}).
                                </p>
                            @elseif ($item->selfie_in_path || $item->selfie_out_path)
                                <div class="flex flex-wrap items-center gap-2.5 border-t border-border pt-3">
                                    @if ($item->selfie_in_path)
                                        <a
                                            href="{{ route('media.attendance.selfie', [$item, 'in']) }}"
                                            target="_blank"
                                            rel="noopener"
                                            class="group flex items-center gap-2 rounded-xl border border-border bg-surface p-1.5 pr-3 transition-colors duration-150 hover:border-brand-navy"
                                        >
                                            <img
                                                src="{{ route('media.attendance.selfie', [$item, 'in']) }}"
                                                alt="Selfie masuk {{ $employeeUser?->name ?? 'karyawan' }}"
                                                loading="lazy"
                                                class="h-11 w-11 rounded-lg object-cover"
                                            >
                                            <div>
                                                <p class="text-xs font-bold text-brand-ink group-hover:text-brand-navy">Selfie Masuk</p>
                                                <p class="text-[11px] tabular-nums text-muted">
                                                    {{ $item->check_in_at?->setTimezone($timezone)->format('H:i') ?? '-' }} WIB
                                                </p>
                                            </div>
                                        </a>
                                    @endif

                                    @if ($item->selfie_out_path)
                                        <a
                                            href="{{ route('media.attendance.selfie', [$item, 'out']) }}"
                                            target="_blank"
                                            rel="noopener"
                                            class="group flex items-center gap-2 rounded-xl border border-border bg-surface p-1.5 pr-3 transition-colors duration-150 hover:border-brand-navy"
                                        >
                                            <img
                                                src="{{ route('media.attendance.selfie', [$item, 'out']) }}"
                                                alt="Selfie pulang {{ $employeeUser?->name ?? 'karyawan' }}"
                                                loading="lazy"
                                                class="h-11 w-11 rounded-lg object-cover"
                                            >
                                            <div>
                                                <p class="text-xs font-bold text-brand-ink group-hover:text-brand-navy">Selfie Pulang</p>
                                                <p class="text-[11px] tabular-nums text-muted">
                                                    {{ $item->check_out_at?->setTimezone($timezone)->format('H:i') ?? '-' }} WIB
                                                </p>
                                            </div>
                                        </a>
                                    @endif
                                </div>
                            @endif
                        @endcan
                    </div>
                @endforeach
            </div>

            {{-- Tabel Desktop (hidden md:block) --}}
            <x-card padding="p-0" class="hidden overflow-hidden md:block">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-border text-left text-sm">
                        <thead class="bg-surface text-xs font-semibold tracking-wide text-muted uppercase">
                            <tr>
                                <th scope="col" class="px-4 py-3.5">Karyawan</th>
                                <th scope="col" class="px-4 py-3.5">Tanggal</th>
                                <th scope="col" class="px-4 py-3.5">Status &amp; Lokasi</th>
                                <th scope="col" class="px-4 py-3.5">Masuk / Pulang (WIB)</th>
                                <th scope="col" class="px-4 py-3.5">Tanda Mencurigakan</th>
                                <th scope="col" class="px-4 py-3.5 text-right">Selfie</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border bg-card">
                            @foreach ($attendances as $item)
                                @php
                                    $employee = $item->employee;
                                    $employeeUser = $employee?->user;
                                    $isFlagged = $item->hasSuspiciousFlags();
                                    $rawFlags = $item->flags ?? [];
                                @endphp

                                <tr class="transition-colors duration-150 hover:bg-surface/60">
                                    <td class="px-4 py-4 align-top">
                                        <p class="font-bold text-brand-ink">{{ $employeeUser?->name ?? '-' }}</p>
                                        <p class="mt-0.5 text-xs font-medium text-muted">{{ $employee?->employee_number ?? '-' }}</p>
                                        <p class="text-xs text-muted">
                                            {{ $employee?->department?->name ?? '-' }}
                                            @if ($employee?->division)
                                                · {{ $employee->division->name }}
                                            @endif
                                        </p>
                                    </td>

                                    <td class="px-4 py-4 align-top">
                                        <p class="font-semibold text-text">{{ $item->work_date->translatedFormat('d M Y') }}</p>
                                        <p class="mt-0.5 text-xs text-muted">{{ $item->work_date->translatedFormat('l') }}</p>
                                    </td>

                                    <td class="px-4 py-4 align-top">
                                        <div class="space-y-1.5">
                                            <x-badge :variant="$item->status->badgeVariant()">
                                                {{ $item->status->label() }}
                                            </x-badge>
                                            @if ($item->officeLocation)
                                                <p class="text-xs text-muted">
                                                    {{ $item->officeLocation->name }}
                                                    @if ($item->check_in_distance_meters !== null)
                                                        ({{ number_format((float) $item->check_in_distance_meters, 0) }} m)
                                                    @endif
                                                </p>
                                            @endif
                                        </div>
                                    </td>

                                    <td class="px-4 py-4 align-top">
                                        <div class="space-y-1 text-xs">
                                            <p class="tabular-nums">
                                                <span class="text-muted">Masuk:</span>
                                                <strong class="font-bold text-text">
                                                    {{ $item->check_in_at ? $item->check_in_at->setTimezone($timezone)->format('H:i') . ' WIB' : '-' }}
                                                </strong>
                                            </p>
                                            <p class="tabular-nums">
                                                <span class="text-muted">Pulang:</span>
                                                <strong class="font-bold text-text">
                                                    {{ $item->check_out_at ? $item->check_out_at->setTimezone($timezone)->format('H:i') . ' WIB' : '-' }}
                                                </strong>
                                            </p>
                                        </div>
                                    </td>

                                    <td class="px-4 py-4 align-top">
                                        @if ($isFlagged)
                                            <div class="space-y-1.5">
                                                <x-badge variant="warning">
                                                    Peringatan ({{ count($rawFlags) }})
                                                </x-badge>

                                                <ul class="space-y-1 text-xs text-warning-ink">
                                                    @foreach ($rawFlags as $flagKey => $flagItem)
                                                        @php
                                                            $detail = $formatFlagDetail($flagItem, $flagKey);
                                                        @endphp
                                                        <li>
                                                            @if ($detail['phaseLabel'])
                                                                <strong>[{{ $detail['phaseLabel'] }}]</strong>
                                                            @endif
                                                            {{ $detail['text'] }}
                                                            @if ($detail['extra'])
                                                                ({{ $detail['extra'] }})
                                                            @endif
                                                            @if ($detail['code'] !== '')
                                                                <span class="font-mono text-[11px] text-muted">({{ $detail['code'] }})</span>
                                                            @endif
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @else
                                            <span class="text-xs text-muted">Normal</span>
                                        @endif
                                    </td>

                                    <td class="px-4 py-4 text-right align-top">
                                        @can('viewSelfie', $item)
                                            @if ($item->selfie_pruned_at !== null)
                                                <span class="text-xs text-muted">Dibersihkan</span>
                                            @elseif ($item->selfie_in_path || $item->selfie_out_path)
                                                <div class="inline-flex items-center justify-end gap-2">
                                                    @if ($item->selfie_in_path)
                                                        <a
                                                            href="{{ route('media.attendance.selfie', [$item, 'in']) }}"
                                                            target="_blank"
                                                            rel="noopener"
                                                            title="Lihat selfie masuk"
                                                            class="inline-flex flex-col items-center gap-1 rounded-xl border border-border bg-surface p-1 transition-colors duration-150 hover:border-brand-navy"
                                                        >
                                                            <img
                                                                src="{{ route('media.attendance.selfie', [$item, 'in']) }}"
                                                                alt="Selfie masuk {{ $employeeUser?->name ?? 'karyawan' }}"
                                                                loading="lazy"
                                                                class="h-11 w-11 rounded-lg object-cover"
                                                            >
                                                            <span class="text-[10px] font-semibold text-brand-ink">Masuk</span>
                                                        </a>
                                                    @endif

                                                    @if ($item->selfie_out_path)
                                                        <a
                                                            href="{{ route('media.attendance.selfie', [$item, 'out']) }}"
                                                            target="_blank"
                                                            rel="noopener"
                                                            title="Lihat selfie pulang"
                                                            class="inline-flex flex-col items-center gap-1 rounded-xl border border-border bg-surface p-1 transition-colors duration-150 hover:border-brand-navy"
                                                        >
                                                            <img
                                                                src="{{ route('media.attendance.selfie', [$item, 'out']) }}"
                                                                alt="Selfie pulang {{ $employeeUser?->name ?? 'karyawan' }}"
                                                                loading="lazy"
                                                                class="h-11 w-11 rounded-lg object-cover"
                                                            >
                                                            <span class="text-[10px] font-semibold text-brand-ink">Pulang</span>
                                                        </a>
                                                    @endif
                                                </div>
                                            @else
                                                <span class="text-xs text-muted">-</span>
                                            @endif
                                        @else
                                            <span class="text-xs text-muted">Akses dibatasi</span>
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>

            @if ($attendances->hasPages())
                <div class="pt-1">
                    {{ $attendances->links() }}
                </div>
            @endif
        @endif
    </div>
</x-app-layout>
