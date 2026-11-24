@props([
    'name',
    'title' => '',
    'maxWidth' => 'sm:max-w-lg',
])

<div
    x-data="{ open: false }"
    x-on:open-modal.window="if ($event.detail === @js($name)) open = true"
    x-on:close-modal.window="if (! $event.detail || $event.detail === @js($name)) open = false"
    x-on:keydown.escape.window="open = false"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-4"
    role="dialog"
    aria-modal="true"
    aria-labelledby="modal-title-{{ $name }}"
>
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        x-on:click="open = false"
        class="fixed inset-0 bg-overlay"
    ></div>

    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-6 sm:translate-y-0 sm:scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave-end="opacity-0 translate-y-6 sm:translate-y-0 sm:scale-95"
        class="relative z-10 w-full {{ $maxWidth }} rounded-t-3xl sm:rounded-2xl bg-card border border-border shadow-xl pb-safe max-h-[90vh] overflow-y-auto"
    >
        <div class="mx-auto mt-2.5 h-1 w-10 rounded-full bg-border sm:hidden" aria-hidden="true"></div>

        <div class="flex items-center justify-between border-b border-border px-5 py-4">
            <h2 id="modal-title-{{ $name }}" class="text-base font-bold text-brand-ink">
                {{ $title }}
            </h2>
            <button
                type="button"
                x-on:click="open = false"
                class="inline-flex h-11 w-11 items-center justify-center rounded-xl text-muted hover:bg-surface hover:text-text"
                aria-label="Tutup dialog"
            >
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="p-5">
            {{ $slot }}
        </div>
    </div>
</div>
