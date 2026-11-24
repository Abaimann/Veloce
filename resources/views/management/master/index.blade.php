@php
    $initialTab = old('_tab') ?: request()->query('tab') ?: session('master_tab', 'departments');
    if (! in_array($initialTab, ['departments', 'divisions', 'schedules', 'offices', 'holidays'], true)) {
        $initialTab = 'departments';
    }

    $departmentOptions = $departments->mapWithKeys(
        static fn ($department): array => [$department->id => "{$department->name} ({$department->code})"]
    )->all();

    $employeeOptions = $employees->mapWithKeys(
        static fn ($employee): array => [
            $employee->id => sprintf('%s (%s)', $employee->user?->name ?? '-', $employee->employee_number),
        ]
    )->all();

    $dayLabels = [
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
        7 => 'Minggu',
    ];

    $shortDayLabels = [
        1 => 'Sen',
        2 => 'Sel',
        3 => 'Rab',
        4 => 'Kam',
        5 => 'Jum',
        6 => 'Sab',
        7 => 'Min',
    ];

    $selectedWorkDays = array_map('intval', (array) old('work_days', [1, 2, 3, 4, 5]));

    $routeUsesMethod = static function (string $routeName, string $expectedMethod): bool {
        $route = app('router')->getRoutes()->getByName($routeName);
        if ($route === null) {
            return true;
        }

        return in_array(strtoupper($expectedMethod), $route->methods(), true);
    };
@endphp

