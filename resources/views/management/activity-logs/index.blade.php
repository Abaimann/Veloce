@php
    $eventBadge = static fn (?string $event): array => match ($event) {
        'created' => ['Dibuat', 'success'],
        'updated' => ['Diubah', 'info'],
        'deleted' => ['Dihapus', 'danger'],
        default => [$event ? ucfirst($event) : 'Aktivitas', 'neutral'],
    };

    $eventOptions = [
        'created' => 'Dibuat (Created)',
        'updated' => 'Diubah (Updated)',
        'deleted' => 'Dihapus (Deleted)',
    ];
@endphp

<x-app-layout title="Log Aktivitas CRUD" subtitle="Audit trail seluruh perubahan data oleh admin & pengguna sistem">
    <div class="space-y-4">
        {{-- Filter Audit Log --}}
        <x-card>
            <form method="GET" action="{{ route('management.activity-logs.index') }}" class="space-y-4">
                <div class="grid gap-3.5 sm:grid-cols-2 lg:grid-cols-4">
                    <x-input
                        id="log-search"
                        name="q"
                        type="search"
                        label="Cari Aktivitas"
                        :value="$search"
                        placeholder="Deskripsi atau entitas..."
                    />

                    <x-select
                        id="log-event"
                        name="event"
                        label="Aksi CRUD"
                        :options="$eventOptions"
                        :value="$selectedEvent"
                        placeholder="Semua Aksi"
                    />

                    <x-select
                        id="log-subject"
                        name="subject_type"
                        label="Modul / Tabel"
                        :options="$subjectTypes"
                        :value="$selectedSubject"
                        placeholder="Semua Modul"
                    />

                    <x-select
                        id="log-causer"
                        name="causer_id"
                        label="Pelaku (User)"
                        :options="$causers"
                        :value="$selectedCauserId"
                        placeholder="Semua Pengguna"
                    />
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-border pt-3">
                    <p class="text-xs text-muted">
                        Menampilkan <strong class="font-semibold text-brand-ink">{{ $logs->count() }}</strong> dari <strong class="font-semibold text-brand-ink">{{ $logs->total() }}</strong> catatan log.
                    </p>

                    <div class="flex flex-wrap items-center gap-2">
                        @if ($search !== '' || $selectedEvent !== null || $selectedSubject !== null || $selectedCauserId !== null)
                            <x-button href="{{ route('management.activity-logs.index') }}" variant="secondary" size="sm">
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

        @if ($logs->isEmpty())
            <x-empty-state
                title="Belum ada log aktivitas yang cocok"
                description="Catatan perubahan CRUD pada sistem akan otomatis terekam di halaman ini."
            />
        @else
            {{-- Kartu Mobile Log Aktivitas --}}
            <div class="space-y-2.5 md:hidden">
                @foreach ($logs as $log)
                    @php
                        [$evLabel, $evVariant] = $eventBadge($log->event);
                        $props = $log->properties?->toArray() ?? [];
                        $old = is_array($props['old'] ?? null) ? $props['old'] : [];
                        $attributes = is_array($props['attributes'] ?? null) ? $props['attributes'] : [];
                        $subjectLabel = $log->subject_type ? class_basename($log->subject_type) : '-';
                    @endphp
                    <x-card padding="p-4" class="space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-1.5">
                                <x-badge :variant="$evVariant">{{ $evLabel }}</x-badge>
                                <span class="text-xs font-bold text-brand-ink">{{ $subjectLabel }} #{{ $log->subject_id ?? '-' }}</span>
                            </div>
                            <span class="text-[11px] font-medium text-muted tabular-nums">
                                {{ $log->created_at?->setTimezone($timezone)->translatedFormat('d M H:i') }} WIB
                            </span>
                        </div>

                        <p class="text-xs font-semibold text-text">{{ $log->description }}</p>
                        <p class="text-[11px] text-muted">Pelaku: <strong class="text-text">{{ $log->causer?->name ?? 'Sistem' }}</strong></p>

                        @if (! empty($attributes) || ! empty($old))
                            <details class="rounded-xl border border-border bg-surface/70 px-3 py-2 text-xs">
                                <summary class="cursor-pointer font-semibold text-brand-navy">Detail perubahan</summary>
                                <div class="mt-2 space-y-1.5 break-all font-mono text-[11px] text-text">
                                    @if (! empty($old))
                                        <p class="text-muted"><strong>Sebelum:</strong> {{ json_encode($old, JSON_UNESCAPED_UNICODE) }}</p>
                                    @endif
                                    @if (! empty($attributes))
                                        <p><strong>Sesudah:</strong> {{ json_encode($attributes, JSON_UNESCAPED_UNICODE) }}</p>
                                    @endif
                                </div>
                            </details>
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
                                <th scope="col" class="px-5 py-3.5">Waktu (WIB)</th>
                                <th scope="col" class="px-5 py-3.5">Pelaku</th>
                                <th scope="col" class="px-5 py-3.5">Aksi</th>
                                <th scope="col" class="px-5 py-3.5">Modul &amp; ID</th>
                                <th scope="col" class="px-5 py-3.5">Ringkasan Perubahan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border bg-card">
                            @foreach ($logs as $log)
                                @php
                                    [$evLabel, $evVariant] = $eventBadge($log->event);
                                    $props = $log->properties?->toArray() ?? [];
                                    $old = is_array($props['old'] ?? null) ? $props['old'] : [];
                                    $attributes = is_array($props['attributes'] ?? null) ? $props['attributes'] : [];
                                    $subjectLabel = $log->subject_type ? class_basename($log->subject_type) : '-';
                                @endphp
                                <tr class="transition-colors duration-150 hover:bg-surface/60">
                                    <td class="px-5 py-4 align-top whitespace-nowrap">
                                        <p class="font-semibold text-brand-ink">
                                            {{ $log->created_at?->setTimezone($timezone)->translatedFormat('d M Y') }}
                                        </p>
                                        <p class="text-xs text-muted tabular-nums">
                                            {{ $log->created_at?->setTimezone($timezone)->format('H:i:s') }} WIB
                                        </p>
                                    </td>
                                    <td class="px-5 py-4 align-top">
                                        <p class="font-semibold text-text">{{ $log->causer?->name ?? 'Sistem / Seeder' }}</p>
                                        @if ($log->causer?->email)
                                            <p class="text-xs text-muted">{{ $log->causer->email }}</p>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 align-top">
                                        <x-badge :variant="$evVariant">{{ $evLabel }}</x-badge>
                                    </td>
                                    <td class="px-5 py-4 align-top whitespace-nowrap">
                                        <p class="font-semibold text-brand-ink">{{ $subjectLabel }}</p>
                                        <p class="text-xs text-muted">#{{ $log->subject_id ?? '-' }}</p>
                                    </td>
                                    <td class="px-5 py-4 align-top">
                                        <p class="font-medium text-text">{{ $log->description }}</p>

                                        @if (! empty($attributes) || ! empty($old))
                                            <details class="mt-2 rounded-xl border border-border bg-surface/70 px-3 py-2 text-xs">
                                                <summary class="cursor-pointer font-semibold text-brand-navy">Lihat detail perubahan</summary>
                                                <div class="mt-2 space-y-1.5 font-mono text-[11px] text-text">
                                                    @if (! empty($old))
                                                        <p class="text-muted"><strong>Sebelum:</strong> {{ json_encode($old, JSON_UNESCAPED_UNICODE) }}</p>
                                                    @endif
                                                    @if (! empty($attributes))
                                                        <p><strong>Sesudah:</strong> {{ json_encode($attributes, JSON_UNESCAPED_UNICODE) }}</p>
                                                    @endif
                                                </div>
                                            </details>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>

            @if ($logs->hasPages())
                <div class="pt-1">
                    {{ $logs->links() }}
                </div>
            @endif
        @endif
    </div>
</x-app-layout>
