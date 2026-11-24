@props([
    'padding' => 'p-4 sm:p-5',
])

<div {{ $attributes->merge(['class' => "bg-card border border-border rounded-2xl shadow-xs {$padding}"]) }}>
    {{ $slot }}
</div>
