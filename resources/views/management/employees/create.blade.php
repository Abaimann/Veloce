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

    $roleOptions = collect($allowedRoles)->mapWithKeys(
        static fn ($role): array => [$role->value => $role->label()]
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

    $initialDepartmentId = (string) old(
        'department_id',
        $lockedDepartmentId ?? ($departments->count() === 1 ? $departments->first()->id : ''),
    );
@endphp

<x-app-layout title="Tambah Karyawan" subtitle="Buat profil karyawan baru tanpa kata sandi awal">
    <div class="space-y-4">
        <div class="flex items-center justify-between gap-3">
            <x-button href="{{ route('management.employees.index') }}" variant="ghost" size="sm">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                </svg>
                <span>Kembali ke Daftar Karyawan</span>
            </x-button>
        </div>

        {{-- Informasi Alur Undangan Aktivasi Tanpa Password --}}
        <div class="rounded-2xl border border-info/25 bg-info-soft p-4 text-xs leading-relaxed text-info-ink">
            <p class="font-bold">Aktivasi Akun Mandiri Melalui Tautan Undangan</p>
            <p class="mt-1">
                Formulir ini tidak menggunakan kolom kata sandi. Setelah akun disimpan, sistem akan menghasilkan tautan undangan bertanda tangan sekali pakai agar karyawan dapat mengatur kata sandinya sendiri.
            </p>
        </div>

        @if ($departments->isEmpty() || $workSchedules->isEmpty())
            <div class="rounded-2xl border border-warning/25 bg-warning-soft p-4 text-xs leading-relaxed text-warning-ink">
                <p class="font-bold">Master Data Belum Lengkap</p>
                <p class="mt-1">
                    Pastikan minimal satu Departemen aktif dan satu Jadwal Kerja aktif sudah tersedia di Master Data sebelum menambahkan karyawan baru.
                </p>
            </div>
        @endif

        <form
            method="POST"
            action="{{ route('management.employees.store') }}"
            x-data="{
                departmentId: @js($initialDepartmentId),
                divisionId: @js((string) old('division_id', '')),
                divisions: @js($divisionsPayload),
                get filteredDivisions() {
                    if (!this.departmentId) {
                        return [];
                    }
                    return this.divisions.filter(
                        (item) => String(item.department_id) === String(this.departmentId)
                    );
                },
                syncDivision() {
                    const stillValid = this.filteredDivisions.some(
                        (item) => String(item.id) === String(this.divisionId)
                    );
                    if (!stillValid) {
                        this.divisionId = '';
                    }
                }
            }"
            class="space-y-5"
        >
            @csrf

            <x-card class="space-y-4">
                <div>
                    <h2 class="text-base font-bold text-brand-ink">Identitas &amp; Akun Karyawan</h2>
                    <p class="mt-0.5 text-xs text-muted">Data utama untuk login dan identitas kepegawaian.</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-input
                        name="name"
                        label="Nama Lengkap"
                        placeholder="Contoh: Budi Santoso"
                        required
                        autofocus
                    />

                    <x-input
                        name="email"
                        type="email"
                        label="Email Perusahaan"
                        placeholder="budi@veloce.id"
                        required
                    />

                    <x-input
                        name="employee_number"
                        label="Nomor Induk Karyawan (NIK)"
                        placeholder="Contoh: VLC-2026-001"
                        required
                    />

                    <x-input
                        name="position_title"
                        label="Jabatan (Opsional)"
                        placeholder="Contoh: Operator Produksi"
                    />
                </div>
            </x-card>

            <x-card class="space-y-4">
                <div>
                    <h2 class="text-base font-bold text-brand-ink">Penempatan, Peran &amp; Jadwal</h2>
                    <p class="mt-0.5 text-xs text-muted">Tentukan unit organisasi, peran akses, dan jadwal kerja.</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <x-select
                            name="department_id"
                            label="Departemen"
                            :options="$departmentOptions"
                            :value="$initialDepartmentId"
                            :placeholder="$lockedDepartmentId === null ? '-- Pilih Departemen --' : null"
                            x-model="departmentId"
                            x-on:change="syncDivision()"
                            required
                        />
                        @if ($lockedDepartmentId !== null)
                            <p class="text-xs text-muted">
                                Manager Departemen hanya dapat menambahkan karyawan di departemennya sendiri.
                            </p>
                        @endif
                    </div>

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
                        name="role"
                        label="Peran Sistem"
                        :options="$roleOptions"
                        placeholder="-- Pilih Peran --"
                        required
                    />

                    <x-select
                        name="employment_type"
                        label="Status Kepegawaian"
                        :options="$employmentTypeOptions"
                        placeholder="-- Pilih Status Kepegawaian --"
                        required
                    />

                    <x-select
                        name="work_schedule_id"
                        label="Jadwal Kerja"
                        :options="$workScheduleOptions"
                        placeholder="-- Pilih Jadwal Kerja --"
                        required
                    />

                    <x-select
                        name="supervisor_id"
                        label="Atasan Langsung (Opsional)"
                        :options="$supervisorOptions"
                        placeholder="-- Pilih Atasan Langsung --"
                    />

                    <x-input
                        name="join_date"
                        type="date"
                        label="Tanggal Bergabung"
                        :value="now('Asia/Jakarta')->toDateString()"
                        required
                    />
                </div>
            </x-card>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <x-button href="{{ route('management.employees.index') }}" variant="secondary">
                    {{ __('ui.actions.cancel') }}
                </x-button>
                <x-button type="submit" variant="checkin" size="lg">
                    Simpan &amp; Buat Undangan
                </x-button>
            </div>
        </form>
    </div>
</x-app-layout>
