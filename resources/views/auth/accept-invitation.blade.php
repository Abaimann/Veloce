<x-guest-layout title="Aktivasi Akun">
    @if ($statusError)
        <div class="space-y-4 text-center">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-danger-soft text-danger-ink">
                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                </svg>
            </div>
            <div>
                <h2 class="text-xl font-bold tracking-tight text-brand-ink">Undangan Tidak Dapat Digunakan</h2>
                <p class="mt-1.5 text-sm text-muted">{{ $statusError }}</p>
            </div>
            <x-button href="{{ route('login') }}" variant="secondary" fullWidth>
                Ke Halaman Masuk
            </x-button>
        </div>
    @else
        <div class="mb-6">
            <h2 class="text-2xl font-bold tracking-tight text-brand-ink">Aktivasi Akun Karyawan</h2>
            <p class="mt-1.5 text-sm text-muted">
                Halo <span class="font-semibold text-text">{{ $invitation->user->name }}</span>, buat kata sandi pribadi Anda untuk mulai menggunakan {{ config('app.name') }}.
            </p>
        </div>

        @error('token')
            <div class="mb-4 rounded-2xl border border-danger/30 bg-danger-soft p-3.5 text-xs font-medium text-danger-ink">
                {{ $message }}
            </div>
        @enderror

        <form
            method="POST"
            action="{{ $actionUrl }}"
            class="space-y-4"
            x-data="{ submitting: false, showPassword: false }"
            x-on:submit="submitting = true"
        >
            @csrf

            <div class="rounded-2xl border border-border bg-surface px-4 py-3 text-xs">
                <span class="text-muted">Email terdaftar:</span>
                <p class="mt-0.5 font-semibold text-text">{{ $invitation->user->email }}</p>
            </div>

            <x-input
                name="password"
                type="password"
                label="Kata Sandi Baru"
                placeholder="Minimal 8 karakter (huruf dan angka)"
                autocomplete="new-password"
                required
                autofocus
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

            <x-input
                name="password_confirmation"
                type="password"
                label="Ulangi Kata Sandi"
                placeholder="Ketik ulang kata sandi baru"
                autocomplete="new-password"
                required
                x-bind:type="showPassword ? 'text' : 'password'"
            >
                <x-slot:leading>
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </x-slot:leading>
            </x-input>

            <x-button type="submit" variant="checkin" size="lg" fullWidth x-bind:disabled="submitting">
                <span x-text="submitting ? '{{ __('ui.login.processing') }}' : 'Simpan Kata Sandi & Masuk'">Simpan Kata Sandi &amp; Masuk</span>
            </x-button>
        </form>
    @endif
</x-guest-layout>
