@php
    $user = auth()->user();
    $canCreateEmployee = $user?->can('create', \App\Models\Employee::class) ?? false;

    $employmentBadgeVariant = static fn (\App\Enums\EmploymentType $type): string => match ($type) {
        \App\Enums\EmploymentType::EXECUTIVE => 'violet',
        \App\Enums\EmploymentType::INTERN => 'info',
        \App\Enums\EmploymentType::STAFF => 'neutral',
    };

    $departmentOptions = $departments->mapWithKeys(
        static fn ($department): array => [$department->id => "{$department->name} ({$department->code})"]
    )->all();

    $divisionJson = $divisions->map(static fn ($division): array => [
        'id' => (string) $division->id,
        'department_id' => (string) $division->department_id,
        'label' => "{$division->name} ({$division->code})",
    ])->values()->all();

    $roleOptions = collect($filterableRoles)->mapWithKeys(
        static fn (\App\Enums\RoleName $role): array => [$role->value => $role->label()]
    )->all();

    $employmentTypeOptions = collect($employmentTypes)->mapWithKeys(
        static fn (\App\Enums\EmploymentType $type): array => [$type->value => $type->label()]
    )->all();

    $statusOptions = [
        'active' => 'Aktif',
        'pending_activation' => 'Menunggu Aktivasi',
        'inactive' => 'Nonaktif',
    ];
@endphp

