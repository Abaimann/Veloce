@php
    $departmentOptions = $departments->mapWithKeys(
        static fn ($department): array => [$department->id => "{$department->name} ({$department->code})"]
    )->all();

    $formatRupiah = static fn (int $amount): string => 'Rp ' . number_format($amount, 0, ',', '.');
@endphp

<x-app-layout title="Perhitungan Gaji Sistem" :subtitle="'Rekap gaji otomatis periode ' . $period->label">
    <div class="space-y-4">
        {{-- Ringkasan Total Periode --}}
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <x-card padding="p-3.5 sm:p-5">
                <p class="text-[11px] font-medium text-muted sm:text-xs">Status ({{ $period->label }})</p>
                <div class="mt-2 flex flex-wrap items-center justify-between gap-1.5">
                    <p class="text-xl font-bold text-brand-ink sm:text-2xl">{{ $summary['employee_count'] }}</p>
                    <x-badge :variant="$period->status->badgeVariant()">{{ $period->status->label() }}</x-badge>
                </div>
                <p class="mt-1 text-[11px] text-muted sm:text-xs">Karyawan aktif</p>
            </x-card>

            <x-card padding="p-3.5 sm:p-5">
                <p class="text-[11px] font-medium text-muted sm:text-xs">Gaji Kotor</p>
                <p class="mt-2 truncate text-base font-bold text-brand-ink tabular-nums sm:text-2xl">{{ $formatRupiah($summary['total_gross']) }}</p>
                <p class="mt-1 text-[11px] text-muted sm:text-xs">Pokok + Tunjangan</p>
            </x-card>

            <x-card padding="p-3.5 sm:p-5">
                <p class="text-[11px] font-medium text-muted sm:text-xs">Total Potongan</p>
                <p class="mt-2 truncate text-base font-bold text-danger-ink tabular-nums sm:text-2xl">{{ $formatRupiah($summary['total_deduction']) }}</p>
                <p class="mt-1 text-[11px] text-muted sm:text-xs">Telat &amp; Alpha</p>
            </x-card>

            <x-card padding="p-3.5 sm:p-5" class="border-brand-green/35 bg-brand-green-soft/35">
                <p class="text-[11px] font-medium text-success-ink sm:text-xs">Take Home Pay</p>
                <p class="mt-2 truncate text-base font-bold text-success-ink tabular-nums sm:text-2xl">{{ $formatRupiah($summary['total_net']) }}</p>
                <p class="mt-1 text-[11px] text-success-ink/80 sm:text-xs">{{ $period->generated_at?->translatedFormat('d M H:i') }} WIB</p>
            </x-card>
        </div>

        {{-- Filter & Aksi Hitung Ulang --}}
        <x-card padding="p-4 sm:p-5">
            <div class="flex flex-col gap-3.5 lg:flex-row lg:items-end lg:justify-between">
                <form method="GET" action="{{ route('payroll.index') }}" class="grid flex-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <x-input
                        id="payroll-month"
                        name="month"
                        type="month"
                        label="Periode Bulan"
                        :value="$selectedMonth"
                    />

                    <x-input
                        id="payroll-search"
                        name="q"
                        type="search"
                        label="Cari Karyawan"
                        :value="$search"
                        placeholder="Nama atau NIK..."
                    />

                    <x-select
                        id="payroll-department"
                        name="department_id"
                        label="Departemen"
                        :options="$departmentOptions"
                        :value="$selectedDepartmentId"
                        placeholder="Semua Departemen"
                    />

                    <div class="flex items-end gap-2">
                        <x-button type="submit" variant="primary" size="md" class="w-full">
                            Tampilkan
                        </x-button>
                    </div>
                </form>

                <div class="grid grid-cols-1 gap-2 sm:flex sm:flex-wrap sm:items-center">
                    <form method="POST" action="{{ route('payroll.recalculate') }}">
                        @csrf
                        <input type="hidden" name="month" value="{{ $selectedMonth }}">
                        <x-button type="submit" variant="secondary" size="md" fullWidth>
                            Hitung Ulang Gaji
                        </x-button>
                    </form>

                    @if ($period->status === \App\Enums\PayrollPeriodStatus::DRAFT)
                        <form method="POST" action="{{ route('payroll.finalize', $period) }}">
                            @csrf
                            <x-button type="submit" variant="checkin" size="md" fullWidth>
                                Tandai Final
                            </x-button>
                        </form>
                    @endif
                </div>
            </div>
        </x-card>

        {{-- Kartu Mobile Rincian Gaji --}}
        <div class="space-y-3 md:hidden">
            @forelse ($items as $item)
                @php
                    $emp = $item->employee;
                @endphp
                <x-card padding="p-4" class="space-y-3">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-bold text-brand-ink">{{ $emp?->user?->name ?? '-' }}</p>
                            <p class="text-xs text-muted">{{ $emp?->employee_number }} · {{ $emp?->department?->code }}</p>
                        </div>
                        <p class="shrink-0 text-sm font-extrabold text-success-ink tabular-nums">
                            {{ $formatRupiah($item->net_salary) }}
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-1.5">
                        <x-badge variant="success">Hadir: {{ $item->present_days }} hr</x-badge>
                        @if ($item->late_days > 0)
                            <x-badge variant="warning">Telat: {{ $item->late_days }}x</x-badge>
                        @endif
                        @if ($item->paid_leave_days > 0)
                            <x-badge variant="violet">Izin/Cuti: {{ $item->paid_leave_days }} hr</x-badge>
                        @endif
                        @if ($item->absent_days + $item->unpaid_leave_days > 0)
                            <x-badge variant="danger">Alpha: {{ $item->absent_days + $item->unpaid_leave_days }} hr</x-badge>
                        @endif
                    </div>

                    <div class="grid grid-cols-2 gap-2 rounded-2xl bg-surface p-3 text-xs tabular-nums">
                        <div>
                            <p class="text-muted">Pokok + Tunjangan</p>
                            <p class="mt-0.5 font-bold text-brand-ink">{{ $formatRupiah($item->base_salary + $item->fixed_allowance) }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-muted">Potongan</p>
                            <p class="mt-0.5 font-bold text-danger-ink">-{{ $formatRupiah($item->late_deduction + $item->absence_deduction) }}</p>
                        </div>
                    </div>
                </x-card>
            @empty
                <x-empty-state title="Tidak ada data gaji yang cocok" />
            @endforelse
        </div>

        {{-- Tabel Desktop Rincian Gaji Per Karyawan --}}
        <x-card padding="p-0" class="hidden overflow-hidden md:block">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-border text-left text-sm">
                    <thead class="bg-surface text-xs font-semibold tracking-wide text-muted uppercase">
                        <tr>
                            <th scope="col" class="px-5 py-3.5">Karyawan &amp; Unit</th>
                            <th scope="col" class="px-5 py-3.5">Rekap Kehadiran</th>
                            <th scope="col" class="px-5 py-3.5 text-right">Gaji Pokok &amp; Tunjangan</th>
                            <th scope="col" class="px-5 py-3.5 text-right">Potongan</th>
                            <th scope="col" class="px-5 py-3.5 text-right">Gaji Bersih</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border bg-card">
                        @forelse ($items as $item)
                            @php
                                $emp = $item->employee;
                                $breakdown = $item->calculation_breakdown ?? [];
                            @endphp
                            <tr class="transition-colors duration-150 hover:bg-surface/60">
                                <td class="px-5 py-4 align-top">
                                    <p class="font-bold text-brand-ink">{{ $emp?->user?->name ?? '-' }}</p>
                                    <p class="text-xs text-muted">{{ $emp?->employee_number }} · {{ $emp?->department?->name }}</p>
                                    <p class="text-xs text-muted">{{ $emp?->position_title ?? $emp?->employment_type?->label() }}</p>
                                </td>
                                <td class="px-5 py-4 align-top text-xs">
                                    <div class="flex flex-wrap gap-1.5">
                                        <x-badge variant="success">Hadir: {{ $item->present_days }} hr</x-badge>
                                        @if ($item->late_days > 0)
                                            <x-badge variant="warning">Telat: {{ $item->late_days }}x</x-badge>
                                        @endif
                                        @if ($item->paid_leave_days > 0)
                                            <x-badge variant="violet">Cuti/Sakit/Izin: {{ $item->paid_leave_days }} hr</x-badge>
                                        @endif
                                        @if ($item->absent_days + $item->unpaid_leave_days > 0)
                                            <x-badge variant="danger">Alpha/Unpaid: {{ $item->absent_days + $item->unpaid_leave_days }} hr</x-badge>
                                        @endif
                                    </div>
                                    <p class="mt-1.5 text-muted">
                                        Hari kerja bulan ini: {{ $item->workdays_in_month }} hari (dievaluasi: {{ $breakdown['evaluated_workdays_count'] ?? $item->workdays_in_month }} hari)
                                    </p>
                                </td>
                                <td class="px-5 py-4 text-right align-top tabular-nums">
                                    <p class="font-semibold text-text">{{ $formatRupiah($item->base_salary) }}</p>
                                    <p class="text-xs text-muted">+ Tunjangan {{ $formatRupiah($item->fixed_allowance) }}</p>
                                </td>
                                <td class="px-5 py-4 text-right align-top tabular-nums">
                                    <p class="font-semibold text-danger-ink">
                                        -{{ $formatRupiah($item->late_deduction + $item->absence_deduction) }}
                                    </p>
                                    <p class="text-xs text-muted">
                                        Telat {{ $formatRupiah($item->late_deduction) }} · Alpha {{ $formatRupiah($item->absence_deduction) }}
                                    </p>
                                </td>
                                <td class="px-5 py-4 text-right align-top tabular-nums">
                                    <p class="text-base font-bold text-success-ink">{{ $formatRupiah($item->net_salary) }}</p>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-8 text-center text-sm text-muted">
                                    Tidak ada data gaji yang cocok dengan filter Anda.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        @if ($items->hasPages())
            <div class="pt-1">
                {{ $items->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
