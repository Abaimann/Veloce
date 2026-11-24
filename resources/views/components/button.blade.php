@props([
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'button',
    'href' => null,
    'fullWidth' => false,
])

@php
    $baseClasses = 'inline-flex items-center justify-center gap-2 rounded-2xl font-semibold transition-[background-color,border-color,color,box-shadow,transform] duration-150 active:scale-[0.98] cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed disabled:active:scale-100 select-none';

    $variantClasses = match ($variant) {
        'checkin' => 'bg-brand-green-strong text-on-brand hover:bg-brand-green shadow-sm shadow-brand-green-strong/20',
        'secondary' => 'border border-brand-navy/25 text-brand-navy bg-card hover:bg-brand-navy-soft hover:border-brand-navy/50 shadow-xs',
        'danger' => 'bg-danger text-on-brand hover:opacity-90 shadow-sm shadow-danger/20',
        'danger-outline' => 'border border-danger/35 text-danger-ink bg-card hover:bg-danger-soft',
        'ghost' => 'text-brand-navy bg-transparent hover:bg-brand-navy-soft',
        default => 'bg-brand-navy text-on-brand hover:bg-brand-ink shadow-sm shadow-brand-navy/20',
    };

    $sizeClasses = match ($size) {
        'sm' => 'min-h-11 px-4 py-2 text-xs',
        'lg' => 'min-h-13 px-6 py-3.5 text-base',
        default => 'min-h-11 px-5 py-2.5 text-sm',
    };

    $classes = trim("{$baseClasses} {$variantClasses} {$sizeClasses} ".($fullWidth ? 'w-full' : ''));
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif
