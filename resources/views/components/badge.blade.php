@props([
    'variant' => 'neutral',
])

@php
    $variantClasses = match ($variant) {
        'success' => 'bg-success-soft text-success-ink border-brand-green/20',
        'warning' => 'bg-warning-soft text-warning-ink border-warning/25',
        'info' => 'bg-info-soft text-info-ink border-info/25',
        'danger' => 'bg-danger-soft text-danger-ink border-danger/25',
        'violet' => 'bg-violet-soft text-violet-ink border-violet/25',
        'navy' => 'bg-brand-navy-soft text-brand-ink border-brand-navy/20',
        default => 'bg-surface text-text border-border',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold leading-none {$variantClasses}"]) }}>
    {{ $slot }}
</span>
