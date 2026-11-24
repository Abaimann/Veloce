<x-guest-layout title="Masuk">
    <div class="mb-7">
        <h2 class="text-2xl font-bold tracking-tight text-brand-ink">{{ __('ui.login.title') }}</h2>
        <p class="mt-1.5 text-sm text-muted">{{ __('ui.login.subtitle') }}</p>
    </div>

    <form
        method="POST"
        action="{{ route('login.store') }}"
        class="space-y-5"
        x-data="{ submitting: false, showPassword: false }"
        x-on:submit="submitting = true"
    >
        @csrf

        <x-input
            name="email"
            type="email"
            :label="__('ui.login.email')"
            placeholder="nama@veloce.id"
            autocomplete="email"
            inputmode="email"
            required
            autofocus
        >
            <x-slot:leading>
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16a1 1 0 011 1v10a1 1 0 01-1 1H4a1 1 0 01-1-1V7a1 1 0 011-1zm0 1l8 6 8-6" />
                </svg>
            </x-slot:leading>
        </x-input>

        <x-input
            name="password"
            type="password"
            :label="__('ui.login.password')"
            :placeholder="__('ui.login.password_placeholder')"
            autocomplete="current-password"
            required
            x-bind:type="showPassword ? 'text' : 'password'"
        >
            <x-slot:leading>
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 11V8a5 5 0 0110 0v3M6 11h12a1 1 0 011 1v8a1 1 0 01-1 1H6a1 1 0 01-1-1v-8a1 1 0 011-1z" />
                </svg>
            </x-slot:leading>
            <x-slot:trailing>
                <button
                    type="button"
                    x-on:click="showPassword = ! showPassword"
                    class="inline-flex h-11 w-11 items-center justify-center rounded-xl text-muted transition-colors duration-150 hover:bg-surface hover:text-brand-navy"
                    aria-label="{{ __('ui.login.show_password') }}"
                    x-bind:aria-label="showPassword ? '{{ __('ui.login.hide_password') }}' : '{{ __('ui.login.show_password') }}'"
                >
                    <svg x-show="! showPassword" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12S6 5 12 5s9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7z" />
                        <circle cx="12" cy="12" r="3" />
                    </svg>
                    <svg x-show="showPassword" x-cloak class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M10.6 10.6a2 2 0 002.8 2.8M9.9 5.2A9.7 9.7 0 0112 5c6 0 9.5 7 9.5 7a17 17 0 01-3.2 4.1M6.3 6.3C3.8 8 2.5 12 2.5 12s3.5 7 9.5 7a9.6 9.6 0 004.4-1.1" />
                    </svg>
                </button>
            </x-slot:trailing>
        </x-input>

        <label class="inline-flex min-h-11 cursor-pointer select-none items-center gap-3 text-sm text-text">
            <input
                type="checkbox"
                name="remember"
                value="1"
                class="h-5 w-5 rounded-md border-border accent-brand-navy"
                @checked(old('remember'))
            >
            <span>{{ __('ui.login.remember') }}</span>
        </label>

        <x-button type="submit" variant="primary" size="lg" fullWidth x-bind:disabled="submitting">
            <svg x-show="submitting" x-cloak class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" class="opacity-25" />
                <path d="M21 12a9 9 0 00-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
            </svg>
            <span x-text="submitting ? '{{ __('ui.login.processing') }}' : '{{ __('ui.actions.login') }}'">{{ __('ui.actions.login') }}</span>
        </x-button>
    </form>

    <div class="mt-6 flex items-start gap-3 rounded-2xl bg-brand-navy-soft p-4 text-xs leading-relaxed text-brand-ink">
        <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <circle cx="12" cy="12" r="9" />
            <path stroke-linecap="round" d="M12 11v5m0-8h.01" />
        </svg>
        <p>{{ __('ui.login.no_account') }}</p>
    </div>
</x-guest-layout>
