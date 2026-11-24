@props([
    'approvals',
    'currentStep' => null,
])

@php
    $timezone = (string) config('attendance.timezone', 'Asia/Jakarta');
    $steps = collect($approvals)->sortBy('step')->values();
@endphp

@if ($steps->isEmpty())
    <div class="rounded-2xl border border-dashed border-border bg-surface px-4 py-6 text-center text-xs text-muted">
        Belum ada tahapan persetujuan yang tercatat.
    </div>
@else
    <ol class="space-y-0" aria-label="Linimasa Persetujuan">
        @foreach ($steps as $index => $step)
            @php
                $status = $step->status instanceof \App\Enums\ApprovalStatus
                    ? $step->status
                    : (\App\Enums\ApprovalStatus::tryFrom((string) $step->status) ?? \App\Enums\ApprovalStatus::PENDING);

                $roleValue = $step->role instanceof \BackedEnum
                    ? (string) $step->role->value
                    : (string) $step->role;

                $isLast = $index === $steps->count() - 1;
                $isCurrent = $status === \App\Enums\ApprovalStatus::PENDING
                    && $currentStep !== null
                    && (int) $step->step === (int) $currentStep;

                $dotClasses = match ($status) {
                    \App\Enums\ApprovalStatus::APPROVED => 'bg-success-soft text-success-ink border-brand-green/30',
                    \App\Enums\ApprovalStatus::REJECTED => 'bg-danger-soft text-danger-ink border-danger/30',
                    \App\Enums\ApprovalStatus::PENDING => 'bg-warning-soft text-warning-ink border-warning/30',
                };

                $lineClasses = match ($status) {
                    \App\Enums\ApprovalStatus::APPROVED => 'bg-brand-green/30',
                    \App\Enums\ApprovalStatus::REJECTED => 'bg-danger/30',
                    \App\Enums\ApprovalStatus::PENDING => 'bg-border',
                };
            @endphp

            <li class="relative flex gap-3.5 {{ $isLast ? '' : 'pb-5' }}">
                @if (! $isLast)
                    <span
                        aria-hidden="true"
                        class="absolute top-9 bottom-0 left-4 -ml-px w-0.5 {{ $lineClasses }}"
                    ></span>
                @endif

                <div class="relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full border {{ $dotClasses }} {{ $isCurrent ? 'ring-4 ring-warning-soft' : '' }}">
                    @if ($status === \App\Enums\ApprovalStatus::APPROVED)
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    @elseif ($status === \App\Enums\ApprovalStatus::REJECTED)
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    @else
                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <circle cx="12" cy="12" r="5" />
                        </svg>
                    @endif
                </div>

                <div class="min-w-0 flex-1 rounded-2xl border {{ $isCurrent ? 'border-warning/40 bg-warning-soft/20' : 'border-border bg-surface' }} p-3.5">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-xs font-bold text-brand-ink">
                                Tahap {{ $step->step }} · {{ __('ui.roles.' . $roleValue) }}
                            </p>
                            @if ($step->approver)
                                <p class="mt-0.5 text-xs font-medium text-text">
                                    {{ $step->approver->name }}
                                </p>
                            @elseif ($isCurrent)
                                <p class="mt-0.5 text-xs font-medium text-warning-ink">
                                    Menunggu tindakan penyetuju saat ini
                                </p>
                            @else
                                <p class="mt-0.5 text-xs text-muted">
                                    Menunggu giliran persetujuan
                                </p>
                            @endif
                        </div>

                        <div class="flex flex-col items-end gap-1">
                            <x-badge :variant="$status->badgeVariant()">
                                {{ $status->label() }}
                            </x-badge>
                            @if ($step->acted_at)
                                <span class="text-[11px] font-medium tabular-nums text-muted">
                                    {{ $step->acted_at->setTimezone($timezone)->translatedFormat('d M Y, H:i') }} WIB
                                </span>
                            @endif
                        </div>
                    </div>

                    @if (is_string($step->note) && trim($step->note) !== '')
                        <p class="mt-2.5 rounded-xl border border-border bg-card px-3 py-2 text-xs leading-relaxed text-text">
                            <span class="font-semibold text-muted">Catatan:</span> {{ $step->note }}
                        </p>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>
@endif