<x-app-layout title="Master Data" subtitle="Kelola struktur organisasi, jadwal kerja, geofence kantor, dan hari libur">
    <div
        x-data="{ tab: @js($initialTab) }"
        class="space-y-4"
    >
        {{-- Navigasi Tab Responsif --}}
        <div class="flex gap-2 overflow-x-auto pb-1" role="tablist" aria-label="Kategori Master Data">
            <button
                type="button"
                role="tab"
                x-on:click="tab = 'departments'"
                :aria-selected="tab === 'departments'"
                :class="tab === 'departments' ? 'bg-brand-navy text-on-brand border-brand-navy' : 'bg-card text-muted border-border hover:bg-surface hover:text-text'"
                class="inline-flex min-h-11 shrink-0 cursor-pointer items-center gap-2 rounded-2xl border px-4 py-2 text-xs font-semibold transition-colors duration-150"
            >
                <span>Departemen</span>
                <span class="rounded-full bg-card/20 px-2 py-0.5 text-[11px] font-bold">{{ $departments->count() }}</span>
            </button>

            <button
                type="button"
                role="tab"
                x-on:click="tab = 'divisions'"
                :aria-selected="tab === 'divisions'"
                :class="tab === 'divisions' ? 'bg-brand-navy text-on-brand border-brand-navy' : 'bg-card text-muted border-border hover:bg-surface hover:text-text'"
                class="inline-flex min-h-11 shrink-0 cursor-pointer items-center gap-2 rounded-2xl border px-4 py-2 text-xs font-semibold transition-colors duration-150"
            >
                <span>Divisi</span>
                <span class="rounded-full bg-card/20 px-2 py-0.5 text-[11px] font-bold">{{ $divisions->count() }}</span>
            </button>

            <button
                type="button"
                role="tab"
                x-on:click="tab = 'schedules'"
                :aria-selected="tab === 'schedules'"
                :class="tab === 'schedules' ? 'bg-brand-navy text-on-brand border-brand-navy' : 'bg-card text-muted border-border hover:bg-surface hover:text-text'"
                class="inline-flex min-h-11 shrink-0 cursor-pointer items-center gap-2 rounded-2xl border px-4 py-2 text-xs font-semibold transition-colors duration-150"
            >
                <span>Jadwal Kerja</span>
                <span class="rounded-full bg-card/20 px-2 py-0.5 text-[11px] font-bold">{{ $workSchedules->count() }}</span>
            </button>

            <button
                type="button"
                role="tab"
                x-on:click="tab = 'offices'"
                :aria-selected="tab === 'offices'"
                :class="tab === 'offices' ? 'bg-brand-navy text-on-brand border-brand-navy' : 'bg-card text-muted border-border hover:bg-surface hover:text-text'"
                class="inline-flex min-h-11 shrink-0 cursor-pointer items-center gap-2 rounded-2xl border px-4 py-2 text-xs font-semibold transition-colors duration-150"
            >
                <span>Lokasi Kantor</span>
                <span class="rounded-full bg-card/20 px-2 py-0.5 text-[11px] font-bold">{{ $officeLocations->count() }}</span>
            </button>

            <button
                type="button"
                role="tab"
                x-on:click="tab = 'holidays'"
                :aria-selected="tab === 'holidays'"
                :class="tab === 'holidays' ? 'bg-brand-navy text-on-brand border-brand-navy' : 'bg-card text-muted border-border hover:bg-surface hover:text-text'"
                class="inline-flex min-h-11 shrink-0 cursor-pointer items-center gap-2 rounded-2xl border px-4 py-2 text-xs font-semibold transition-colors duration-150"
            >
                <span>Hari Libur</span>
                <span class="rounded-full bg-card/20 px-2 py-0.5 text-[11px] font-bold">{{ $holidays->count() }}</span>
            </button>
        </div>

        @if ($errors->any())
            <div class="rounded-2xl border border-danger/30 bg-danger-soft p-4 text-xs text-danger-ink">
                <p class="font-bold">Terdapat data yang belum valid. Silakan periksa kembali isian formulir Anda.</p>
            </div>
        @endif

        {{-- TAB 1: DEPARTEMEN --}}
        <section x-show="tab === 'departments'" class="grid gap-5 lg:grid-cols-12">
            <div class="lg:col-span-4">
                <x-card class="space-y-4">
                    <div>
                        <h2 class="text-base font-bold text-brand-ink">Tambah Departemen</h2>
                        <p class="mt-0.5 text-xs text-muted">Buat unit departemen baru perusahaan.</p>
                    </div>

                    <form method="POST" action="{{ route('management.master.departments.store') }}" class="space-y-3.5">
                        @csrf
                        <input type="hidden" name="_tab" value="departments">

                        <x-input
                            id="department-code"
                            name="code"
                            label="Kode Departemen"
                            placeholder="Contoh: PRD"
                            required
                        />

                        <x-input
                            id="department-name"
                            name="name"
                            label="Nama Departemen"
                            placeholder="Contoh: Produksi"
                            required
                        />

                        <x-select
                            id="department-head-employee"
                            name="head_employee_id"
                            label="Kepala Departemen (Opsional)"
                            :options="$employeeOptions"
                            placeholder="-- Belum ditentukan --"
                        />

                        <x-button type="submit" variant="checkin" fullWidth>
                            Simpan Departemen
                        </x-button>
                    </form>
                </x-card>
            </div>

            <div class="lg:col-span-8">
                @if ($departments->isEmpty())
                    <x-empty-state title="Belum ada departemen" description="Tambahkan departemen pertama melalui formulir di samping." />
                @else
                    <div class="space-y-3 md:hidden">
                        @foreach ($departments as $department)
                            <x-card class="space-y-3">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <p class="text-sm font-bold text-brand-ink">{{ $department->name }}</p>
                                        <p class="text-xs text-muted">
                                            Kode: {{ $department->code }} · {{ $department->divisions->count() }} divisi · {{ $department->employees_count }} karyawan
                                        </p>
                                        @if ($department->headEmployee?->user)
                                            <p class="mt-1 text-xs text-muted">Kepala: {{ $department->headEmployee->user->name }}</p>
                                        @endif
                                    </div>
                                    <x-badge :variant="$department->is_active ? 'success' : 'danger'">
                                        {{ $department->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </x-badge>
                                </div>

                                <form method="POST" action="{{ route('management.master.departments.toggle', $department) }}">
                                    @csrf
                                    @if ($routeUsesMethod('management.master.departments.toggle', 'PATCH'))
                                        @method('PATCH')
                                    @endif
                                    <x-button type="submit" variant="secondary" size="sm" fullWidth>
                                        {{ $department->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                    </x-button>
                                </form>
                            </x-card>
                        @endforeach
                    </div>

                    <x-card padding="p-0" class="hidden overflow-hidden md:block">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-border text-left text-sm">
                                <thead class="bg-surface text-xs font-semibold tracking-wide text-muted uppercase">
                                    <tr>
                                        <th scope="col" class="px-4 py-3">Kode</th>
                                        <th scope="col" class="px-4 py-3">Departemen</th>
                                        <th scope="col" class="px-4 py-3">Kepala</th>
                                        <th scope="col" class="px-4 py-3">Statistik</th>
                                        <th scope="col" class="px-4 py-3">Status</th>
                                        <th scope="col" class="px-4 py-3 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border bg-card">
                                    @foreach ($departments as $department)
                                        <tr>
                                            <td class="px-4 py-3 font-mono text-xs font-bold text-brand-ink">{{ $department->code }}</td>
                                            <td class="px-4 py-3 font-semibold text-text">{{ $department->name }}</td>
                                            <td class="px-4 py-3 text-xs text-muted">{{ $department->headEmployee?->user?->name ?? '-' }}</td>
                                            <td class="px-4 py-3 text-xs text-muted">
                                                {{ $department->divisions->count() }} divisi · {{ $department->employees_count }} karyawan
                                            </td>
                                            <td class="px-4 py-3">
                                                <x-badge :variant="$department->is_active ? 'success' : 'danger'">
                                                    {{ $department->is_active ? 'Aktif' : 'Nonaktif' }}
                                                </x-badge>
                                            </td>
                                            <td class="px-4 py-3 text-right">
                                                <form method="POST" action="{{ route('management.master.departments.toggle', $department) }}" class="inline-block">
                                                    @csrf
                                                    @if ($routeUsesMethod('management.master.departments.toggle', 'PATCH'))
                                                        @method('PATCH')
                                                    @endif
                                                    <x-button type="submit" variant="secondary" size="sm">
                                                        {{ $department->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                                    </x-button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </x-card>
                @endif
            </div>
        </section>

        {{-- TAB 2: DIVISI --}}
        <section x-show="tab === 'divisions'" class="grid gap-5 lg:grid-cols-12">
            <div class="lg:col-span-4">
                <x-card class="space-y-4">
                    <div>
                        <h2 class="text-base font-bold text-brand-ink">Tambah Divisi</h2>
                        <p class="mt-0.5 text-xs text-muted">Tambahkan divisi di bawah departemen tertentu.</p>
                    </div>

                    <form method="POST" action="{{ route('management.master.divisions.store') }}" class="space-y-3.5">
                        @csrf
                        <input type="hidden" name="_tab" value="divisions">

                        <x-select
                            id="division-department"
                            name="department_id"
                            label="Departemen"
                            :options="$departmentOptions"
                            placeholder="-- Pilih Departemen --"
                            required
                        />

                        <x-input
                            id="division-code"
                            name="code"
                            label="Kode Divisi"
                            placeholder="Contoh: QC"
                            required
                        />

                        <x-input
                            id="division-name"
                            name="name"
                            label="Nama Divisi"
                            placeholder="Contoh: Quality Control"
                            required
                        />

                        <x-select
                            id="division-head-employee"
                            name="head_employee_id"
                            label="Ketua Divisi (Opsional)"
                            :options="$employeeOptions"
                            placeholder="-- Belum ditentukan --"
                        />

                        <x-button type="submit" variant="checkin" fullWidth>
                            Simpan Divisi
                        </x-button>
                    </form>
                </x-card>
            </div>

            <div class="lg:col-span-8">
                @if ($divisions->isEmpty())
                    <x-empty-state title="Belum ada divisi" description="Tambahkan divisi untuk mengelompokkan tim di dalam departemen." />
                @else
                    <div class="space-y-3 md:hidden">
                        @foreach ($divisions as $division)
                            <x-card class="space-y-3">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <p class="text-sm font-bold text-brand-ink">{{ $division->name }}</p>
                                        <p class="text-xs text-muted">
                                            Kode: {{ $division->code }} · {{ $division->department?->name ?? '-' }} · {{ $division->employees_count }} karyawan
                                        </p>
                                        @if ($division->headEmployee?->user)
                                            <p class="mt-1 text-xs text-muted">Ketua: {{ $division->headEmployee->user->name }}</p>
                                        @endif
                                    </div>
                                    <x-badge :variant="$division->is_active ? 'success' : 'danger'">
                                        {{ $division->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </x-badge>
                                </div>

                                <form method="POST" action="{{ route('management.master.divisions.toggle', $division) }}">
                                    @csrf
                                    @if ($routeUsesMethod('management.master.divisions.toggle', 'PATCH'))
                                        @method('PATCH')
                                    @endif
                                    <x-button type="submit" variant="secondary" size="sm" fullWidth>
                                        {{ $division->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                    </x-button>
                                </form>
                            </x-card>
                        @endforeach
                    </div>

                    <x-card padding="p-0" class="hidden overflow-hidden md:block">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-border text-left text-sm">
                                <thead class="bg-surface text-xs font-semibold tracking-wide text-muted uppercase">
                                    <tr>
                                        <th scope="col" class="px-4 py-3">Kode</th>
                                        <th scope="col" class="px-4 py-3">Divisi</th>
                                        <th scope="col" class="px-4 py-3">Departemen</th>
                                        <th scope="col" class="px-4 py-3">Ketua</th>
                                        <th scope="col" class="px-4 py-3">Status</th>
                                        <th scope="col" class="px-4 py-3 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border bg-card">
                                    @foreach ($divisions as $division)
                                        <tr>
                                            <td class="px-4 py-3 font-mono text-xs font-bold text-brand-ink">{{ $division->code }}</td>
                                            <td class="px-4 py-3">
                                                <p class="font-semibold text-text">{{ $division->name }}</p>
                                                <p class="text-xs text-muted">{{ $division->employees_count }} karyawan</p>
                                            </td>
                                            <td class="px-4 py-3 text-xs font-medium text-text">{{ $division->department?->name ?? '-' }}</td>
                                            <td class="px-4 py-3 text-xs text-muted">{{ $division->headEmployee?->user?->name ?? '-' }}</td>
                                            <td class="px-4 py-3">
                                                <x-badge :variant="$division->is_active ? 'success' : 'danger'">
                                                    {{ $division->is_active ? 'Aktif' : 'Nonaktif' }}
                                                </x-badge>
                                            </td>
                                            <td class="px-4 py-3 text-right">
                                                <form method="POST" action="{{ route('management.master.divisions.toggle', $division) }}" class="inline-block">
                                                    @csrf
                                                    @if ($routeUsesMethod('management.master.divisions.toggle', 'PATCH'))
                                                        @method('PATCH')
                                                    @endif
                                                    <x-button type="submit" variant="secondary" size="sm">
                                                        {{ $division->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                                    </x-button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </x-card>
                @endif
            </div>
        </section>

        {{-- TAB 3: JADWAL KERJA --}}
        <section x-show="tab === 'schedules'" class="grid gap-5 lg:grid-cols-12">
            <div class="lg:col-span-4">
                <x-card class="space-y-4">
                    <div>
                        <h2 class="text-base font-bold text-brand-ink">Tambah Jadwal Kerja</h2>
                        <p class="mt-0.5 text-xs text-muted">Atur jam masuk, jam pulang, toleransi terlambat, dan hari kerja.</p>
                    </div>

                    <form method="POST" action="{{ route('management.master.schedules.store') }}" class="space-y-3.5">
                        @csrf
                        <input type="hidden" name="_tab" value="schedules">

                        <x-input
                            id="schedule-name"
                            name="name"
                            label="Nama Jadwal"
                            placeholder="Contoh: Shift Pagi Reguler"
                            required
                        />

                        <div class="grid grid-cols-2 gap-3">
                            <x-input
                                id="schedule-check-in"
                                name="check_in_time"
                                type="time"
                                label="Jam Masuk"
                                value="08:00"
                                required
                            />

                            <x-input
                                id="schedule-check-out"
                                name="check_out_time"
                                type="time"
                                label="Jam Pulang"
                                value="17:00"
                                required
                            />
                        </div>

                        <x-input
                            id="schedule-late-tolerance"
                            name="late_tolerance_minutes"
                            type="number"
                            min="0"
                            max="480"
                            label="Toleransi Terlambat (Menit)"
                            value="15"
                            required
                        />

                        <div class="space-y-2">
                            <span class="block text-sm font-semibold text-text">
                                Hari Kerja <span class="text-danger" aria-hidden="true">*</span>
                            </span>
                            <div class="grid grid-cols-2 gap-2">
                                @foreach ($dayLabels as $isoDay => $dayName)
                                    <label class="flex min-h-11 cursor-pointer items-center gap-2 rounded-2xl border border-border bg-surface px-3 py-2 text-xs font-medium text-text">
                                        <input
                                            type="checkbox"
                                            name="work_days[]"
                                            value="{{ $isoDay }}"
                                            @checked(in_array($isoDay, $selectedWorkDays, true))
                                            class="h-4 w-4 rounded border-border text-brand-navy"
                                        >
                                        <span>{{ $dayName }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @error('work_days')
                                <p class="text-xs font-medium text-danger-ink">{{ $message }}</p>
                            @enderror
                        </div>

                        <x-button type="submit" variant="checkin" fullWidth>
                            Simpan Jadwal Kerja
                        </x-button>
                    </form>
                </x-card>
            </div>

            <div class="lg:col-span-8">
                @if ($workSchedules->isEmpty())
                    <x-empty-state title="Belum ada jadwal kerja" description="Buat jadwal kerja untuk menentukan jam operasional karyawan." />
                @else
                    <div class="space-y-3 md:hidden">
                        @foreach ($workSchedules as $schedule)
                            @php
                                $formattedDays = collect($schedule->work_days ?? [])
                                    ->map(static fn ($day): string => $shortDayLabels[(int) $day] ?? (string) $day)
                                    ->implode(', ');
                            @endphp

                            <x-card class="space-y-3">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <p class="text-sm font-bold text-brand-ink">{{ $schedule->name }}</p>
                                        <p class="text-xs font-medium text-text">
                                            {{ substr((string) $schedule->check_in_time, 0, 5) }} – {{ substr((string) $schedule->check_out_time, 0, 5) }} WIB
                                        </p>
                                        <p class="mt-0.5 text-xs text-muted">
                                            Toleransi {{ $schedule->late_tolerance_minutes }} menit · {{ $formattedDays ?: '-' }}
                                        </p>
                                    </div>
                                    <x-badge :variant="$schedule->is_active ? 'success' : 'danger'">
                                        {{ $schedule->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </x-badge>
                                </div>

                                <form method="POST" action="{{ route('management.master.schedules.toggle', $schedule) }}">
                                    @csrf
                                    @if ($routeUsesMethod('management.master.schedules.toggle', 'PATCH'))
                                        @method('PATCH')
                                    @endif
                                    <x-button type="submit" variant="secondary" size="sm" fullWidth>
                                        {{ $schedule->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                    </x-button>
                                </form>
                            </x-card>
                        @endforeach
                    </div>

                    <x-card padding="p-0" class="hidden overflow-hidden md:block">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-border text-left text-sm">
                                <thead class="bg-surface text-xs font-semibold tracking-wide text-muted uppercase">
                                    <tr>
                                        <th scope="col" class="px-4 py-3">Nama Jadwal</th>
                                        <th scope="col" class="px-4 py-3">Jam Kerja</th>
                                        <th scope="col" class="px-4 py-3">Hari Kerja</th>
                                        <th scope="col" class="px-4 py-3">Status</th>
                                        <th scope="col" class="px-4 py-3 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border bg-card">
                                    @foreach ($workSchedules as $schedule)
                                        @php
                                            $formattedDays = collect($schedule->work_days ?? [])
                                                ->map(static fn ($day): string => $shortDayLabels[(int) $day] ?? (string) $day)
                                                ->implode(', ');
                                        @endphp
                                        <tr>
                                            <td class="px-4 py-3">
                                                <p class="font-semibold text-brand-ink">{{ $schedule->name }}</p>
                                                <p class="text-xs text-muted">{{ $schedule->employees_count }} karyawan</p>
                                            </td>
                                            <td class="px-4 py-3 text-xs">
                                                <p class="font-semibold text-text">
                                                    {{ substr((string) $schedule->check_in_time, 0, 5) }} – {{ substr((string) $schedule->check_out_time, 0, 5) }} WIB
                                                </p>
                                                <p class="text-muted">Toleransi {{ $schedule->late_tolerance_minutes }} menit</p>
                                            </td>
                                            <td class="px-4 py-3 text-xs text-muted">{{ $formattedDays ?: '-' }}</td>
                                            <td class="px-4 py-3">
                                                <x-badge :variant="$schedule->is_active ? 'success' : 'danger'">
                                                    {{ $schedule->is_active ? 'Aktif' : 'Nonaktif' }}
                                                </x-badge>
                                            </td>
                                            <td class="px-4 py-3 text-right">
                                                <form method="POST" action="{{ route('management.master.schedules.toggle', $schedule) }}" class="inline-block">
                                                    @csrf
                                                    @if ($routeUsesMethod('management.master.schedules.toggle', 'PATCH'))
                                                        @method('PATCH')
                                                    @endif
                                                    <x-button type="submit" variant="secondary" size="sm">
                                                        {{ $schedule->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                                    </x-button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </x-card>
                @endif
            </div>
        </section>

        {{-- TAB 4: LOKASI KANTOR --}}
        <section x-show="tab === 'offices'" class="grid gap-5 lg:grid-cols-12">
            <div class="lg:col-span-4">
                <x-card class="space-y-4">
                    <div>
                        <h2 class="text-base font-bold text-brand-ink">Tambah Lokasi Kantor</h2>
                        <p class="mt-0.5 text-xs text-muted">Titik koordinat dan radius geofence validasi absensi.</p>
                    </div>

                    <form method="POST" action="{{ route('management.master.offices.store') }}" class="space-y-3.5">
                        @csrf
                        <input type="hidden" name="_tab" value="offices">

                        <x-input
                            id="office-name"
                            name="name"
                            label="Nama Lokasi Kantor"
                            placeholder="Contoh: Pabrik Cikarang"
                            required
                        />

                        <x-input
                            id="office-address"
                            name="address"
                            label="Alamat (Opsional)"
                            placeholder="Kawasan Industri Jababeka"
                        />

                        <div class="grid grid-cols-2 gap-3">
                            <x-input
                                id="office-lat"
                                name="lat"
                                type="number"
                                step="0.0000001"
                                label="Latitude"
                                placeholder="-6.3105800"
                                required
                            />

                            <x-input
                                id="office-lng"
                                name="lng"
                                type="number"
                                step="0.0000001"
                                label="Longitude"
                                placeholder="107.1436200"
                                required
                            />
                        </div>

                        <x-input
                            id="office-radius"
                            name="radius_meters"
                            type="number"
                            min="10"
                            max="50000"
                            label="Radius Geofence (Meter)"
                            value="150"
                            required
                        />

                        <x-button type="submit" variant="checkin" fullWidth>
                            Simpan Lokasi Kantor
                        </x-button>
                    </form>
                </x-card>
            </div>

            <div class="lg:col-span-8">
                @if ($officeLocations->isEmpty())
                    <x-empty-state title="Belum ada lokasi kantor" description="Tambahkan koordinat lokasi kantor untuk validasi radius absensi." />
                @else
                    <div class="space-y-3 md:hidden">
                        @foreach ($officeLocations as $office)
                            <x-card class="space-y-3">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="text-sm font-bold text-brand-ink">{{ $office->name }}</p>
                                        @if ($office->address)
                                            <p class="text-xs text-muted">{{ $office->address }}</p>
                                        @endif
                                        <p class="mt-1 font-mono text-xs text-text">
                                            {{ $office->lat }}, {{ $office->lng }} · Radius {{ number_format($office->radius_meters) }} m
                                        </p>
                                    </div>
                                    <x-badge :variant="$office->is_active ? 'success' : 'danger'">
                                        {{ $office->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </x-badge>
                                </div>

                                <form method="POST" action="{{ route('management.master.offices.toggle', $office) }}">
                                    @csrf
                                    @if ($routeUsesMethod('management.master.offices.toggle', 'PATCH'))
                                        @method('PATCH')
                                    @endif
                                    <x-button type="submit" variant="secondary" size="sm" fullWidth>
                                        {{ $office->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                    </x-button>
                                </form>
                            </x-card>
                        @endforeach
                    </div>

                    <x-card padding="p-0" class="hidden overflow-hidden md:block">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-border text-left text-sm">
                                <thead class="bg-surface text-xs font-semibold tracking-wide text-muted uppercase">
                                    <tr>
                                        <th scope="col" class="px-4 py-3">Lokasi Kantor</th>
                                        <th scope="col" class="px-4 py-3">Koordinat</th>
                                        <th scope="col" class="px-4 py-3">Radius</th>
                                        <th scope="col" class="px-4 py-3">Status</th>
                                        <th scope="col" class="px-4 py-3 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border bg-card">
                                    @foreach ($officeLocations as $office)
                                        <tr>
                                            <td class="px-4 py-3">
                                                <p class="font-semibold text-brand-ink">{{ $office->name }}</p>
                                                <p class="text-xs text-muted">{{ $office->address ?: '-' }}</p>
                                            </td>
                                            <td class="px-4 py-3 font-mono text-xs text-text">
                                                {{ $office->lat }}, {{ $office->lng }}
                                            </td>
                                            <td class="px-4 py-3 text-xs font-semibold text-text">
                                                {{ number_format($office->radius_meters) }} m
                                            </td>
                                            <td class="px-4 py-3">
                                                <x-badge :variant="$office->is_active ? 'success' : 'danger'">
                                                    {{ $office->is_active ? 'Aktif' : 'Nonaktif' }}
                                                </x-badge>
                                            </td>
                                            <td class="px-4 py-3 text-right">
                                                <form method="POST" action="{{ route('management.master.offices.toggle', $office) }}" class="inline-block">
                                                    @csrf
                                                    @if ($routeUsesMethod('management.master.offices.toggle', 'PATCH'))
                                                        @method('PATCH')
                                                    @endif
                                                    <x-button type="submit" variant="secondary" size="sm">
                                                        {{ $office->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                                    </x-button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </x-card>
                @endif
            </div>
        </section>

        {{-- TAB 5: HARI LIBUR --}}
        <section x-show="tab === 'holidays'" class="grid gap-5 lg:grid-cols-12">
            <div class="lg:col-span-4">
                <x-card class="space-y-4">
                    <div>
                        <h2 class="text-base font-bold text-brand-ink">Tambah Hari Libur</h2>
                        <p class="mt-0.5 text-xs text-muted">Hari libur dikecualikan dari perhitungan hari kerja cuti dan absensi.</p>
                    </div>

                    <form method="POST" action="{{ route('management.master.holidays.store') }}" class="space-y-3.5">
                        @csrf
                        <input type="hidden" name="_tab" value="holidays">

                        <x-input
                            id="holiday-date"
                            name="date"
                            type="date"
                            label="Tanggal Libur"
                            required
                        />

                        <x-input
                            id="holiday-name"
                            name="name"
                            label="Nama Hari Libur"
                            placeholder="Contoh: Hari Kemerdekaan RI"
                            required
                        />

                        <x-button type="submit" variant="checkin" fullWidth>
                            Simpan Hari Libur
                        </x-button>
                    </form>
                </x-card>
            </div>

            <div class="lg:col-span-8">
                @if ($holidays->isEmpty())
                    <x-empty-state title="Belum ada hari libur" description="Tambahkan daftar hari libur nasional atau libur perusahaan." />
                @else
                    <div class="space-y-3 md:hidden">
                        @foreach ($holidays as $holiday)
                            <x-card class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-bold text-brand-ink">{{ $holiday->name }}</p>
                                    <p class="mt-0.5 text-xs text-muted">{{ $holiday->date->translatedFormat('l, d F Y') }}</p>
                                </div>

                                <form method="POST" action="{{ route('management.master.holidays.destroy', $holiday) }}" class="shrink-0">
                                    @csrf
                                    @if ($routeUsesMethod('management.master.holidays.destroy', 'DELETE'))
                                        @method('DELETE')
                                    @endif
                                    <x-button type="submit" variant="danger-outline" size="sm">
                                        {{ __('ui.actions.delete') }}
                                    </x-button>
                                </form>
                            </x-card>
                        @endforeach
                    </div>

                    <x-card padding="p-0" class="hidden overflow-hidden md:block">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-border text-left text-sm">
                                <thead class="bg-surface text-xs font-semibold tracking-wide text-muted uppercase">
                                    <tr>
                                        <th scope="col" class="px-4 py-3">Tanggal</th>
                                        <th scope="col" class="px-4 py-3">Keterangan Hari Libur</th>
                                        <th scope="col" class="px-4 py-3 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border bg-card">
                                    @foreach ($holidays as $holiday)
                                        <tr>
                                            <td class="px-4 py-3 text-xs font-semibold text-text">
                                                {{ $holiday->date->translatedFormat('l, d F Y') }}
                                            </td>
                                            <td class="px-4 py-3 font-semibold text-brand-ink">
                                                {{ $holiday->name }}
                                            </td>
                                            <td class="px-4 py-3 text-right">
                                                <form method="POST" action="{{ route('management.master.holidays.destroy', $holiday) }}" class="inline-block">
                                                    @csrf
                                                    @if ($routeUsesMethod('management.master.holidays.destroy', 'DELETE'))
                                                        @method('DELETE')
                                                    @endif
                                                    <x-button type="submit" variant="danger-outline" size="sm">
                                                        {{ __('ui.actions.delete') }}
                                                    </x-button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </x-card>
                @endif
            </div>
        </section>
    </div>
</x-app-layout>
