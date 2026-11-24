@php
    $periodOptions = collect([
        \App\Enums\TargetPeriodType::MONTHLY,
        \App\Enums\TargetPeriodType::WEEKLY,
    ])->mapWithKeys(
        static fn (\App\Enums\TargetPeriodType $period): array => [$period->value => $period->label()]
    )->all();

    $departmentOptions = $availableDepartments->mapWithKeys(
        static fn ($dept): array => [$dept->id => "{$dept->code} — {$dept->name}"]
    )->all();

    $divisionOptions = $availableDivisions->mapWithKeys(
        static fn ($div): array => [$div->id => "{$div->code} — {$div->name}"]
    )->all();

    $employeeOptions = $visibleEmployees->mapWithKeys(static fn ($emp): array => [
        $emp->id => sprintf('%s (%s)', $emp->user?->name ?? '-', $emp->employee_number),
    ])->all();

    $ringCircumference = 138.23;

    $gaugeCards = [
        [
            'key' => 'monthly',
            'label' => 'Bulanan',
            'count' => $summary['monthly_count'],
            'percent' => (float) $summary['monthly_avg_progress'],
            'strokeClass' => 'stroke-on-brand',
            'dotClass' => 'bg-on-brand',
            'unit' => 'target',
        ],
        [
            'key' => 'weekly',
            'label' => 'Mingguan',
            'count' => $summary['weekly_count'],
            'percent' => (float) $summary['weekly_avg_progress'],
            'strokeClass' => 'stroke-violet',
            'dotClass' => 'bg-violet',
            'unit' => 'sprint',
        ],
        [
            'key' => 'daily',
            'label' => 'Bukti Harian',
            'count' => $summary['daily_count'],
            'percent' => (float) $summary['daily_avg_progress'],
            'strokeClass' => 'stroke-brand-green',
            'dotClass' => 'bg-brand-green',
            'unit' => 'upload',
        ],
    ];

    $ganttSections = [
        [
            'key' => 'monthly',
            'number' => '01',
            'heading' => 'Target Bulanan',
            'badge' => 'Bulanan',
            'badgeVariant' => 'navy',
            'tierClass' => 'timeline-tier-monthly',
            'fillClass' => 'timeline-fill-monthly',
            'numBgClass' => 'bg-brand-navy-soft text-brand-ink',
            'avgProgress' => (float) $summary['monthly_avg_progress'],
            'items' => $monthlyTracks,
            'emptyText' => 'Belum ada target bulanan bulan ini.',
        ],
        [
            'key' => 'weekly',
            'number' => '02',
            'heading' => 'Target Mingguan',
            'badge' => 'Mingguan',
            'badgeVariant' => 'violet',
            'tierClass' => 'timeline-tier-weekly',
            'fillClass' => 'timeline-fill-weekly',
            'numBgClass' => 'bg-violet-soft text-violet-ink',
            'avgProgress' => (float) $summary['weekly_avg_progress'],
            'items' => $weeklyTracks,
            'emptyText' => 'Belum ada target mingguan bulan ini.',
        ],
    ];

    $initialActiveTier = $selectedPeriod?->value ?? 'all';
@endphp

