@props([
    'title',
    'description' => null,
])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center rounded-2xl border border-dashed border-border bg-card px-6 py-10 text-center']) }}>
    @isset($icon)
        <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-navy-soft text-brand-navy">
            {{ $icon }}
        </div>
    @else
        <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-navy-soft text-brand-navy">
            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20 13V7a2 2 0 00-2-2H6a2 2 0 00-2 2v6m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-3.586a1 1 0 00-.707.293l-1.414 1.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293L8.293 13.293A1 1 0 007.586 13H4"/>
            </svg>
        </div>
    @endisset

    <p class="text-sm font-bold text-brand-ink">{{ $title }}</p>
    @if ($description)
        <p class="mt-1 max-w-sm text-xs leading-relaxed text-muted">{{ $description }}</p>
    @endif

    @isset($action)
        <div class="mt-4">
            {{ $action }}
        </div>
    @endisset
</div>
