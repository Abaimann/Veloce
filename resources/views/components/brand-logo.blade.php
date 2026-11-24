@props([
    'boxed' => false,
    'size' => 'md',
])

@php
    $svgPath = public_path('brand/logo.svg');
    $pngPath = public_path('brand/logo.png');
    $logoUrl = file_exists($svgPath)
        ? asset('brand/logo.svg').'?v='.filemtime($svgPath)
        : (file_exists($pngPath) ? asset('brand/logo.png').'?v='.filemtime($pngPath) : null);

    $heightClass = match ($size) {
        'sm' => 'h-7',
        'lg' => 'h-11',
        default => 'h-9',
    };
@endphp

<div {{ $attributes->class([
    'inline-flex items-center gap-2.5 select-none',
    'bg-card px-3 py-1.5 rounded-xl shadow-xs' => $boxed,
]) }}>
    @if ($logoUrl)
        <img src="{{ $logoUrl }}" alt="{{ __('ui.company_name') }}" class="{{ $heightClass }} w-auto object-contain">
    @else
        <span class="inline-flex items-center justify-center rounded-xl bg-brand-navy px-2.5 py-1 text-sm font-bold tracking-wide text-on-brand">
            VELOCE
        </span>
    @endif
</div>
