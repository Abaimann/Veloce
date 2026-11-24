@php
    $approvalsUrl = Route::has('approvals.index') ? route('approvals.index') : '#';
    $teamAttendanceUrl = Route::has('management.attendances.index') ? route('management.attendances.index') : null;
@endphp

<x-app-layout
    title="Dashboard Admin"
    subtitle="Ringkasan Organisasi & Master Data"
    :pending-approvals-count="$pendingHrApprovals"
    :unread-notifications-count="$unreadNotificationsCount"
>
    <div class="space-y-4">
        {{-- Hero Status Server & Identitas Super Admin --}}
        <section
            x-data="serverClock({{ $nowWib->getTimestampMs() }})"
            aria-label="Ringkasan Waktu Server Super Admin"
            class="relative isolate overflow-hidden rounded-3xl bg-gradient-to-br from-brand-navy to-brand-ink p-5 text-on-brand shadow-lg shadow-brand-ink/15 sm:p-6"
        >
            <div class="pointer-events-none absolute -top-20 -right-20 h-56 w-56 rounded-full bg-brand-green/20 blur-3xl" aria-hidden="true"></div>
            <div class="relative flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs font-medium text-on-brand/80">Kontrol Organisasi &amp; Master Data</p>
                    <h2 class="mt-0.5 truncate text-xl font-bold tracking-tight text-on-brand sm:text-2xl">{{ $user->name }}</h2>
                    <p class="mt-0.5 text-xs text-on-brand/80">{{ __('ui.company_name') }}</p>
                </div>

                <span class="rounded-full bg-on-brand/15 px-3 py-1 text-[11px] font-semibold text-on-brand ring-1 ring-on-brand/15">
                    Super Admin
                </span>
            </div>

            <div class="relative mt-5 border-t border-on-brand/15 pt-4">
                <p class="text-3xl font-bold tracking-tight text-on-brand tabular-nums sm:text-4xl" x-text="timeText">
                    {{ $nowWib->format('H:i:s') }} WIB
                </p>
                <p class="mt-1 text-xs text-on-brand/80" x-text="dateText">
                    {{ $nowWib->translatedFormat('l, d F Y') }}
                </p>
            </div>
        </section>

        {{-- Banner Persetujuan Pengajuan HR Manager --}}
        @if ($pendingHrApprovals > 0)
            <div class="rounded-2xl border border-brand-green/35 bg-brand-green-soft/45 p-4 shadow-xs sm:p-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-start gap-3">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-brand-green-strong text-on-brand">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-sm font-bold text-success-ink">Persetujuan Pengajuan HR Manager</h3>
                                <x-badge variant="success">{{ $pendingHrApprovals }} Menunggu</x-badge>
                            </div>
                            <p class="mt-0.5 text-xs leading-relaxed text-text">
                                Terdapat {{ $pendingHrApprovals }} pengajuan cuti/izin/WFH dari HR Manager yang membutuhkan persetujuan Anda.
                            </p>
                        </div>
                    </div>

                    <x-button href="{{ $approvalsUrl }}" variant="checkin" size="sm" class="shrink-0">
                        Tinjau Persetujuan
                    </x-button>
                </div>
            </div>
        @endif

        {{-- Kartu Statistik Organisasi & Master Data --}}
        <section aria-labelledby="admin-stats-heading" class="space-y-2.5">
            <h3 id="admin-stats-heading" class="text-sm font-bold text-brand-ink">
                Statistik Organisasi &amp; Konfigurasi Aktif
            </h3>

            <div class="grid grid-cols-2 gap-3 lg:grid-cols-3">
                <x-card padding="p-4">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs font-semibold text-muted">Karyawan Aktif</p>
                        <x-badge variant="success">Aktif</x-badge>
                    </div>
                    <p class="mt-2 text-2xl font-bold tabular-nums text-brand-ink">{{ $activeEmployeesCount }}</p>
                    <p class="mt-1 text-[11px] text-muted">Terdaftar dalam struktur organisasi</p>
                </x-card>

                <x-card padding="p-4">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs font-semibold text-muted">Menunggu Aktivasi</p>
                        <x-badge :variant="$pendingInvitationsCount > 0 ? 'warning' : 'neutral'">
                            {{ $pendingInvitationsCount > 0 ? 'Undangan' : 'Lengkap' }}
                        </x-badge>
                    </div>
                    <p class="mt-2 text-2xl font-bold tabular-nums {{ $pendingInvitationsCount > 0 ? 'text-warning-ink' : 'text-brand-ink' }}">
                        {{ $pendingInvitationsCount }}
                    </p>
                    <p class="mt-1 text-[11px] text-muted">Akun belum mengatur kata sandi</p>
                </x-card>

                <x-card padding="p-4">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs font-semibold text-muted">Departemen Aktif</p>
                        <x-badge variant="navy">Organisasi</x-badge>
                    </div>
                    <p class="mt-2 text-2xl font-bold tabular-nums text-brand-ink">{{ $activeDepartmentsCount }}</p>
                    <p class="mt-1 text-[11px] text-muted">{{ $activeDivisionsCount }} divisi aktif terdaftar</p>
                </x-card>

                <x-card padding="p-4">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs font-semibold text-muted">Divisi Aktif</p>
                        <x-badge variant="navy">Unit</x-badge>
                    </div>
                    <p class="mt-2 text-2xl font-bold tabular-nums text-brand-ink">{{ $activeDivisionsCount }}</p>
                    <p class="mt-1 text-[11px] text-muted">Sub-unit di bawah departemen</p>
                </x-card>

                <x-card padding="p-4">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs font-semibold text-muted">Lokasi Kantor Aktif</p>
                        <x-badge variant="info">Geofence</x-badge>
                    </div>
                    <p class="mt-2 text-2xl font-bold tabular-nums text-brand-ink">{{ $activeOfficesCount }}</p>
                    <p class="mt-1 text-[11px] text-muted">Titik radius absensi GPS</p>
                </x-card>

                <x-card padding="p-4">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs font-semibold text-muted">Jadwal Kerja Aktif</p>
                        <x-badge variant="violet">Jam Kerja</x-badge>
                    </div>
                    <p class="mt-2 text-2xl font-bold tabular-nums text-brand-ink">{{ $activeSchedulesCount }}</p>
                    <p class="mt-1 text-[11px] text-muted">Aturan jam masuk &amp; toleransi</p>
                </x-card>
            </div>
        </section>

        {{-- Tautan Cepat Manajemen --}}
        <section aria-labelledby="admin-shortcuts-heading" class="space-y-2.5">
            <h3 id="admin-shortcuts-heading" class="text-sm font-bold text-brand-ink">
                Tautan Cepat Administrasi
            </h3>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <x-card class="flex flex-col justify-between gap-3">
                    <div>
                        <p class="text-sm font-bold text-brand-ink">Master Data</p>
                        <p class="mt-0.5 text-xs leading-relaxed text-muted">
                            Kelola departemen, divisi, jadwal kerja, lokasi geofence kantor, dan hari libur nasional.
                        </p>
                    </div>
                    <x-button href="{{ route('management.master.index') }}" variant="primary" size="sm" fullWidth>
                        Kelola Master Data
                    </x-button>
                </x-card>

                <x-card class="flex flex-col justify-between gap-3">
                    <div>
                        <p class="text-sm font-bold text-brand-ink">Manajemen Karyawan</p>
                        <p class="mt-0.5 text-xs leading-relaxed text-muted">
                            Lihat daftar akun karyawan, kirim ulang undangan aktivasi, atau tambah karyawan baru.
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <x-button href="{{ route('management.employees.index') }}" variant="secondary" size="sm" class="flex-1">
                            Daftar
                        </x-button>
                        <x-button href="{{ route('management.employees.create') }}" variant="checkin" size="sm" class="flex-1">
                            Tambah
                        </x-button>
                    </div>
                </x-card>

                <x-card class="flex flex-col justify-between gap-3">
                    <div>
                        <p class="text-sm font-bold text-brand-ink">Monitoring &amp; Persetujuan</p>
                        <p class="mt-0.5 text-xs leading-relaxed text-muted">
                            Pantau rekap absensi seluruh perusahaan dan proses pengajuan HR Manager.
                        </p>
                    </div>
                    <div class="flex gap-2">
                        @if ($teamAttendanceUrl)
                            <x-button href="{{ $teamAttendanceUrl }}" variant="secondary" size="sm" class="flex-1">
                                Rekap Absensi
                            </x-button>
                        @endif
                        <x-button href="{{ $approvalsUrl }}" variant="secondary" size="sm" class="flex-1">
                            Persetujuan ({{ $pendingHrApprovals }})
                        </x-button>
                    </div>
                </x-card>
            </div>
        </section>
    </div>
</x-app-layout>
