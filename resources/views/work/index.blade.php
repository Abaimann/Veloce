@php
    $targetOptions = $availableTargets->mapWithKeys(static fn ($target): array => [
        $target->id => sprintf(
            '[%s] %s (%s/%s %s)',
            $target->period_type->label(),
            $target->title,
            $target->approved_value,
            $target->target_value,
            $target->unit_label,
        ),
    ])->all();

    $statusOptions = collect($statuses)->mapWithKeys(
        static fn (\App\Enums\WorkSubmissionStatus $s): array => [$s->value => $s->label()]
    )->all();
@endphp

<x-app-layout title="Kerjaan Harian" subtitle="Upload bukti harian ke Google Drive PT">
    <div class="space-y-5">
        {{-- Status Drive PT Ringkas --}}
        <div x-data="{ showDriveHelp: false }">
            @if (! $driveConfigured)
                <x-card padding="p-3.5" class="border-warning/35 bg-warning-soft/45">
                    <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-warning-ink">
                        <span class="font-bold">Google Drive PT belum terhubung di <code>.env</code></span>
                        <button type="button" x-on:click="showDriveHelp = !showDriveHelp" class="cursor-pointer font-bold underline">
                            <span x-text="showDriveHelp ? 'Tutup' : 'Cara Atur'">Cara Atur</span>
                        </button>
                    </div>
                    <p x-show="showDriveHelp" x-cloak class="mt-2 text-xs text-warning-ink">
                        Isi <code>GOOGLE_DRIVE_ROOT_FOLDER_ID</code> &amp; <code>GOOGLE_DRIVE_CREDENTIALS_PATH</code>, lalu share folder ke email Service Account.
                    </p>
                </x-card>
            @else
                <x-card padding="p-3.5" class="border-brand-green/30 bg-brand-green-soft/35">
                    <p class="text-xs font-bold text-success-ink">
                        Google Drive PT aktif ({{ $driveRootFolderId }})
                    </p>
                </x-card>
            @endif
        </div>

        {{-- Form Upload Bukti Kerja Harian --}}
        <x-card class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-base font-extrabold text-brand-ink">Upload Bukti Kerja Hari Ini</h2>
                <div class="flex items-center gap-2">
                    <x-button href="{{ route('timeline.index') }}" variant="secondary" size="sm">Timeline</x-button>
                    <x-button href="{{ route('targets.index') }}" variant="secondary" size="sm">Target</x-button>
                </div>
            </div>

            @if ($availableTargets->isEmpty())
                <x-empty-state title="Belum ada target mingguan/bulanan aktif" />
            @else
                <form method="POST" action="{{ route('work.store') }}" enctype="multipart/form-data" class="space-y-3.5">
                    @csrf
                    <div class="grid gap-3.5 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="sm:col-span-2">
                            <x-select
                                name="division_target_id"
                                label="Target Mingguan / Bulanan"
                                :options="$targetOptions"
                                placeholder="-- Pilih Target --"
                                required
                            />
                        </div>

                        <x-input name="work_date" type="date" label="Tanggal" :value="$todayWib" required />

                        <div class="sm:col-span-2">
                            <x-input name="title" label="Apa yang dikerjakan hari ini?" placeholder="Contoh: Perbaikan API Payroll & Dokumentasi" required />
                        </div>

                        <x-input name="contribution_value" type="number" min="1" max="100" label="Poin Pencapaian" value="1" required />
                    </div>

                    <x-textarea name="description" label="Catatan (Opsional)" rows="2" placeholder="Ringkasan hasil pekerjaan..." />

                    <div class="space-y-1.5">
                        <label for="work-file" class="block text-sm font-semibold text-text">
                            File Bukti Kerja (Upload Drive PT) <span class="text-danger">*</span>
                        </label>
                        <input
                            id="work-file"
                            name="work_file"
                            type="file"
                            required
                            class="block w-full min-w-0 max-w-full overflow-hidden rounded-2xl border border-border bg-card px-3.5 py-2.5 text-xs text-text sm:px-4 sm:text-sm file:mr-2.5 file:rounded-xl file:border-0 file:bg-brand-navy-soft file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-brand-ink hover:file:bg-brand-navy/15"
                        >
                        @error('work_file')
                            <p class="text-xs font-medium text-danger-ink">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex justify-end">
                        <x-button type="submit" variant="checkin" size="md">
                            Upload ke Drive PT
                        </x-button>
                    </div>
                </form>
            @endif
        </x-card>

        {{-- Konfirmasi Ketua Divisi --}}
        @if ($canReviewTeam)
            <div class="space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-base font-extrabold text-brand-ink">Konfirmasi Tim (Ketua Divisi)</h2>

                    <form method="GET" action="{{ route('work.index') }}" class="flex items-center gap-2">
                        <x-select name="status" :options="$statusOptions" :value="$selectedStatus?->value" placeholder="Semua Status" />
                        <x-button type="submit" variant="secondary" size="sm">Filter</x-button>
                    </form>
                </div>

                @if ($teamSubmissions->isEmpty())
                    <x-empty-state title="Belum ada kiriman kerjaan tim" />
                @else
                    <div class="space-y-2.5">
                        @foreach ($teamSubmissions as $item)
                            <x-card padding="p-4" class="space-y-2.5">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="min-w-0 space-y-1">
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <span class="text-sm font-bold text-brand-ink">{{ $item->employee?->user?->name ?? '-' }}</span>
                                            <x-badge :variant="$item->status->badgeVariant()">{{ $item->status->label() }}</x-badge>
                                            <x-badge variant="neutral">{{ $item->work_date->translatedFormat('d M Y') }}</x-badge>
                                        </div>
                                        <p class="text-sm font-semibold text-text">{{ $item->title }}</p>
                                        <p class="text-xs text-muted">
                                            Target: <strong class="text-text">{{ $item->divisionTarget?->title ?? '-' }}</strong> (+{{ $item->contribution_value }})
                                            @if ($item->drive_web_view_link)
                                                · <a href="{{ $item->drive_web_view_link }}" target="_blank" rel="noopener noreferrer" class="font-bold text-brand-navy underline">Buka Drive PT</a>
                                            @endif
                                        </p>
                                    </div>

                                    @if ($item->status !== \App\Enums\WorkSubmissionStatus::APPROVED)
                                        <div class="flex w-full shrink-0 flex-col gap-2 sm:w-auto sm:flex-row sm:flex-wrap sm:items-center">
                                            <form method="POST" action="{{ route('work.approve', $item) }}">
                                                @csrf
                                                <x-button type="submit" variant="checkin" size="sm" class="w-full sm:w-auto">✓ Cocok &amp; Centang</x-button>
                                            </form>

                                            @if ($item->status === \App\Enums\WorkSubmissionStatus::PENDING_REVIEW)
                                                <form method="POST" action="{{ route('work.revision', $item) }}" class="flex w-full min-w-0 items-center gap-1.5 sm:w-auto">
                                                    @csrf
                                                    <input
                                                        type="text"
                                                        name="review_note"
                                                        required
                                                        placeholder="Catatan revisi..."
                                                        class="min-h-10 w-full min-w-0 flex-1 rounded-xl border border-border bg-card px-3 text-xs text-text sm:w-40 sm:flex-none"
                                                    >
                                                    <x-button type="submit" variant="danger-outline" size="sm" class="shrink-0">Revisi</x-button>
                                                </form>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </x-card>
                        @endforeach
                    </div>

                    @if ($teamSubmissions->hasPages())
                        <div class="pt-1">{{ $teamSubmissions->links() }}</div>
                    @endif
                @endif
            </div>
        @endif

        {{-- Riwayat Upload Saya --}}
        <div class="space-y-3">
            <h2 class="text-base font-extrabold text-brand-ink">Riwayat Upload Saya</h2>

            @if ($mySubmissions->isEmpty())
                <x-empty-state title="Belum ada upload kerjaan" />
            @else
                <div class="grid gap-2.5 md:grid-cols-2">
                    @foreach ($mySubmissions as $mine)
                        <x-card padding="p-4" class="space-y-1.5">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-xs font-bold text-muted">{{ $mine->work_date->translatedFormat('d M Y') }}</span>
                                <x-badge :variant="$mine->status->badgeVariant()">{{ $mine->status->label() }}</x-badge>
                            </div>
                            <p class="text-sm font-bold text-brand-ink">{{ $mine->title }}</p>
                            <p class="truncate text-xs text-muted">{{ $mine->divisionTarget?->title ?? '-' }}</p>
                            @if ($mine->drive_web_view_link)
                                <a href="{{ $mine->drive_web_view_link }}" target="_blank" rel="noopener noreferrer" class="inline-block text-xs font-bold text-brand-navy underline">
                                    Drive PT: {{ $mine->drive_file_name }}
                                </a>
                            @endif
                        </x-card>
                    @endforeach
                </div>

                @if ($mySubmissions->hasPages())
                    <div class="pt-1">{{ $mySubmissions->links() }}</div>
                @endif
            @endif
        </div>
    </div>
</x-app-layout>