<x-app-layout title="Manajemen Karyawan" subtitle="Kelola akun karyawan dan tautan undangan aktivasi">
    <div class="space-y-4">
        {{-- Header & Aksi Utama --}}
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-base font-bold text-brand-ink sm:text-lg">Daftar Karyawan</h2>
                <p class="text-xs text-muted">
                    @if ($hasActiveFilters)
                        Ditemukan {{ $employees->total() }} karyawan sesuai filter pencarian Anda.
                    @else
                        Total {{ $employees->total() }} karyawan sesuai cakupan akses Anda.
                    @endif
                </p>
            </div>

            @if ($canCreateEmployee)
                <x-button href="{{ route('management.employees.create') }}" variant="checkin" size="md">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/>
                    </svg>
                    <span>Tambah Karyawan</span>
                </x-button>
            @endif
        </div>

        {{-- Pencarian & Filter Karyawan --}}
        <x-card>
            <form
                method="GET"
                action="{{ route('management.employees.index') }}"
                x-data="{
                    departmentId: @js($selectedDepartmentId !== null ? (string) $selectedDepartmentId : ''),
                    divisionId: @js($selectedDivisionFilter !== null ? (string) $selectedDivisionFilter : ''),
                    allDivisions: @js($divisionJson),
                    get filteredDivisions() {
                        if (!this.departmentId) return this.allDivisions;
                        return this.allDivisions.filter(d => d.department_id === this.departmentId);
                    },
                    onDepartmentChange() {
                        if (this.divisionId && this.divisionId !== 'none') {
                            const stillValid = this.filteredDivisions.some(d => d.id === this.divisionId);
                            if (!stillValid) this.divisionId = '';
                        }
                    }
                }"
                class="space-y-4"
            >
                <div class="grid gap-3.5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                    {{-- Input Cari --}}
                    <div class="sm:col-span-2 {{ $canFilterDepartment ? 'xl:col-span-2' : ($canFilterDivision ? 'xl:col-span-2' : 'xl:col-span-3') }}">
                        <x-input
                            id="search-employee"
                            name="q"
                            type="search"
                            label="Cari Karyawan"
                            :value="$search"
                            placeholder="Nama, NIK, email, atau jabatan..."
                        >
                            <x-slot:leading>
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="11" cy="11" r="7" />
                                    <path stroke-linecap="round" d="M20 20l-3.5-3.5" />
                                </svg>
                            </x-slot:leading>
                        </x-input>
                    </div>

                    {{-- Filter Departemen (Super Admin & HR Manager) --}}
                    @if ($canFilterDepartment)
                        <x-select
                            id="filter-employee-department"
                            name="department_id"
                            label="Departemen"
                            :options="$departmentOptions"
                            :value="$selectedDepartmentId"
                            placeholder="Semua Departemen"
                            x-model="departmentId"
                            x-on:change="onDepartmentChange()"
                        />
                    @endif

                    {{-- Filter Divisi (Super Admin, HR Manager & Dept Manager) --}}
                    @if ($canFilterDivision)
                        <div class="space-y-1.5">
                            <label for="filter-employee-division" class="block text-sm font-semibold text-text">
                                Divisi
                            </label>
                            <select
                                id="filter-employee-division"
                                name="division_id"
                                x-model="divisionId"
                                class="block w-full min-h-12 rounded-2xl border border-border bg-card px-4 py-3 text-sm text-text shadow-xs transition-[border-color,box-shadow] duration-150 hover:border-muted/50 focus:border-brand-navy focus:outline-none focus:ring-4 focus:ring-brand-navy/10"
                            >
                                <option value="">Semua Divisi</option>
                                <option value="none" @selected($selectedDivisionFilter === 'none')>Tanpa Divisi</option>
                                <template x-for="division in filteredDivisions" :key="division.id">
                                    <option
                                        :value="division.id"
                                        :selected="divisionId === division.id"
                                        x-text="division.label"
                                    ></option>
                                </template>
                            </select>
                        </div>
                    @endif

                    {{-- Filter Peran --}}
                    <x-select
                        id="filter-employee-role"
                        name="role"
                        label="Peran"
                        :options="$roleOptions"
                        :value="$selectedRole?->value"
                        placeholder="Semua Peran"
                    />

                    {{-- Filter Tipe Karyawan --}}
                    <x-select
                        id="filter-employee-type"
                        name="employment_type"
                        label="Tipe Karyawan"
                        :options="$employmentTypeOptions"
                        :value="$selectedEmploymentType?->value"
                        placeholder="Semua Tipe"
                    />

                    {{-- Filter Status Akun --}}
                    <x-select
                        id="filter-employee-status"
                        name="status"
                        label="Status Akun"
                        :options="$statusOptions"
                        :value="$selectedStatus"
                        placeholder="Semua Status"
                    />
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-border pt-3">
                    <p class="text-xs text-muted">
                        Menampilkan <strong class="font-semibold text-brand-ink">{{ $employees->count() }}</strong> dari <strong class="font-semibold text-brand-ink">{{ $employees->total() }}</strong> karyawan.
                    </p>

                    <div class="flex flex-wrap items-center gap-2">
                        @if ($hasActiveFilters)
                            <x-button href="{{ route('management.employees.index') }}" variant="secondary" size="sm">
                                Reset Filter
                            </x-button>
                        @endif

                        <x-button type="submit" variant="primary" size="sm">
                            Terapkan Filter
                        </x-button>
                    </div>
                </div>
            </form>
        </x-card>

        {{-- Banner Tautan Undangan Aktivasi Sekali Pakai --}}
        @if (session('invitation_url'))
            <x-card class="border-brand-green/30 bg-brand-green-soft/60">
                <div
                    x-data="{
                        copied: false,
                        url: @js((string) session('invitation_url')),
                        async copyLink() {
                            try {
                                if (navigator.clipboard && window.isSecureContext) {
                                    await navigator.clipboard.writeText(this.url);
                                } else {
                                    this.$refs.inviteInput.focus();
                                    this.$refs.inviteInput.select();
                                    document.execCommand('copy');
                                }
                                this.copied = true;
                                setTimeout(() => { this.copied = false; }, 2500);
                            } catch (e) {
                                this.$refs.inviteInput.focus();
                                this.$refs.inviteInput.select();
                            }
                        }
                    }"
                    class="space-y-3"
                >
                    <div class="flex items-start gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-brand-green-strong text-on-brand">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-bold text-success-ink">
                                Tautan Undangan Aktivasi
                                @if (session('invited_name'))
                                    untuk {{ session('invited_name') }}
                                @endif
                            </p>
                            <p class="mt-0.5 text-xs leading-relaxed text-success-ink/90">
                                Bagikan tautan bertanda tangan sekali pakai ini kepada karyawan untuk mengatur kata sandi sendiri (berlaku {{ config('attendance.invitation_expiry_hours', 72) }} jam).
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <input
                            x-ref="inviteInput"
                            type="text"
                            readonly
                            value="{{ session('invitation_url') }}"
                            x-on:click="$event.target.select()"
                            aria-label="Tautan undangan aktivasi"
                            class="min-h-11 w-full flex-1 rounded-2xl border border-brand-green/30 bg-card px-3.5 py-2 font-mono text-xs text-text"
                        >
                        <x-button
                            type="button"
                            variant="checkin"
                            size="sm"
                            x-on:click="copyLink()"
                            class="shrink-0"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="9" y="9" width="13" height="13" rx="2"/>
                                <path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/>
                            </svg>
                            <span x-text="copied ? @js(__('ui.actions.copied')) : @js(__('ui.actions.copy_link'))">
                                {{ __('ui.actions.copy_link') }}
                            </span>
                        </x-button>
                    </div>
                </div>
            </x-card>
        @endif

        @if ($employees->isEmpty())
            <x-empty-state
                :title="$hasActiveFilters ? 'Tidak ada karyawan yang cocok' : 'Belum ada data karyawan'"
                :description="$hasActiveFilters ? 'Coba ubah kata kunci pencarian atau reset filter untuk melihat seluruh karyawan.' : 'Belum ada karyawan yang tercatat dalam cakupan akses Anda.'"
            >
                @if ($hasActiveFilters)
                    <x-slot:action>
                        <x-button href="{{ route('management.employees.index') }}" variant="secondary" size="sm">
                            Reset Filter
                        </x-button>
                    </x-slot:action>
                @elseif ($canCreateEmployee)
                    <x-slot:action>
                        <x-button href="{{ route('management.employees.create') }}" variant="checkin" size="sm">
                            Tambah Karyawan Pertama
                        </x-button>
                    </x-slot:action>
                @endif
            </x-empty-state>
        @else
            {{-- Daftar Kartu Mobile --}}
            <div class="space-y-3 md:hidden">
                @foreach ($employees as $employee)
                    @php
                        $account = $employee->user;
                        $isActivated = $account?->hasActivatedPassword() ?? false;
                        $role = $account?->primaryRole();
                        $contract = $employee->contractSummary();
                        $canEditEmployee = $user?->can('update', $employee) ?? false;
                        $canReinvite = ! $isActivated
                            && $canCreateEmployee
                            && ($user?->can('regenerateInvitation', $employee) ?? false);
                    @endphp

                    <x-card class="space-y-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-bold text-brand-ink">
                                    {{ $account?->name ?? '-' }}
                                </p>
                                <p class="text-xs font-medium text-muted">
                                    {{ $employee->employee_number }} · {{ $account?->email ?? '-' }}
                                </p>
                            </div>

                            @if ($isActivated)
                                <x-badge variant="success">Aktif</x-badge>
                            @else
                                <x-badge variant="warning">Menunggu Aktivasi</x-badge>
                            @endif
                        </div>

                        <div class="flex flex-wrap items-center gap-1.5">
                            @if ($role)
                                <x-badge variant="navy">{{ $role->label() }}</x-badge>
                            @endif
                            <x-badge :variant="$employmentBadgeVariant($employee->employment_type)">
                                {{ $employee->employment_type->label() }}
                            </x-badge>
                            @if (! $employee->is_active || ! ($account?->is_active ?? true))
                                <x-badge variant="danger">Nonaktif</x-badge>
                            @endif
                        </div>

                        <dl class="grid grid-cols-2 gap-x-3 gap-y-2 rounded-2xl bg-surface p-3 text-xs">
                            <div>
                                <dt class="text-muted">Departemen</dt>
                                <dd class="mt-0.5 font-semibold text-text">{{ $employee->department?->name ?? '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-muted">Divisi</dt>
                                <dd class="mt-0.5 font-semibold text-text">{{ $employee->division?->name ?? '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-muted">Jabatan</dt>
                                <dd class="mt-0.5 font-semibold text-text">{{ $employee->position_title ?? '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-muted">Jadwal Kerja</dt>
                                <dd class="mt-0.5 font-semibold text-text">{{ $employee->workSchedule?->name ?? '-' }}</dd>
                            </div>
                            <div class="col-span-2">
                                <dt class="text-muted">Masa Kontrak</dt>
                                <dd class="mt-1 flex flex-wrap items-center gap-2">
                                    <x-badge :variant="$contract['variant']">{{ $contract['label'] }}</x-badge>
                                    <span class="font-medium text-text">{{ $contract['period_text'] }}</span>
                                </dd>
                            </div>
                        </dl>

                        <div class="flex flex-wrap gap-2">
                            @if ($canEditEmployee)
                                <x-button href="{{ route('management.employees.edit', $employee) }}" variant="secondary" size="sm" class="flex-1">
                                    Ubah Data &amp; Kontrak
                                </x-button>
                            @endif

                            @if ($canReinvite)
                                <form method="POST" action="{{ route('management.employees.reinvite', $employee) }}" class="flex-1">
                                    @csrf
                                    <x-button type="submit" variant="secondary" size="sm" fullWidth>
                                        Undang Ulang
                                    </x-button>
                                </form>
                            @endif
                        </div>
                    </x-card>
                @endforeach
            </div>

            {{-- Tabel Desktop --}}
            <x-card padding="p-0" class="hidden overflow-hidden md:block">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-border text-left text-sm">
                        <thead class="bg-surface text-xs font-semibold tracking-wide text-muted uppercase">
                            <tr>
                                <th scope="col" class="px-5 py-3.5">Karyawan</th>
                                <th scope="col" class="px-5 py-3.5">Unit &amp; Jabatan</th>
                                <th scope="col" class="px-5 py-3.5">Peran &amp; Tipe</th>
                                <th scope="col" class="px-5 py-3.5">Masa Kontrak</th>
                                <th scope="col" class="px-5 py-3.5">Jadwal Kerja</th>
                                <th scope="col" class="px-5 py-3.5">Status Akun</th>
                                <th scope="col" class="px-5 py-3.5 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border bg-card">
                            @foreach ($employees as $employee)
                                @php
                                    $account = $employee->user;
                                    $isActivated = $account?->hasActivatedPassword() ?? false;
                                    $role = $account?->primaryRole();
                                    $contract = $employee->contractSummary();
                                    $canEditEmployee = $user?->can('update', $employee) ?? false;
                                    $canReinvite = ! $isActivated
                                        && $canCreateEmployee
                                        && ($user?->can('regenerateInvitation', $employee) ?? false);
                                @endphp

                                <tr class="transition-colors duration-150 hover:bg-surface/60">
                                    <td class="px-5 py-4 align-top">
                                        <p class="font-bold text-brand-ink">{{ $account?->name ?? '-' }}</p>
                                        <p class="mt-0.5 text-xs font-medium text-muted">{{ $employee->employee_number }}</p>
                                        <p class="text-xs text-muted">{{ $account?->email ?? '-' }}</p>
                                    </td>
                                    <td class="px-5 py-4 align-top">
                                        <p class="font-semibold text-text">{{ $employee->department?->name ?? '-' }}</p>
                                        <p class="mt-0.5 text-xs text-muted">
                                            {{ $employee->division?->name ?? 'Tanpa Divisi' }}
                                            @if ($employee->position_title)
                                                · {{ $employee->position_title }}
                                            @endif
                                        </p>
                                    </td>
                                    <td class="px-5 py-4 align-top">
                                        <div class="flex flex-wrap gap-1.5">
                                            @if ($role)
                                                <x-badge variant="navy">{{ $role->label() }}</x-badge>
                                            @endif
                                            <x-badge :variant="$employmentBadgeVariant($employee->employment_type)">
                                                {{ $employee->employment_type->label() }}
                                            </x-badge>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 align-top">
                                        <x-badge :variant="$contract['variant']">{{ $contract['label'] }}</x-badge>
                                        <p class="mt-1 text-xs text-muted">{{ $contract['period_text'] }}</p>
                                    </td>
                                    <td class="px-5 py-4 align-top">
                                        <p class="font-medium text-text">{{ $employee->workSchedule?->name ?? '-' }}</p>
                                        @if ($employee->workSchedule)
                                            <p class="mt-0.5 text-xs text-muted">
                                                {{ substr((string) $employee->workSchedule->check_in_time, 0, 5) }}
                                                –
                                                {{ substr((string) $employee->workSchedule->check_out_time, 0, 5) }} WIB
                                            </p>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 align-top">
                                        <div class="flex flex-wrap gap-1.5">
                                            @if ($isActivated)
                                                <x-badge variant="success">Aktif</x-badge>
                                            @else
                                                <x-badge variant="warning">Menunggu Aktivasi</x-badge>
                                            @endif

                                            @if (! $employee->is_active || ! ($account?->is_active ?? true))
                                                <x-badge variant="danger">Nonaktif</x-badge>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 text-right align-top">
                                        <div class="inline-flex flex-wrap items-center justify-end gap-1.5">
                                            @if ($canEditEmployee)
                                                <x-button href="{{ route('management.employees.edit', $employee) }}" variant="ghost" size="sm">
                                                    Ubah
                                                </x-button>
                                            @endif

                                            @if ($canReinvite)
                                                <form method="POST" action="{{ route('management.employees.reinvite', $employee) }}" class="inline-block">
                                                    @csrf
                                                    <x-button type="submit" variant="secondary" size="sm">
                                                        Undang Ulang
                                                    </x-button>
                                                </form>
                                            @endif

                                            @if (! $canEditEmployee && ! $canReinvite)
                                                <span class="text-xs text-muted">-</span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>

            @if ($employees->hasPages())
                <div class="pt-1">
                    {{ $employees->links() }}
                </div>
            @endif
        @endif
    </div>
</x-app-layout>
