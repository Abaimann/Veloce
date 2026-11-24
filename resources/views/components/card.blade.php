@props([
    'padding' => 'p-4 sm:p-5',
])

<div {{ $attributes->merge(['class' => "veloce-card min-w-0 rounded-3xl {$padding}"]) }}>
    {{ $slot }}
</div>
