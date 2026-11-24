@props([
    'title' => config('app.name'),
])

@php
    $featureIcons = [
        'M3 8a2 2 0 012-2h2l1.5-2h7L17 6h2a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V8zm9 8.5a3.5 3.5 0 100-7 3.5 3.5 0 000 7z',
        'M8 2v4m8-4v4M3 10h18M5 4h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V6a2 2 0 012-2zm4 11l2 2 4-4',
        'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
    ];
@endphp

<!DOCTYPE html>
<html lang="id" class="h-full antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="">
    @if (file_exists(public_path('manifest.webmanifest')))
        <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    @endif
    @if (file_exists(public_path('icons/icon-192.png')))
        <link rel="icon" type="image/png" href="{{ asset('icons/icon-192.png') }}">
        <link rel="apple-touch-icon" href="{{ asset('icons/icon-192.png') }}">
    @endif

    <title>{{ $title }} — {{ config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-surface text-text">
    <x-toast />

    <div class="flex min-h-screen flex-col lg:grid lg:grid-cols-2">
        {{-- Panel identitas brand: hero di mobile, kolom kiri di desktop --}}
        <aside class="relative isolate overflow-hidden bg-gradient-to-br from-brand-navy to-brand-ink text-on-brand pt-safe">
            <div class="pointer-events-none absolute -top-24 -right-24 h-80 w-80 rounded-full bg-brand-green/20 blur-3xl" aria-hidden="true"></div>
            <div class="pointer-events-none absolute -bottom-32 -left-24 h-96 w-96 rounded-full bg-on-brand/5 blur-3xl" aria-hidden="true"></div>
            <svg class="pointer-events-none absolute -right-20 -bottom-20 h-72 w-72 text-on-brand/10 lg:h-[30rem] lg:w-[30rem]" viewBox="0 0 400 400" fill="none" aria-hidden="true">
                <circle cx="200" cy="200" r="60" stroke="currentColor" stroke-width="1.5" />
                <circle cx="200" cy="200" r="110" stroke="currentColor" stroke-width="1.5" />
                <circle cx="200" cy="200" r="160" stroke="currentColor" stroke-width="1.5" />
                <circle cx="200" cy="200" r="198" stroke="currentColor" stroke-width="1.5" />
            </svg>

            <div class="relative mx-auto flex w-full max-w-md flex-col items-center px-6 pt-10 pb-20 text-center lg:h-full lg:max-w-xl lg:items-start lg:justify-between lg:px-14 lg:py-14 lg:text-left">
                <x-brand-logo boxed size="md" />

                <div class="mt-6 lg:mt-0">
                    <h1 class="text-3xl font-bold tracking-tight text-on-brand lg:text-5xl">{{ config('app.name') }}</h1>
                    <p class="mt-2 text-sm text-on-brand/80 lg:mt-3 lg:max-w-sm lg:text-lg">{{ __('ui.tagline') }}</p>
                    @if (__('ui.company_name') !== config('app.name'))
                        <p class="mt-1 text-xs text-on-brand/60 lg:hidden">{{ __('ui.company_name') }}</p>
                    @endif

                    <ul class="mt-10 hidden space-y-4 lg:block">
                        @foreach (__('ui.brand_panel.features') as $index => $feature)
                            <li class="flex items-center gap-3.5 text-sm text-on-brand/90">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-on-brand/10 ring-1 ring-on-brand/15">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $featureIcons[$index] ?? $featureIcons[0] }}" />
                                    </svg>
                                </span>
                                <span>{{ $feature }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <p class="hidden text-xs text-on-brand/60 lg:block">&copy; {{ now()->year }} {{ __('ui.company_name') }}</p>
            </div>
        </aside>

        {{-- Area form: kartu mengambang di atas hero (mobile), terpusat (desktop) --}}
        <main class="relative z-10 -mt-12 flex flex-1 flex-col px-4 pb-10 lg:mt-0 lg:items-center lg:justify-center lg:px-12 lg:py-12">
            <div class="mx-auto w-full max-w-md">
                <div class="rounded-3xl border border-border bg-card p-6 shadow-xl shadow-brand-ink/10 sm:p-8 lg:shadow-lg lg:shadow-brand-ink/5">
                    {{ $slot }}
                </div>

                <p class="mt-6 text-center text-xs text-muted lg:hidden">
                    &copy; {{ now()->year }} {{ __('ui.company_name') }}
                </p>
            </div>
        </main>
    </div>
</body>
</html>
