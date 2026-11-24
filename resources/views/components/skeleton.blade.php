@props([
    'lines' => 3,
])

<div {{ $attributes->merge(['class' => 'animate-pulse space-y-3 rounded-2xl border border-border bg-card p-4']) }} aria-hidden="true">
    <div class="h-4 w-1/3 rounded-full bg-border"></div>
    @for ($i = 0; $i < $lines; $i++)
        <div class="h-3 {{ $i === $lines - 1 ? 'w-2/3' : 'w-full' }} rounded-full bg-surface"></div>
    @endfor
</div>
