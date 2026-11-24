@php
    $departmentOptions = $availableDepartments->mapWithKeys(
        static fn ($department): array => [$department->id => "{$department->name} ({$department->code})"]
    )->all();

    $divisionOptions = $availableDivisions->mapWithKeys(
        static fn ($division): array => [$division->id => "{$division->name} ({$division->code})"]
    )->all();

    $deptTargetOptions = $deptTargets->mapWithKeys(
        static fn ($target): array => [$target->id => "{$target->department?->code}: {$target->title}"]
    )->all();

    $periodOptions = collect($periodTypes)->mapWithKeys(
        static fn (\App\Enums\TargetPeriodType $period): array => [$period->value => $period->label()]
    )->all();
@endphp

<x-app-layout title="Target Kerja" :subtitle="$selectedMonthLabel">
    <div
        class="space-y-5"
        x-data="{ showForms: @js($errors->any()) }"
    >
        {{-- Bar Kontrol Atas Ringkas --}}
        <x-card padding="p-4">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <form method="GET" action="{{ route('targets.index') }}" class="grid flex-1 gap-3 sm:grid-cols-3">
                    <x-input id="target-month" name="month" type="month" label="Bulan" :value="$selectedMonth" />

                    <x-select
                        id="target-period"
                        name="period_type"
                        label="Skala"
                        :options="$periodOptions"
                        :value="$selectedPeriod?->value"
                        placeholder="Bulanan & Mingguan"
                    />

                    <div class="flex items-end">
                        <x-button type="submit" variant="primary" size="md" class="w-full sm:w-auto">Tampilkan</x-button>
                    </div>
                </form>

                <div class="flex flex-wrap items-center gap-2">
                    @if ($canCreateDepartmentTarget || $canCreateDivisionTarget)
                        <x-button
                            type="button"
                            variant="checkin"
                            size="md"
                            x-on:click="showForms = !showForms"
                        >
                            <span x-text="showForms ? 'Tutup Form' : '+ Buat Target'">+ Buat Target</span>
                        </x-button>
                    @endif

                    @if (Route::has('timeline.index'))
                        <x-button href="{{ route('timeline.index', ['month' => $selectedMonth]) }}" variant="secondary" size="md">
                            Timeline
                        </x-button>
                    @endif
                </div>
            </div>
        </x-card>

        {{-- Form Buat Target (Buka/Tutup agar halaman tetap bersih) --}}
        @if ($canCreateDepartmentTarget || $canCreateDivisionTarget)
            <div
                x-show="showForms"
                x-cloak
                class="grid gap-4 {{ $canCreateDepartmentTarget && $canCreateDivisionTarget ? 'lg:grid-cols-2' : '' }}"
            >
                @if ($canCreateDepartmentTarget && $availableDepartments->isNotEmpty())
                    <x-card class="space-y-3.5">
                        <h2 class="text-base font-extrabold text-brand-ink">Buat Target Bulanan Departemen</h2>

                        <form method="POST" action="{{ route('targets.department.store') }}" class="space-y-3">
                            @csrf
                            <div class="grid gap-3 sm:grid-cols-2">
                                <x-select name="department_id" label="Departemen" :options="$departmentOptions" :value="$availableDepartments->first()?->id" required />
                                <x-input name="month" type="month" label="Bulan" :value="$selectedMonth" required />
                            </div>

                            <x-input name="title" label="Judul Target" placeholder="Contoh: Digitalisasi Sistem Veloce" required />

                            <div class="grid gap-3 sm:grid-cols-2">
                                <x-input name="target_value" type="number" min="1" label="Sasaran" value="100" required />
                                <x-input name="unit_label" label="Satuan" value="persen" required />
                            </div>

                            <x-button type="submit" variant="primary" size="md">Simpan Target Bulanan</x-button>
                        </form>
                    </x-card>
                @endif

                @if ($canCreateDivisionTarget && $availableDivisions->isNotEmpty())
                    <x-card class="space-y-3.5">
                        <h2 class="text-base font-extrabold text-brand-ink">Buat Target Mingguan / Divisi &amp; Bobot</h2>

                        <form method="POST" action="{{ route('targets.division.store') }}" class="space-y-3">
                            @csrf
                            <div class="grid gap-3 sm:grid-cols-2">
                                <x-select name="division_id" label="Divisi" :options="$divisionOptions" :value="$availableDivisions->first()?->id" required />
                                <x-select name="period_type" label="Periode" :options="$periodOptions" value="weekly" required />
                            </div>

                            @if (! empty($deptTargetOptions))
                                <x-select
                                    name="department_target_id"
                                    label="Induk Target Bulanan"
                                    :options="$deptTargetOptions"
                                    placeholder="-- Pilih Target Bulanan --"
                                />
                            @endif

                            <x-input name="title" label="Judul Target" placeholder="Contoh: Pekan 4 — Integrasi Payroll & Drive PT" required />

                            <div class="grid gap-3 sm:grid-cols-2">
                                <x-input name="start_date" type="date" label="Mulai" :value="$todayWib" required />
                                <x-input name="end_date" type="date" label="Selesai" :value="$todayWib" required />
                            </div>

                            <div class="grid gap-3 sm:grid-cols-3">
                                <x-input name="weight_percent" type="number" min="1" max="100" step="1" label="Bobot (%)" value="25" required />
                                <x-input name="target_value" type="number" min="1" label="Target Poin" value="4" required />
                                <x-input name="unit_label" label="Satuan" value="laporan" required />
                            </div>

                            <x-button type="submit" variant="checkin" size="md">Simpan Target Mingguan</x-button>
                        </form>
                    </x-card>
                @endif
            </div>
        @endif

        {{-- 1. TARGET BULANAN DEPARTEMEN --}}
        <div class="space-y-3">
            <h2 class="text-base font-extrabold text-brand-ink">Target Bulanan ({{ $selectedMonthLabel }})</h2>

            @if ($deptTargets->isEmpty())
                <x-empty-state title="Belum ada target bulanan" />
            @else
                <div class="grid gap-3.5 md:grid-cols-2">
                    @foreach ($deptTargets as $deptTarget)
                        @php
                            $deptProgress = $deptTarget->progressPercent();
                            $directChildren = $deptTarget->resolvedDirectTargets();
                            $allocatedWeight = $deptTarget->allocatedWeight();
                        @endphp
                        <x-card class="space-y-3">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="text-xs font-bold text-brand-navy">{{ $deptTarget->department?->code }} · {{ $deptTarget->department?->name }}</p>
                                    <h3 class="mt-0.5 text-base font-bold text-brand-ink">{{ $deptTarget->title }}</h3>
                                </div>
                                <x-badge :variant="$deptProgress >= 100 ? 'success' : 'navy'">{{ number_format($deptProgress, 0) }}%</x-badge>
                            </div>

                            <div class="h-2.5 w-full overflow-hidden rounded-full bg-surface">
                                <div class="h-full rounded-full bg-brand-navy transition-[width] duration-300" style="width: {{ min(100, $deptProgress) }}%"></div>
                            </div>

                            <div class="flex items-center justify-between text-xs text-muted">
                                <span>{{ $directChildren->count() }} target mingguan/divisi</span>
                                <span>Bobot terpasang: <strong class="text-brand-ink">{{ number_format($allocatedWeight, 0) }}%</strong></span>
                            </div>

                            @if ($directChildren->isNotEmpty())
                                <div class="space-y-1.5 border-t border-border pt-2.5 text-xs">
                                    @foreach ($directChildren as $child)
                                        <div class="flex min-w-0 items-center justify-between gap-2 rounded-xl bg-surface px-3 py-1.5">
                                            <span class="min-w-0 flex-1 truncate font-semibold text-text">{{ $child->title }}</span>
                                            <span class="shrink-0 text-[11px] font-bold text-brand-ink tabular-nums">
                                                {{ number_format((float) $child->weight_percent, 0) }}% bobot · {{ number_format((float) $child->progress_percent, 0) }}%
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </x-card>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- 2. TARGET MINGGUAN & DIVISI --}}
        <div class="space-y-3">
            <h2 class="text-base font-extrabold text-brand-ink">Target Mingguan &amp; Divisi</h2>

            @if ($divisionTargets->isEmpty())
                <x-empty-state title="Belum ada target mingguan pada bulan ini" />
            @else
                <div class="grid gap-3.5 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($divisionTargets as $divTarget)
                        @php
                            $hasParent = $divTarget->parentTarget !== null || $divTarget->departmentTarget !== null;
                            $parentTitle = $divTarget->parentTarget?->title ?? $divTarget->departmentTarget?->title;
                            $contribPercent = $hasParent ? $divTarget->contributionToParentPercent() : null;
                        @endphp
                        <x-card class="flex flex-col justify-between space-y-3">
                            <div class="space-y-1.5">
                                <div class="flex flex-wrap items-center justify-between gap-1.5">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <x-badge :variant="$divTarget->period_type->badgeVariant()">{{ $divTarget->period_type->label() }}</x-badge>
                                        @if ($hasParent)
                                            <x-badge variant="violet">Bobot {{ number_format((float) $divTarget->weight_percent, 0) }}%</x-badge>
                                        @endif
                                    </div>
                                    <x-badge :variant="$divTarget->progress_percent >= 100 ? 'success' : 'neutral'">
                                        {{ number_format((float) $divTarget->progress_percent, 0) }}%
                                    </x-badge>
                                </div>

                                <p class="text-[11px] font-semibold text-muted">
                                    {{ $divTarget->division?->code }} · {{ $divTarget->start_date->translatedFormat('d M') }}–{{ $divTarget->end_date->translatedFormat('d M') }}
                                </p>
                                <h3 class="text-sm font-bold text-brand-ink">{{ $divTarget->title }}</h3>

                                @if ($parentTitle)
                                    <p class="truncate text-[11px] font-medium text-brand-navy">
                                        ↳ {{ $parentTitle }}
                                        @if ($contribPercent !== null)
                                            <span class="font-bold text-success-ink">(+{{ number_format($contribPercent, 1) }}%)</span>
                                        @endif
                                    </p>
                                @endif
                            </div>

                            <div class="space-y-2.5 pt-1">
                                <div class="h-2 w-full overflow-hidden rounded-full bg-surface">
                                    <div class="h-full rounded-full bg-brand-green-strong transition-[width] duration-300" style="width: {{ min(100, (float) $divTarget->progress_percent) }}%"></div>
                                </div>

                                <div class="flex items-center justify-between text-xs text-muted">
                                    <span>{{ $divTarget->approved_value }} / {{ $divTarget->target_value }} {{ $divTarget->unit_label }}</span>
                                    <span class="font-bold text-brand-ink">{{ number_format((float) $divTarget->progress_percent, 1) }}%</span>
                                </div>

                                @if ($canEditWeights && $hasParent)
                                    <form method="POST" action="{{ route('targets.division.weight', $divTarget) }}" class="flex items-center justify-between gap-2 border-t border-border pt-2.5">
                                        @csrf
                                        @method('PATCH')
                                        <label for="weight-{{ $divTarget->id }}" class="text-[11px] font-semibold text-muted">Bobot (%)</label>
                                        <div class="flex items-center gap-1.5">
                                            <input
                                                id="weight-{{ $divTarget->id }}"
                                                name="weight_percent"
                                                type="number"
                                                min="1"
                                                max="100"
                                                step="1"
                                                value="{{ number_format((float) $divTarget->weight_percent, 0, '.', '') }}"
                                                class="veloce-field h-8 w-16 rounded-xl border border-border px-2 text-center text-xs font-bold text-brand-ink"
                                                required
                                            >
                                            <x-button type="submit" variant="secondary" size="sm" class="min-h-8! px-3! py-1! text-xs!">Simpan</x-button>
                                        </div>
                                    </form>
                                @endif
                            </div>
                        </x-card>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