<x-app-layout title="Timeline" :subtitle="$selectedMonthLabel">
    <div
        class="space-y-5"
        x-data="{
            activeTier: @js($initialActiveTier),
            showFilters: @js(request()->hasAny(['department_id', 'division_id', 'period_type', 'employee_id', 'date'])),
        }"
    >
        {{-- HERO RADAR RINGKAS --}}
        <section class="timeline-hero-blueprint rounded-3xl p-5 text-on-brand sm:p-6">
            <div class="relative z-10 grid gap-5 lg:grid-cols-12 lg:items-center">
                <div class="space-y-4 lg:col-span-7">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center gap-2 rounded-full border border-on-brand/20 bg-on-brand/10 px-3 py-1 text-[11px] font-bold tracking-wider uppercase text-on-brand">
                            <span class="timeline-beacon-dot h-2 w-2 rounded-full bg-brand-green"></span>
                            Timeline Kerja
                        </span>
                        <span class="rounded-full border border-on-brand/15 bg-on-brand/5 px-3 py-1 text-[11px] font-semibold text-on-brand/85">
                            {{ $selectedMonthLabel }}
                        </span>
                    </div>

                    <h2 class="text-2xl font-extrabold tracking-tight text-on-brand sm:text-3xl">
                        Bulanan → Mingguan → Bukti Harian
                    </h2>

                    <div class="flex flex-wrap items-center gap-2">
                        <div class="inline-flex flex-wrap items-center gap-1 rounded-2xl border border-on-brand/15 bg-brand-ink/45 p-1">
                            @foreach (['all' => 'Semua', 'monthly' => 'Bulanan', 'weekly' => 'Mingguan', 'daily' => 'Bukti Harian'] as $tierKey => $tierLabel)
                                <button
                                    type="button"
                                    x-on:click="activeTier = '{{ $tierKey }}'"
                                    x-bind:class="activeTier === '{{ $tierKey }}'
                                        ? 'bg-card text-brand-ink shadow-xs'
                                        : 'text-on-brand/80 hover:bg-on-brand/10 hover:text-on-brand'"
                                    class="cursor-pointer rounded-xl px-3 py-1.5 text-xs font-bold transition-all"
                                >
                                    {{ $tierLabel }}
                                </button>
                            @endforeach
                        </div>

                        <button
                            type="button"
                            x-on:click="showFilters = !showFilters"
                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-on-brand/20 bg-on-brand/10 px-3 py-2 text-xs font-semibold text-on-brand hover:bg-on-brand/20"
                        >
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4h18M6 12h12M10 20h4"/></svg>
                            Filter
                        </button>
                    </div>

                    <div class="flex flex-wrap items-center gap-2.5">
                        @if (Route::has('targets.index'))
                            <a
                                href="{{ route('targets.index', ['month' => $selectedMonth]) }}"
                                class="inline-flex items-center gap-1.5 rounded-2xl bg-card px-4 py-2 text-xs font-bold text-brand-ink shadow-xs hover:-translate-y-0.5 transition-transform"
                            >
                                {{ $canManageTargets ? '+ Atur Target & Bobot' : 'Lihat Target' }}
                            </a>
                        @endif

                        @if ($canUploadWork && Route::has('work.index'))
                            <a
                                href="{{ route('work.index') }}"
                                class="inline-flex items-center gap-1.5 rounded-2xl bg-brand-green-strong px-4 py-2 text-xs font-bold text-on-brand shadow-xs hover:-translate-y-0.5 transition-transform"
                            >
                                {{ $canReviewWork ? 'Upload / Centang Drive PT' : 'Upload Drive PT' }}
                            </a>
                        @endif
                    </div>
                </div>

                {{-- 3 RADIAL PROGRESS DIALS --}}
                <div class="grid grid-cols-3 gap-2.5 lg:col-span-5">
                    @foreach ($gaugeCards as $gauge)
                        @php
                            $clamped = min(100, max(0, $gauge['percent']));
                            $dashOffset = round($ringCircumference * (1 - ($clamped / 100)), 2);
                        @endphp
                        <button
                            type="button"
                            x-on:click="activeTier = activeTier === '{{ $gauge['key'] }}' ? 'all' : '{{ $gauge['key'] }}'"
                            class="timeline-glass-tile flex cursor-pointer flex-col items-center rounded-2xl p-3 text-center"
                        >
                            <div class="relative flex h-14 w-14 items-center justify-center sm:h-16 sm:w-16">
                                <svg class="h-full w-full -rotate-90" viewBox="0 0 56 56">
                                    <circle cx="28" cy="28" r="22" fill="none" stroke-width="5" class="timeline-ring-track" />
                                    <circle
                                        cx="28"
                                        cy="28"
                                        r="22"
                                        fill="none"
                                        stroke-width="5"
                                        stroke-linecap="round"
                                        stroke-dasharray="{{ $ringCircumference }}"
                                        stroke-dashoffset="{{ $dashOffset }}"
                                        class="timeline-ring-progress {{ $gauge['strokeClass'] }}"
                                    />
                                </svg>
                                <span class="absolute text-xs font-extrabold text-on-brand tabular-nums">
                                    {{ number_format($gauge['percent'], 0) }}%
                                </span>
                            </div>

                            <div class="mt-1.5 flex items-center gap-1">
                                <span class="h-1.5 w-1.5 rounded-full {{ $gauge['dotClass'] }}"></span>
                                <span class="text-[11px] font-bold text-on-brand">{{ $gauge['label'] }}</span>
                            </div>
                            <span class="text-[10px] text-on-brand/70 tabular-nums">{{ $gauge['count'] }} {{ $gauge['unit'] }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- FILTER COMPACT --}}
        <div x-show="showFilters" x-cloak>
            <x-card>
                <form method="GET" action="{{ route('timeline.index') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    <x-input id="timeline-month" name="month" type="month" label="Bulan" :value="$selectedMonth" />

                    @if ($canFilterDepartment)
                        <x-select id="timeline-department" name="department_id" label="Departemen" :options="$departmentOptions" :value="$selectedDepartmentId" placeholder="Semua Departemen" />
                    @endif

                    @if ($canFilterDivision)
                        <x-select id="timeline-division" name="division_id" label="Divisi" :options="$divisionOptions" :value="$selectedDivisionId" placeholder="Semua Divisi" />
                    @endif

                    <x-input id="timeline-date" name="date" type="date" label="Tanggal Log" :value="$selectedDate" />

                    @if ($canSelectEmployee)
                        <x-select id="timeline-employee" name="employee_id" label="Karyawan (Log)" :options="$employeeOptions" :value="$selectedEmployee?->id" />
                    @endif

                    <div class="flex items-end gap-2">
                        <x-button type="submit" variant="primary" size="md" class="flex-1">Terapkan</x-button>
                        <x-button href="{{ route('timeline.index') }}" variant="secondary" size="md">Reset</x-button>
                    </div>
                </form>
            </x-card>
        </div>

        {{-- 01 & 02: TARGET BULANAN -> TARGET MINGGUAN (GANTT PEKAN 1-5) --}}
        @foreach ($ganttSections as $section)
            <section
                x-show="activeTier === 'all' || activeTier === '{{ $section['key'] }}'"
                class="timeline-tier-card {{ $section['tierClass'] }} rounded-3xl border border-border bg-card p-4 shadow-xs sm:p-5"
            >
                <div class="flex flex-wrap items-center justify-between gap-2 pb-3">
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-8 w-8 items-center justify-center rounded-xl text-xs font-extrabold tabular-nums {{ $section['numBgClass'] }}">
                            {{ $section['number'] }}
                        </span>
                        <h2 class="text-base font-extrabold text-brand-ink">{{ $section['heading'] }}</h2>
                        <x-badge :variant="$section['badgeVariant']">{{ $section['items']->count() }}</x-badge>
                    </div>
                    <span class="text-xs font-bold text-brand-ink tabular-nums">
                        Progres {{ number_format($section['avgProgress'], 0) }}%
                    </span>
                </div>

                @if ($section['items']->isEmpty())
                    <p class="rounded-2xl border border-dashed border-border bg-surface/60 py-6 text-center text-xs text-muted">
                        {{ $section['emptyText'] }}
                    </p>
                @else
                    {{-- Tampilan Kartu Ringkas Mobile (Tanpa Geser Horizontal) --}}
                    <div class="space-y-2.5 md:hidden">
                        @foreach ($section['items'] as $item)
                            @php
                                $barFillClass = $item['is_completed'] ? 'timeline-fill-complete' : $section['fillClass'];
                            @endphp
                            <div class="rounded-2xl border border-border bg-surface/55 p-3.5 space-y-2">
                                <div class="flex flex-wrap items-center justify-between gap-1.5">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <x-badge :variant="$item['period_variant']">{{ $item['scope_label'] }}</x-badge>
                                        @if (! empty($item['weight_percent']))
                                            <x-badge variant="violet">Bobot {{ number_format((float) $item['weight_percent'], 0) }}%</x-badge>
                                        @endif
                                    </div>
                                    <span class="text-xs font-extrabold text-brand-ink tabular-nums">{{ number_format($item['progress_percent'], 0) }}%</span>
                                </div>

                                <h3 class="text-sm font-bold text-brand-ink">{{ $item['title'] }}</h3>
                                @if (! empty($item['parent_title']))
                                    <p class="truncate text-[11px] text-muted">↳ {{ $item['parent_title'] }}</p>
                                @endif

                                <div class="h-2.5 w-full overflow-hidden rounded-full border border-border bg-card">
                                    <div class="h-full {{ $barFillClass }}" style="width: {{ min(100, max(6, $item['progress_percent'])) }}%;"></div>
                                </div>

                                <div class="flex items-center justify-between text-[11px] text-muted">
                                    <span>{{ $item['start_label'] }}–{{ $item['end_label'] }}</span>
                                    <span class="font-semibold text-text">{{ $item['metric_text'] }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Papan Gantt Desktop --}}
                    <div class="hidden overflow-x-auto pb-1 md:block">
                        <div class="min-w-[760px] space-y-2.5">
                            {{-- Skala Pekan --}}
                            <div class="grid grid-cols-12 items-center gap-3 rounded-xl bg-surface px-3 py-1.5 text-[11px] font-bold text-muted">
                                <div class="col-span-5">Target</div>
                                <div class="relative col-span-7 flex overflow-hidden rounded-lg border border-border bg-card">
                                    @foreach ($weeks as $week)
                                        <div class="border-e border-dashed border-border py-1 text-center last:border-e-0" style="width: {{ $week['widthPercent'] }}%;">
                                            <span class="text-brand-ink">{{ $week['label'] }}</span>
                                            <span class="ml-1 text-[10px] font-normal text-muted">{{ $week['range'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Baris Target --}}
                            @foreach ($section['items'] as $item)
                                @php
                                    $barFillClass = $item['is_completed'] ? 'timeline-fill-complete' : $section['fillClass'];
                                @endphp
                                <div class="timeline-row-card rounded-2xl border border-border bg-card p-3">
                                    <div class="grid grid-cols-12 items-center gap-3">
                                        <div class="col-span-5 space-y-1">
                                            <div class="flex flex-wrap items-center gap-1.5">
                                                <x-badge :variant="$item['period_variant']">{{ $item['scope_label'] }}</x-badge>
                                                @if (! empty($item['weight_percent']))
                                                    <x-badge variant="violet">Bobot {{ number_format((float) $item['weight_percent'], 0) }}%</x-badge>
                                                @endif
                                                @if ($item['is_completed'])
                                                    <x-badge variant="success">Selesai</x-badge>
                                                @endif
                                            </div>
                                            <h3 class="text-sm font-bold text-brand-ink leading-snug">{{ $item['title'] }}</h3>
                                            @if (! empty($item['parent_title']))
                                                <p class="truncate text-[11px] text-muted">
                                                    ↳ {{ $item['parent_title'] }}
                                                </p>
                                            @endif
                                        </div>

                                        <div class="col-span-7 space-y-1">
                                            <div class="timeline-track-canvas relative h-9 w-full overflow-hidden rounded-xl border border-border">
                                                @if ($todayOffsetPercent !== null)
                                                    <div
                                                        class="timeline-today-beam pointer-events-none absolute inset-y-0 z-20 w-0.5"
                                                        style="left: {{ $todayOffsetPercent }}%;"
                                                        title="Hari Ini"
                                                    ></div>
                                                @endif

                                                <div
                                                    class="timeline-bar-shell absolute top-1 bottom-1 z-10 overflow-hidden rounded-lg border border-brand-navy/25"
                                                    style="left: {{ $item['offset_percent'] }}%; width: {{ $item['width_percent'] }}%;"
                                                >
                                                    <div
                                                        class="h-full {{ $barFillClass }} transition-all duration-500"
                                                        style="width: {{ min(100, max(8, $item['progress_percent'])) }}%;"
                                                    ></div>
                                                    <div class="absolute inset-0 flex items-center justify-between px-2 text-[11px] font-extrabold text-brand-ink">
                                                        <span class="truncate">{{ $item['start_label'] }}–{{ $item['end_label'] }}</span>
                                                        <span class="ms-1 rounded bg-card/90 px-1.5 py-0.5 text-[10px] tabular-nums">
                                                            {{ number_format($item['progress_percent'], 0) }}%
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex items-center justify-between text-[11px] text-muted">
                                                <span class="font-semibold text-text tabular-nums">{{ $item['metric_text'] }}</span>
                                                @if ($item['pending_count'] > 0 && $canReviewWork && Route::has('work.index'))
                                                    <a href="{{ route('work.index') }}" class="font-bold text-warning-ink underline">
                                                        {{ $item['pending_count'] }} belum dicentang
                                                    </a>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </section>
        @endforeach

        {{-- 03: BUKTI KERJA HARIAN FLEKSIBEL (GOOGLE DRIVE PT) --}}
        <section
            x-show="activeTier === 'all' || activeTier === 'daily'"
            class="timeline-tier-card timeline-tier-daily rounded-3xl border border-border bg-card p-4 shadow-xs sm:p-5"
        >
            <div class="flex flex-wrap items-center justify-between gap-2 pb-3">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-info-soft text-xs font-extrabold text-info-ink">
                        03
                    </span>
                    <h2 class="text-base font-extrabold text-brand-ink">Bukti Kerja Harian (Drive PT)</h2>
                    <x-badge variant="info">{{ $dailyWorkLogs->count() }}</x-badge>
                </div>

                @if ($canUploadWork && Route::has('work.index'))
                    <x-button href="{{ route('work.index') }}" variant="checkin" size="sm">
                        {{ $canReviewWork ? 'Centang / Upload' : '+ Upload Kerjaan' }}
                    </x-button>
                @endif
            </div>

            @if ($dailyWorkLogs->isEmpty())
                <p class="rounded-2xl border border-dashed border-border bg-surface/60 py-6 text-center text-xs text-muted">
                    Belum ada upload bukti kerja harian ke Drive PT pada bulan ini.
                </p>
            @else
                <div class="grid gap-2.5 md:grid-cols-2">
                    @foreach ($dailyWorkLogs->take(10) as $log)
                        <div class="flex items-center justify-between gap-3 rounded-2xl border border-border bg-surface/65 p-3">
                            <div class="min-w-0 space-y-0.5">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="text-[11px] font-bold text-brand-ink tabular-nums">{{ $log->work_date->translatedFormat('d M') }}</span>
                                    <span class="text-xs font-bold text-text">{{ $log->employee?->user?->name ?? '-' }}</span>
                                    <x-badge :variant="$log->status->badgeVariant()">{{ $log->status->label() }}</x-badge>
                                </div>
                                <p class="truncate text-xs font-semibold text-brand-ink">{{ $log->title }}</p>
                                <p class="truncate text-[11px] text-muted">{{ $log->divisionTarget?->title ?? '-' }}</p>
                            </div>

                            @if ($log->drive_web_view_link)
                                <a
                                    href="{{ $log->drive_web_view_link }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="shrink-0 rounded-xl bg-brand-navy-soft px-3 py-1.5 text-[11px] font-bold text-brand-navy hover:bg-brand-navy hover:text-on-brand transition-colors"
                                >
                                    Drive PT
                                </a>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- 04: LOG KRONOLOGIS HARIAN --}}
        <section id="log-kronologis" class="rounded-3xl border border-border bg-card p-4 shadow-xs sm:p-5">
            <div class="flex flex-wrap items-center justify-between gap-2 pb-3">
                <div class="flex items-center gap-2">
                    <h2 class="text-sm font-extrabold text-brand-ink">Log Aktivitas · {{ $selectedDateLabel }}</h2>
                    @if ($selectedEmployee)
                        <span class="text-xs text-muted">({{ $selectedEmployee->user?->name ?? '-' }})</span>
                    @endif
                </div>
                <x-badge variant="navy">{{ $timelineEvents->count() }}</x-badge>
            </div>

            @if ($timelineEvents->isEmpty())
                <p class="text-xs text-muted">Belum ada catatan pada tanggal ini.</p>
            @else
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($timelineEvents as $event)
                        <div class="rounded-2xl border border-border bg-surface/70 p-3">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-xs font-extrabold text-brand-ink tabular-nums">{{ $event['time'] }} WIB</span>
                                <x-badge :variant="$event['variant']">{{ $event['badge'] }}</x-badge>
                            </div>
                            <p class="mt-1.5 text-xs font-bold text-text">{{ $event['title'] }}</p>
                            @if ($event['description'])
                                <p class="mt-0.5 line-clamp-2 text-[11px] text-muted">{{ $event['description'] }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
