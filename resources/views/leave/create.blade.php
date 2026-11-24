@php
    $remainingDays = $leaveBalance?->remainingDays() ?? 0;
    $entitledDays = $leaveBalance?->entitled_days ?? 0;

    $initialTypeId = (string) old('leave_type_id', (string) ($leaveTypes->first()?->id ?? ''));
    $initialStartDate = (string) old('start_date', $todayDate);
    $initialEndDate = (string) old('end_date', $todayDate);

    $leaveTypesPayload = $leaveTypes->mapWithKeys(static fn ($type): array => [
        (string) $type->id => [
            'id' => $type->id,
            'code' => $type->code,
            'name' => $type->name,
            'is_paid' => (bool) $type->is_paid,
            'requires_attachment' => (bool) $type->requires_attachment,
            'deducts_balance' => (bool) $type->deducts_balance,
            'hr_approval_after_days' => $type->hr_approval_after_days,
        ],
    ])->all();
@endphp

<x-app-layout title="Ajukan Izin / Cuti" subtitle="Pilih jenis pengajuan, rentang tanggal kerja, dan lampiran pendukung">
    <div class="space-y-4">
        <div class="flex items-center justify-between gap-3">
            <x-button href="{{ route('leave.index') }}" variant="ghost" size="sm">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
                <span>Kembali ke Daftar Izin</span>
            </x-button>
        </div>

        @if ($errors->any())
            <div class="rounded-2xl border border-danger/30 bg-danger-soft p-4 text-xs text-danger-ink" role="alert">
                <p class="font-bold">Pengajuan belum dapat diproses</p>
                <ul class="mt-1.5 list-disc space-y-1 pl-4">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form
            method="POST"
            action="{{ route('leave.store') }}"
            enctype="multipart/form-data"
            x-data="{
                selectedTypeId: @js($initialTypeId),
                startDate: @js($initialStartDate),
                endDate: @js($initialEndDate),
                hasInteracted: false,
                types: @js($leaveTypesPayload),
                previews: @js($approvalPreviews),
                workDays: @js($workDays),
                holidays: @js($holidayDates),
                remainingBalance: @js($remainingDays),
                get selectedType() {
                    return this.types[String(this.selectedTypeId)] ?? null;
                },
                get crossesYear() {
                    if (!this.startDate || !this.endDate) {
                        return false;
                    }
                    return this.startDate.slice(0, 4) !== this.endDate.slice(0, 4);
                },
                get estimatedWorkDays() {
                    if (!this.startDate || !this.endDate || this.endDate < this.startDate) {
                        return 0;
                    }
                    const [sy, sm, sd] = this.startDate.split('-').map(Number);
                    const [ey, em, ed] = this.endDate.split('-').map(Number);
                    const start = new Date(Date.UTC(sy, sm - 1, sd));
                    const end = new Date(Date.UTC(ey, em - 1, ed));
                    const holidaySet = new Set(this.holidays);
                    let count = 0;

                    for (let cursor = new Date(start); cursor <= end; cursor.setUTCDate(cursor.getUTCDate() + 1)) {
                        const isoDay = cursor.getUTCDay() === 0 ? 7 : cursor.getUTCDay();
                        const yyyy = cursor.getUTCFullYear();
                        const mm = String(cursor.getUTCMonth() + 1).padStart(2, '0');
                        const dd = String(cursor.getUTCDate()).padStart(2, '0');
                        const isoDate = `${yyyy}-${mm}-${dd}`;

                        if (this.workDays.includes(isoDay) && !holidaySet.has(isoDate)) {
                            count++;
                        }
                    }

                    return count;
                },
                get activePreviewSteps() {
                    const preview = this.previews[String(this.selectedTypeId)] ?? null;
                    if (!preview) {
                        return [];
                    }
                    if (preview.hr_after_days !== null && this.estimatedWorkDays > preview.hr_after_days) {
                        return preview.extended;
                    }
                    return preview.base;
                },
                selectType(id) {
                    this.selectedTypeId = String(id);
                    this.hasInteracted = true;
                },
                syncDates() {
                    this.hasInteracted = true;
                    if (this.startDate && (!this.endDate || this.endDate < this.startDate)) {
                        this.endDate = this.startDate;
                    }
                }
            }"
            class="space-y-5"
        >
            @csrf

            {{-- Langkah 1: Kartu Radio Interaktif Jenis Izin / Cuti --}}
            <x-card class="space-y-3.5">
                <div>
                    <h2 class="text-base font-bold text-brand-ink">1. Pilih Jenis Pengajuan</h2>
                    <p class="mt-0.5 text-xs text-muted">Ketuk salah satu kartu sesuai kebutuhan Anda.</p>
                </div>

                @if ($leaveTypes->isEmpty())
                    <div class="rounded-2xl border border-warning/30 bg-warning-soft p-4 text-xs text-warning-ink">
                        Belum ada jenis pengajuan aktif yang tersedia. Hubungi HR Manager.
                    </div>
                @else
                    <div class="grid gap-3 sm:grid-cols-2" role="radiogroup" aria-label="Jenis pengajuan izin atau cuti">
                        @foreach ($leaveTypes as $type)
                            @php
                                $typeId = (string) $type->id;
                            @endphp
                            <label
                                for="leave_type_{{ $type->id }}"
                                x-on:click="selectType('{{ $type->id }}')"
                                :class="String(selectedTypeId) === '{{ $typeId }}'
                                    ? 'border-brand-navy bg-brand-navy-soft/50 ring-2 ring-brand-navy/20'
                                    : 'border-border bg-card hover:border-brand-navy/40'"
                                class="relative flex cursor-pointer flex-col justify-between gap-2.5 rounded-2xl border p-4 transition-[border-color,background-color,box-shadow] duration-150"
                            >
                                <input
                                    id="leave_type_{{ $type->id }}"
                                    type="radio"
                                    name="leave_type_id"
                                    value="{{ $type->id }}"
                                    x-model="selectedTypeId"
                                    x-on:change="hasInteracted = true"
                                    @checked($initialTypeId === $typeId)
                                    class="sr-only"
                                >

                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="text-sm font-bold text-brand-ink">{{ $type->name }}</p>
                                        <p class="mt-0.5 text-xs text-muted">
                                            {{ $type->is_paid ? 'Status berbayar' : 'Tidak berbayar (unpaid)' }}
                                        </p>
                                    </div>

                                    <span
                                        class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full border transition-colors"
                                        :class="String(selectedTypeId) === '{{ $typeId }}'
                                            ? 'border-brand-navy bg-brand-navy text-on-brand'
                                            : 'border-border bg-surface text-transparent'"
                                    >
                                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </span>
                                </div>

                                <div class="flex flex-wrap items-center gap-1.5">
                                    @if ($type->deducts_balance)
                                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $remainingDays > 0 ? 'bg-success-soft text-success-ink' : 'bg-danger-soft text-danger-ink' }}">
                                            Sisa saldo: {{ $remainingDays }} / {{ $entitledDays }} hari
                                        </span>
                                    @endif

                                    @if ($type->requires_attachment)
                                        <span class="inline-flex items-center rounded-full bg-warning-soft px-2.5 py-1 text-[11px] font-semibold text-warning-ink">
                                            Wajib lampiran
                                        </span>
                                    @endif

                                    @if ($type->hr_approval_after_days !== null)
                                        <span class="inline-flex items-center rounded-full bg-info-soft px-2.5 py-1 text-[11px] font-semibold text-info-ink">
                                            Persetujuan HR bila &gt; {{ $type->hr_approval_after_days }} hari
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-surface px-2.5 py-1 text-[11px] font-medium text-muted">
                                            Tanpa eskalasi HR tambahan
                                        </span>
                                    @endif
                                </div>
                            </label>
                        @endforeach
                    </div>
                @endif

                @error('leave_type_id')
                    <p class="text-xs font-medium text-danger-ink">{{ $message }}</p>
                @enderror
            </x-card>

            {{-- Langkah 2: Rentang Tanggal, Lampiran & Alasan --}}
            <x-card class="space-y-4">
                <div>
                    <h2 class="text-base font-bold text-brand-ink">2. Rentang Tanggal &amp; Keterangan</h2>
                    <p class="mt-0.5 text-xs text-muted">Akhir pekan dan hari libur nasional otomatis tidak dihitung.</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-input
                        name="start_date"
                        type="date"
                        label="Tanggal Mulai"
                        :value="$initialStartDate"
                        x-model="startDate"
                        x-on:change="syncDates()"
                        required
                    />

                    <x-input
                        name="end_date"
                        type="date"
                        label="Tanggal Selesai"
                        :value="$initialEndDate"
                        x-model="endDate"
                        x-on:change="hasInteracted = true"
                        x-bind:min="startDate"
                        required
                    />
                </div>

                {{-- Ringkasan Estimasi Hari Kerja --}}
                <div class="rounded-2xl border border-border bg-surface px-4 py-3 text-xs">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="font-medium text-muted">Estimasi hari kerja efektif</span>
                        <span class="font-bold tabular-nums text-brand-ink" x-text="estimatedWorkDays + ' hari kerja'">
                            1 hari kerja
                        </span>
                    </div>

                    <p
                        x-show="crossesYear"
                        x-cloak
                        class="mt-2 font-medium text-danger-ink"
                    >
                        Pengajuan tidak diperbolehkan melintasi pergantian tahun. Ajukan terpisah per tahun.
                    </p>

                    <p
                        x-show="!crossesYear && estimatedWorkDays === 0"
                        x-cloak
                        class="mt-2 font-medium text-warning-ink"
                    >
                        Rentang tanggal ini hanya mencakup akhir pekan atau hari libur nasional (0 hari kerja).
                    </p>

                    <p
                        x-show="selectedType && selectedType.deducts_balance && estimatedWorkDays > remainingBalance"
                        x-cloak
                        class="mt-2 font-medium text-danger-ink"
                        x-text="'Saldo cuti tahunan tersisa ' + remainingBalance + ' hari (dibutuhkan ' + estimatedWorkDays + ' hari).'"
                    ></p>
                </div>

                <div class="space-y-1.5">
                    <label for="attachment" class="block text-sm font-semibold text-text">
                        <span>Lampiran Pendukung</span>
                        <span
                            x-show="selectedType && selectedType.requires_attachment"
                            x-cloak
                            class="text-danger"
                            aria-hidden="true"
                        >*</span>
                        <span
                            x-show="!selectedType || !selectedType.requires_attachment"
                            class="text-xs font-normal text-muted"
                        >(Opsional)</span>
                    </label>

                    <input
                        id="attachment"
                        name="attachment"
                        type="file"
                        accept=".jpg,.jpeg,.png,.webp,.pdf"
                        x-bind:required="Boolean(selectedType && selectedType.requires_attachment)"
                        class="block w-full min-h-11 rounded-2xl border border-border bg-card px-4 py-2.5 text-sm text-text file:mr-3 file:rounded-xl file:border-0 file:bg-brand-navy-soft file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-brand-ink hover:file:bg-brand-navy-soft/80"
                    >

                    <p
                        x-show="selectedType && selectedType.requires_attachment"
                        x-cloak
                        class="text-xs font-medium text-warning-ink"
                    >
                        Jenis pengajuan ini wajib melampirkan surat dokter atau dokumen pendukung (JPG, PNG, WEBP, atau PDF maks. 5 MB).
                    </p>
                    <p
                        x-show="!selectedType || !selectedType.requires_attachment"
                        class="text-xs text-muted"
                    >
                        Format yang didukung: JPG, JPEG, PNG, WEBP, atau PDF maksimal 5 MB.
                    </p>

                    @error('attachment')
                        <p class="text-xs font-medium text-danger-ink">{{ $message }}</p>
                    @enderror
                </div>

                <x-textarea
                    name="reason"
                    label="Alasan Pengajuan"
                    rows="4"
                    placeholder="Jelaskan keperluan izin, cuti, sakit, atau rencana kerja WFH (minimal 5 karakter)..."
                    required
                />
            </x-card>

            {{-- Langkah 3: Pratinjau Alur Persetujuan Vertikal --}}
            <x-card class="space-y-3.5">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <h2 class="text-base font-bold text-brand-ink">3. Pratinjau Alur Persetujuan</h2>
                        <p class="mt-0.5 text-xs text-muted">
                            Menyesuaikan jabatan Anda, jenis pengajuan, serta durasi hari kerja.
                        </p>
                    </div>
                    <template x-if="selectedType && selectedType.hr_approval_after_days !== null && estimatedWorkDays > selectedType.hr_approval_after_days">
                        <x-badge variant="info">Termasuk Persetujuan HR</x-badge>
                    </template>
                </div>

                {{-- Render awal dari server agar akurat saat muat awal --}}
                <div x-show="!hasInteracted">
                    @if (empty($defaultPreviewSteps))
                        <div class="rounded-2xl border border-dashed border-border bg-surface px-4 py-5 text-center text-xs text-muted">
                            Tidak ada tahapan persetujuan yang dapat ditampilkan.
                        </div>
                    @else
                        <ol class="space-y-0" aria-label="Pratinjau Alur Persetujuan">
                            @foreach ($defaultPreviewSteps as $index => $step)
                                @php
                                    $isLast = $index === count($defaultPreviewSteps) - 1;
                                @endphp
                                <li class="relative flex gap-3.5 {{ $isLast ? '' : 'pb-4' }}">
                                    @if (! $isLast)
                                        <span aria-hidden="true" class="absolute top-8 bottom-0 left-4 -ml-px w-0.5 bg-border"></span>
                                    @endif
                                    <div class="relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-warning/30 bg-warning-soft text-xs font-bold text-warning-ink">
                                        {{ $step['step'] }}
                                    </div>
                                    <div class="min-w-0 flex-1 rounded-2xl border border-border bg-surface px-3.5 py-2.5">
                                        <p class="text-xs font-bold text-brand-ink">
                                            Tahap {{ $step['step'] }} · {{ $step['label'] }}
                                        </p>
                                        <p class="mt-0.5 text-[11px] text-muted">
                                            Menunggu persetujuan {{ $step['label'] }}
                                        </p>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </div>

                {{-- Render reaktif saat pengguna mengganti jenis izin atau tanggal --}}
                <div x-show="hasInteracted" x-cloak>
                    <template x-if="activePreviewSteps.length === 0">
                        <div class="rounded-2xl border border-dashed border-border bg-surface px-4 py-5 text-center text-xs text-muted">
                            Tidak ada tahapan persetujuan yang tersedia.
                        </div>
                    </template>

                    <ol class="space-y-0" aria-label="Pratinjau Alur Persetujuan Dinamis">
                        <template x-for="(step, idx) in activePreviewSteps" :key="step.step + '-' + step.role">
                            <li class="relative flex gap-3.5" :class="idx === activePreviewSteps.length - 1 ? '' : 'pb-4'">
                                <template x-if="idx !== activePreviewSteps.length - 1">
                                    <span aria-hidden="true" class="absolute top-8 bottom-0 left-4 -ml-px w-0.5 bg-border"></span>
                                </template>
                                <div
                                    class="relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-warning/30 bg-warning-soft text-xs font-bold text-warning-ink"
                                    x-text="step.step"
                                ></div>
                                <div class="min-w-0 flex-1 rounded-2xl border border-border bg-surface px-3.5 py-2.5">
                                    <p class="text-xs font-bold text-brand-ink" x-text="'Tahap ' + step.step + ' · ' + step.label"></p>
                                    <p class="mt-0.5 text-[11px] text-muted" x-text="'Menunggu persetujuan ' + step.label"></p>
                                </div>
                            </li>
                        </template>
                    </ol>
                </div>
            </x-card>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <x-button href="{{ route('leave.index') }}" variant="secondary">
                    {{ __('ui.actions.cancel') }}
                </x-button>
                <x-button type="submit" variant="checkin" size="lg">
                    Kirim Pengajuan
                </x-button>
            </div>
        </form>
    </div>
</x-app-layout>
