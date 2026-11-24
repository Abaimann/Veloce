@php
    $departmentOptions = $departments->mapWithKeys(
        static fn ($department): array => [$department->id => "{$department->name} ({$department->code})"]
    )->all();

    $divisionsPayload = $divisions->map(
        static fn ($division): array => [
            'id' => $division->id,
            'department_id' => $division->department_id,
            'name' => $division->name,
            'code' => $division->code,
        ]
    )->values()->all();

    $workScheduleOptions = $workSchedules->mapWithKeys(
        static fn ($schedule): array => [
            $schedule->id => sprintf(
                '%s (%s - %s)',
                $schedule->name,
                substr((string) $schedule->check_in_time, 0, 5),
                substr((string) $schedule->check_out_time, 0, 5),
            ),
        ]
    )->all();

    $employmentTypeOptions = collect($employmentTypes)->mapWithKeys(
        static fn ($type): array => [$type->value => $type->label()]
    )->all();

    $supervisorOptions = $supervisors->mapWithKeys(
        static fn ($supervisor): array => [
            $supervisor->id => sprintf(
                '%s (%s)',
                $supervisor->user?->name ?? '-',
                $supervisor->employee_number,
            ),
        ]
    )->all();
@endphp

<x-app-layout title="Ubah Data Karyawan" :subtitle="$employee->user?->name">
    <div class="space-y-4">
        <div class="flex items-center justify-between gap-3">
            <x-button href="{{ route('management.employees.index') }}" variant="ghost" size="sm">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                </svg>
                <span>Kembali ke Daftar Karyawan</span>
            </x-button>
        </div>

        <form
            method="POST"
            action="{{ route('management.employees.update', $employee) }}"
            x-data="{
                departmentId: @js((string) old('department_id', $employee->department_id)),
                divisionId: @js((string) old('division_id', $employee->division_id ?? '')),
                divisions: @js($divisionsPayload),
                get filteredDivisions() {
                    if (!this.departmentId) return [];
                    return this.divisions.filter(d => String(d.department_id) === String(this.departmentId));
                },
                syncDivision() {
                    const exists = this.filteredDivisions.some(d => String(d.id) === String(this.divisionId));
                    if (!exists) {
                        this.divisionId = '';
                    }
                }
            }"
            class="space-y-4"
        >
            @csrf
            @method('PUT')

            <x-card class="space-y-4">
                <div>
                    <h2 class="text-base font-bold text-brand-ink">Identitas &amp; Penempatan</h2>
                    <p class="mt-0.5 text-xs text-muted">Email: {{ $employee->user?->email }}</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-input
                        name="name"
                        label="Nama Lengkap"
                        :value="$employee->user?->name"
                        required
                    />

                    <x-input
                        name="employee_number"
                        label="Nomor Induk Karyawan (NIK)"
                        :value="$employee->employee_number"
                        required
                    />

                    <x-input
                        name="position_title"
                        label="Jabatan"
                        :value="$employee->position_title"
                    />

                    <x-select
                        name="department_id"
                        label="Departemen"
                        :options="$departmentOptions"
                        :value="$employee->department_id"
                        x-model="departmentId"
                        x-on:change="syncDivision()"
                        required
                    />

                    <x-select
                        name="division_id"
                        label="Divisi (Opsional)"
                        placeholder="-- Tidak ada / Pilih Divisi --"
                        x-model="divisionId"
                    >
                        <template x-for="division in filteredDivisions" :key="division.id">
                            <option
                                :value="String(division.id)"
                                x-text="division.name + ' (' + division.code + ')'"
                                :selected="String(division.id) === String(divisionId)"
                            ></option>
                        </template>
                    </x-select>

                    <x-select
                        name="employment_type"
                        label="Status Kepegawaian"
                        :options="$employmentTypeOptions"
                        :value="$employee->employment_type->value"
                        required
                    />

                    <x-select
                        name="work_schedule_id"
                        label="Jadwal Kerja"
                        :options="$workScheduleOptions"
                        :value="$employee->work_schedule_id"
                        required
                    />

                    <x-select
                        name="supervisor_id"
                        label="Atasan Langsung (Opsional)"
                        :options="$supervisorOptions"
                        :value="$employee->supervisor_id"
                        placeholder="-- Pilih Atasan Langsung --"
                    />

                    <x-input
                        name="join_date"
                        type="date"
                        label="Tanggal Bergabung"
                        :value="$employee->join_date?->toDateString()"
                        required
                    />

                    <div class="flex flex-col justify-end">
                        <label class="flex min-h-12 cursor-pointer items-center gap-2.5 rounded-2xl border border-border bg-surface px-4 py-3 text-sm font-semibold text-text">
                            <input type="hidden" name="is_active" value="0">
                            <input
                                type="checkbox"
                                name="is_active"
                                value="1"
                                @checked(old('is_active', $employee->is_active))
                                class="h-4 w-4 rounded border-border text-brand-navy"
                            >
                            <span>Karyawan Aktif</span>
                        </label>
                    </div>
                </div>
            </x-card>

            <x-card class="space-y-4">
                <div>
                    <h2 class="text-base font-bold text-brand-ink">Masa Kontrak &amp; Kompensasi</h2>
                    <p class="mt-0.5 text-xs text-muted">Atur tanggal mulai/selesai kontrak serta komponen gaji bulanan.</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-input
                        name="contract_start_date"
                        type="date"
                        label="Tanggal Mulai Kontrak"
                        :value="$employee->contract_start_date?->toDateString() ?? $employee->join_date?->toDateString()"
                    />

                    <x-input
                        name="contract_end_date"
                        type="date"
                        label="Tanggal Berakhir Kontrak (Opsional)"
                        :value="$employee->contract_end_date?->toDateString()"
                        hint="Kosongkan bila berstatus karyawan tetap (PKWTT)."
                    />

                    @if ($canEditSalary)
                        <x-input
                            name="base_salary"
                            type="number"
                            min="0"
                            step="50000"
                            label="Gaji Pokok Bulanan (Rp)"
                            :value="$employee->base_salary"
                        />

                        <x-input
                            name="fixed_allowance"
                            type="number"
                            min="0"
                            step="50000"
                            label="Tunjangan Tetap Bulanan (Rp)"
                            :value="$employee->fixed_allowance"
                        />
                    @endif
                </div>
            </x-card>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <x-button href="{{ route('management.employees.index') }}" variant="secondary">
                    {{ __('ui.actions.cancel') }}
                </x-button>
                <x-button type="submit" variant="primary" size="lg">
                    Simpan Perubahan
                </x-button>
            </div>
        </form>
    </div>
</x-app-layout>
