@php
    use App\Enums\EmploymentType;

    $primaryRole = $user->primaryRole();
    $dayNames = [
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
        7 => 'Minggu',
    ];

    $workSchedule = $employee?->workSchedule;
    $workDaysLabel = '-';
    if ($workSchedule !== null) {
        $mappedDays = array_values(array_filter(array_map(
            static fn ($day): ?string => $dayNames[(int) $day] ?? null,
            $workSchedule->work_days ?? [],
        )));
        if (count($mappedDays) > 0) {
            $workDaysLabel = implode(', ', $mappedDays);
        }
    }

    $remainingLeaveDays = $leaveBalance?->remainingDays() ?? 0;

    $passwordActionUrl = Route::has('profile.password') ? route('profile.password') : '#';
    $passwordRoute = app('router')->getRoutes()->getByName('profile.password');
    $passwordFormMethod = 'POST';
    if ($passwordRoute !== null) {
        $routeMethods = $passwordRoute->methods();
        if (! in_array('POST', $routeMethods, true)) {
            if (in_array('PUT', $routeMethods, true)) {
                $passwordFormMethod = 'PUT';
            } elseif (in_array('PATCH', $routeMethods, true)) {
                $passwordFormMethod = 'PATCH';
            }
        }
    }
@endphp

<x-app-layout
    title="Profil Saya"
    subtitle="Informasi akun, detail kepegawaian, dan keamanan kata sandi"
    :pending-approvals-count="$pendingApprovalsCount"
    :unread-notifications-count="$unreadNotificationsCount"
