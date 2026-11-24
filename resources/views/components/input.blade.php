@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'hint' => null,
    'required' => false,
    'id' => null,
])

@php
    $inputId = $id ?? str_replace(['[', ']'], ['_', ''], $name);
    $errorKey = str_replace(['[', ']'], ['.', ''], $name);
    $hasError = $errors->has($errorKey);
    $hasLeading = isset($leading) && $leading->isNotEmpty();
    $hasTrailing = isset($trailing) && $trailing->isNotEmpty();
@endphp

<div class="space-y-1.5">
    @if ($label)
        <label for="{{ $inputId }}" class="block text-sm font-semibold text-text">
            {{ $label }}
            @if ($required)
                <span class="text-danger" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    <div class="relative">
        @if ($hasLeading)
            <span class="pointer-events-none absolute inset-y-0 left-0 flex w-12 items-center justify-center text-muted" aria-hidden="true">
                {{ $leading }}
            </span>
        @endif

        <input
            id="{{ $inputId }}"
            name="{{ $name }}"
            type="{{ $type }}"
            value="{{ old($errorKey, $value) }}"
            @if ($required) required @endif
            @if ($hasError) aria-invalid="true" aria-describedby="{{ $inputId }}-error" @endif
            {{ $attributes->class([
                'block w-full min-h-12 rounded-2xl border bg-card py-3 text-sm text-text placeholder:text-muted shadow-xs transition-[border-color,box-shadow] duration-150 focus:outline-none focus:ring-4',
                'pl-12' => $hasLeading,
                'pl-4' => ! $hasLeading,
                'pr-14' => $hasTrailing,
                'pr-4' => ! $hasTrailing,
                'border-danger focus:border-danger focus:ring-danger/15' => $hasError,
                'border-border hover:border-muted/50 focus:border-brand-navy focus:ring-brand-navy/10' => ! $hasError,
            ]) }}
        >

        @if ($hasTrailing)
            <div class="absolute inset-y-0 right-0 flex items-center pr-0.5">
                {{ $trailing }}
            </div>
        @endif
    </div>

    @if ($hint && ! $hasError)
        <p class="text-xs text-muted">{{ $hint }}</p>
    @endif

    @error($errorKey)
        <p id="{{ $inputId }}-error" class="flex items-start gap-1.5 text-xs font-medium text-danger-ink">
            <svg class="mt-px h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                <circle cx="12" cy="12" r="9" />
                <path stroke-linecap="round" d="M12 8v5m0 3h.01" />
            </svg>
            <span>{{ $message }}</span>
        </p>
    @enderror
</div>
