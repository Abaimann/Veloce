@php
    $statusMessage = session('status');
    $errorMessage = session('error');
@endphp

@if ($statusMessage || $errorMessage)
    @php
        $isError = (bool) $errorMessage;
        $message = $errorMessage ?: $statusMessage;
    @endphp

    <div
        x-data="{ visible: true }"
        x-init="setTimeout(() => visible = false, 6000)"
        x-show="visible"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        role="status"
        aria-live="polite"
        class="fixed top-4 right-4 left-4 z-50 mx-auto max-w-md rounded-2xl border p-4 shadow-lg {{ $isError ? 'bg-danger-soft border-danger/30 text-danger-ink' : 'bg-success-soft border-brand-green/30 text-success-ink' }}"
    >
        <div class="flex items-start justify-between gap-3">
            <div class="flex items-start gap-2.5 text-sm font-medium">
                @if ($isError)
                    <svg class="mt-0.5 h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                    </svg>
                @else
                    <svg class="mt-0.5 h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                @endif
                <span>{{ $message }}</span>
            </div>
            <button
                type="button"
                x-on:click="visible = false"
                class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-xl hover:bg-card/50"
                aria-label="Tutup notifikasi"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>
@endif
