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
<body class="veloce-workspace-canvas min-h-full text-text">
    <x-toast />

    <div class="flex min-h-screen flex-col lg:grid lg:grid-cols-12">
        {{-- Panel Blueprint Identitas Brand --}}
        <aside class="timeline-hero-blueprint relative isolate text-on-brand pt-safe lg:col-span-6 xl:col-span-7">
            <svg class="pointer-events-none absolute -right-20 -bottom-24 h-72 w-72 text-on-brand/8 lg:h-[32rem] lg:w-[32rem]" viewBox="0 0 400 400" fill="none" aria-hidden="true">
                <circle cx="200" cy="200" r="60" stroke="currentColor" stroke-width="1.3" stroke-dasharray="6 6" />
                <circle cx="200" cy="200" r="115" stroke="currentColor" stroke-width="1.3" />
                <circle cx="200" cy="200" r="165" stroke="currentColor" stroke-width="1.3" stroke-dasharray="4 8" />
                <circle cx="200" cy="200" r="198" stroke="currentColor" stroke-width="1.3" />
            </svg>

            <div class="relative z-10 mx-auto flex w-full max-w-md flex-col items-center px-6 pt-10 pb-20 text-center lg:h-full lg:max-w-2xl lg:items-start lg:justify-between lg:px-14 lg:py-14 lg:text-left">
                <div class="flex flex-wrap items-center gap-3">
                    <x-brand-logo boxed size="md" />
                    <span class="hidden items-center gap-1.5 rounded-full border border-on-brand/20 bg-on-brand/10 px-3 py-1 text-[11px] font-bold tracking-wider uppercase text-on-brand lg:inline-flex">
                        <span class="timeline-beacon-dot h-2 w-2 rounded-full bg-brand-green"></span>
                        Enterprise Workspace
                    </span>
                </div>

                <div class="mt-6 lg:mt-0">
                    <h1 class="text-3xl font-extrabold tracking-tight text-on-brand lg:text-5xl">{{ config('app.name') }}</h1>
                    <p class="mt-2 text-sm text-on-brand/80 lg:mt-3 lg:max-w-md lg:text-base">{{ __('ui.tagline') }}</p>
                    @if (__('ui.company_name') !== config('app.name'))
                        <p class="mt-1 text-xs text-on-brand/60 lg:hidden">{{ __('ui.company_name') }}</p>
                    @endif

                    <ul class="mt-8 hidden space-y-3 lg:block">
                        @foreach (__('ui.brand_panel.features') as $index => $feature)
                            <li class="timeline-glass-tile flex items-center gap-3.5 rounded-2xl px-4 py-3 text-sm font-medium text-on-brand/95">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-green/25 text-on-brand ring-1 ring-on-brand/15">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
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

        {{-- Area Form Sculpted Card --}}
        <main class="relative z-10 -mt-12 flex flex-1 flex-col px-4 pb-10 lg:col-span-6 lg:mt-0 lg:items-center lg:justify-center lg:px-12 lg:py-12 xl:col-span-5">
            <div class="veloce-page-enter mx-auto w-full max-w-md">
                <div class="veloce-card overflow-hidden rounded-3xl">
                    <div class="h-1.5 w-full bg-gradient-to-r from-brand-navy via-brand-green to-brand-ink" aria-hidden="true"></div>
                    <div class="p-6 sm:p-8">
                        {{ $slot }}
                    </div>
                </div>

                <p class="mt-6 text-center text-xs text-muted lg:hidden">
                    &copy; {{ now()->year }} {{ __('ui.company_name') }}
                </p>
            </div>
        </main>
    </div>
</body>
</html>
