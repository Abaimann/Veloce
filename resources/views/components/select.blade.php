@props([
    'name',
    'label' => null,
    'options' => [],
    'value' => null,
    'placeholder' => null,
    'required' => false,
    'id' => null,
])

@php
    $selectId = $id ?? str_replace(['[', ']'], ['_', ''], $name);
    $errorKey = str_replace(['[', ']'], ['.', ''], $name);
    $hasError = $errors->has($errorKey);
    $selectedValue = (string) old($errorKey, $value ?? '');
@endphp

<div class="space-y-1.5">
    @if ($label)
        <label for="{{ $selectId }}" class="block text-sm font-semibold text-text">
            {{ $label }}
            @if ($required)
                <span class="text-danger" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    <select
        id="{{ $selectId }}"
        name="{{ $name }}"
        @if ($required) required @endif
        @if ($hasError) aria-invalid="true" aria-describedby="{{ $selectId }}-error" @endif
        {{ $attributes->class([
            'block w-full min-h-12 rounded-2xl border bg-card px-4 py-3 text-sm text-text shadow-xs transition-[border-color,box-shadow] duration-150 focus:outline-none focus:ring-4',
            'border-danger focus:border-danger focus:ring-danger/15' => $hasError,
            'border-border hover:border-muted/50 focus:border-brand-navy focus:ring-brand-navy/10' => ! $hasError,
        ]) }}
    >
        @if ($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif

        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected($selectedValue === (string) $optionValue)>
                {{ $optionLabel }}
            </option>
        @endforeach

        {{ $slot }}
    </select>

    @error($errorKey)
        <p id="{{ $selectId }}-error" class="flex items-start gap-1.5 text-xs font-medium text-danger-ink">
            <svg class="mt-px h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                <circle cx="12" cy="12" r="9" />
                <path stroke-linecap="round" d="M12 8v5m0 3h.01" />
            </svg>
            <span>{{ $message }}</span>
        </p>
    @enderror
</div>