>
    <div class="space-y-4">
        {{-- Informasi Akun --}}
        <x-card class="space-y-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3.5">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-brand-navy text-base font-bold text-on-brand">
                        {{ strtoupper(mb_substr($user->name, 0, 2)) }}
                    </div>
                    <div class="min-w-0">
                        <h2 class="truncate text-base font-bold text-brand-ink sm:text-lg">{{ $user->name }}</h2>
                        <p class="truncate text-xs text-muted">{{ $user->email }}</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-1.5">
                    @if ($primaryRole)
                        <x-badge variant="navy">{{ $primaryRole->label() }}</x-badge>
                    @endif
                    <x-badge :variant="$user->is_active ? 'success' : 'danger'">
                        {{ $user->is_active ? 'Akun Aktif' : 'Akun Nonaktif' }}
                    </x-badge>
                </div>
            </div>

            <dl class="grid grid-cols-1 gap-3 rounded-2xl bg-surface p-4 text-xs sm:grid-cols-3">
                <div>
                    <dt class="text-muted">Nama Lengkap</dt>
                    <dd class="mt-0.5 font-semibold text-text">{{ $user->name }}</dd>
                </div>
                <div>
                    <dt class="text-muted">Email Perusahaan</dt>
                    <dd class="mt-0.5 truncate font-semibold text-text">{{ $user->email }}</dd>
                </div>
                <div>
                    <dt class="text-muted">Masuk Terakhir</dt>
                    <dd class="mt-0.5 font-semibold text-text">
                        @if ($user->last_login_at)
                            {{ $user->last_login_at->setTimezone($timezone)->translatedFormat('d M Y, H:i') }} WIB
                        @else
                            -
                        @endif
                    </dd>
                </div>
            </dl>
        </x-card>

        {{-- Detail Kepegawaian, Jadwal Kerja, dan Saldo Cuti --}}
        @if ($employee !== null)
            <div class="grid gap-4 lg:grid-cols-2">
                <x-card class="space-y-3.5">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-sm font-bold text-brand-ink">Profil Kepegawaian</h3>
                        <x-badge variant="info">{{ $employee->employment_type->label() }}</x-badge>
                    </div>

                    <dl class="grid grid-cols-2 gap-3 rounded-2xl bg-surface p-4 text-xs">
                        <div>
                            <dt class="text-muted">NIP / NIK</dt>
                            <dd class="mt-0.5 font-bold text-brand-ink">{{ $employee->employee_number }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted">Jabatan</dt>
                            <dd class="mt-0.5 font-semibold text-text">{{ $employee->position_title ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted">Departemen</dt>
                            <dd class="mt-0.5 font-semibold text-text">{{ $employee->department?->name ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted">Divisi</dt>
                            <dd class="mt-0.5 font-semibold text-text">{{ $employee->division?->name ?? 'Tanpa Divisi' }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted">Tanggal Bergabung</dt>
                            <dd class="mt-0.5 font-semibold text-text">
                                {{ $employee->join_date?->translatedFormat('d F Y') ?? '-' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted">Atasan Langsung</dt>
                            <dd class="mt-0.5 font-semibold text-text">{{ $employee->supervisor?->user?->name ?? '-' }}</dd>
                        </div>
                    </dl>
                </x-card>

                <x-card class="space-y-3.5">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-sm font-bold text-brand-ink">Jadwal Kerja &amp; Saldo Cuti</h3>
                        <x-badge :variant="$remainingLeaveDays > 0 ? 'success' : 'neutral'">
                            Sisa Cuti: {{ $remainingLeaveDays }} Hari
                        </x-badge>
                    </div>

                    <dl class="grid grid-cols-2 gap-3 rounded-2xl bg-surface p-4 text-xs">
                        <div>
                            <dt class="text-muted">Jadwal Kerja</dt>
                            <dd class="mt-0.5 font-semibold text-text">{{ $workSchedule?->name ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted">Jam Kerja</dt>
                            <dd class="mt-0.5 font-bold tabular-nums text-brand-ink">
                                @if ($workSchedule)
                                    {{ substr((string) $workSchedule->check_in_time, 0, 5) }} – {{ substr((string) $workSchedule->check_out_time, 0, 5) }} WIB
                                @else
                                    -
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted">Toleransi Terlambat</dt>
                            <dd class="mt-0.5 font-semibold text-text">
                                {{ $workSchedule ? $workSchedule->late_tolerance_minutes . ' menit' : '-' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted">Hari Kerja</dt>
                            <dd class="mt-0.5 font-semibold text-text">{{ $workDaysLabel }}</dd>
                        </div>
                        <div class="col-span-2 border-t border-border pt-2.5">
                            <dt class="text-muted">Saldo Cuti Tahunan {{ $currentYear }}</dt>
                            <dd class="mt-0.5 font-semibold text-text">
                                @if ($leaveBalance && $leaveBalance->entitled_days > 0)
                                    <span class="font-bold text-success-ink">{{ $remainingLeaveDays }} hari tersisa</span>
                                    <span class="text-muted">
                                        (hak {{ $leaveBalance->entitled_days }} hari, terpakai {{ $leaveBalance->used_days }} hari)
                                    </span>
                                @elseif ($employee->employment_type === EmploymentType::INTERN)
                                    <span>0 hari (status magang tidak mendapat kuota cuti tahunan)</span>
                                @else
                                    <span>{{ $remainingLeaveDays }} hari</span>
                                @endif
                            </dd>
                        </div>
                    </dl>
                </x-card>
            </div>
        @else
            <x-card>
                <p class="text-sm font-bold text-brand-ink">Akun Sistem Tanpa Profil Karyawan</p>
                <p class="mt-1 text-xs leading-relaxed text-muted">
                    Akun ini beroperasi pada tingkat administrasi atau pelaporan tanpa jadwal kerja dan saldo cuti individu.
                </p>
            </x-card>
        @endif

        {{-- Form Ubah Kata Sandi --}}
        <div class="grid gap-4 lg:grid-cols-3">
            <x-card class="space-y-4 lg:col-span-2">
                <div>
                    <h3 class="text-base font-bold text-brand-ink">Ubah Kata Sandi</h3>
                    <p class="mt-0.5 text-xs text-muted">
                        Gunakan kata sandi baru minimal 8 karakter dengan kombinasi huruf dan angka untuk menjaga keamanan akun.
                    </p>
                </div>

                <form method="POST" action="{{ $passwordActionUrl }}" class="space-y-4">
                    @csrf
                    @if ($passwordFormMethod !== 'POST')
                        @method($passwordFormMethod)
                    @endif

                    <x-input
                        name="current_password"
                        type="password"
                        label="Kata Sandi Saat Ini"
                        placeholder="Masukkan kata sandi saat ini"
                        autocomplete="current-password"
                        required
                    />

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-input
                            name="password"
                            type="password"
                            label="Kata Sandi Baru"
                            placeholder="Minimal 8 karakter"
                            hint="Harus memuat huruf dan angka."
                            autocomplete="new-password"
                            required
                        />

                        <x-input
                            name="password_confirmation"
                            type="password"
                            label="Konfirmasi Kata Sandi Baru"
                            placeholder="Ulangi kata sandi baru"
                            autocomplete="new-password"
                            required
                        />
                    </div>

                    <div class="flex justify-end pt-1">
                        <x-button type="submit" variant="primary" size="md">
                            Simpan Kata Sandi
                        </x-button>
                    </div>
                </form>
            </x-card>

            {{-- Tombol Keluar --}}
            <x-card class="flex flex-col justify-between gap-4">
                <div>
                    <h3 class="text-base font-bold text-brand-ink">Sesi Perangkat</h3>
                    <p class="mt-1 text-xs leading-relaxed text-muted">
                        Akhiri sesi Anda jika menggunakan perangkat bersama atau selesai mengakses sistem Veloce.
                    </p>
                </div>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-button type="submit" variant="danger-outline" size="md" fullWidth>
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        <span>{{ __('ui.actions.logout') }}</span>
                    </x-button>
                </form>
            </x-card>
        </div>
    </div>
</x-app-layout>
