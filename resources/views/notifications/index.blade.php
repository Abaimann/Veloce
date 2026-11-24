<x-app-layout
    title="Notifikasi"
    subtitle="Pembaruan pengajuan izin, cuti, WFH, dan tugas persetujuan"
    :unread-notifications-count="$unreadCount"
>
    <div class="space-y-4">
        {{-- Header & Aksi Tandai Semua Dibaca --}}
        <x-card>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-wrap items-center gap-2.5">
                    <h2 class="text-base font-bold text-brand-ink">Daftar Notifikasi</h2>
                    @if ($unreadCount > 0)
                        <x-badge variant="navy">
                            {{ $unreadCount }} Belum Dibaca
                        </x-badge>
                    @else
                        <x-badge variant="success">
                            Semua Sudah Dibaca
                        </x-badge>
                    @endif
                </div>

                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <x-button
                        type="submit"
                        variant="secondary"
                        size="sm"
                        :disabled="$unreadCount === 0"
                        class="w-full sm:w-auto"
                    >
                        Tandai Semua Dibaca
                    </x-button>
                </form>
            </div>
        </x-card>

        @if ($notifications->isEmpty())
            <x-empty-state
                title="Belum ada notifikasi"
                description="Pembaruan status pengajuan izin, cuti, WFH, maupun tugas persetujuan akan tampil di sini."
            />
        @else
            <div class="space-y-3">
                @foreach ($notifications as $notification)
                    @php
                        $data = is_array($notification->data) ? $notification->data : [];
                        $isUnread = $notification->read_at === null;
                        $title = is_string($data['title'] ?? null) && $data['title'] !== ''
                            ? $data['title']
                            : 'Pembaruan Sistem';
                        $message = is_string($data['message'] ?? null)
                            ? $data['message']
                            : '';
                        $rawUrl = $data['url'] ?? null;
                        $hasActionUrl = is_string($rawUrl)
                            && str_starts_with($rawUrl, '/')
                            && ! str_starts_with($rawUrl, '//');
                        $createdAtWib = $notification->created_at?->setTimezone($timezone);
                    @endphp

                    <div class="rounded-2xl border p-4 shadow-xs transition-colors duration-150 sm:p-5 {{ $isUnread ? 'border-brand-navy/40 bg-brand-navy-soft/35' : 'border-border bg-card' }}">
                        <div class="flex flex-col gap-3.5 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0 flex-1 space-y-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    @if ($isUnread)
                                        <span
                                            class="h-2.5 w-2.5 shrink-0 rounded-full bg-brand-green-strong"
                                            aria-label="Belum dibaca"
                                        ></span>
                                    @endif

                                    <h3 class="text-sm font-bold text-brand-ink">
                                        {{ $title }}
                                    </h3>

                                    @if ($isUnread)
                                        <x-badge variant="navy">Belum dibaca</x-badge>
                                    @else
                                        <x-badge variant="neutral">Dibaca</x-badge>
                                    @endif
                                </div>

                                @if ($message !== '')
                                    <p class="text-xs leading-relaxed text-text sm:text-sm">
                                        {{ $message }}
                                    </p>
                                @endif

                                @if ($createdAtWib)
                                    <p class="text-[11px] font-medium tabular-nums text-muted">
                                        {{ $createdAtWib->translatedFormat('d M Y, H:i') }} WIB · {{ $createdAtWib->diffForHumans() }}
                                    </p>
                                @endif
                            </div>

                            <div class="flex flex-wrap items-center gap-2 sm:shrink-0 sm:justify-end">
                                @if ($hasActionUrl)
                                    @if ($isUnread)
                                        <form method="POST" action="{{ route('notifications.read', [$notification->id, 'redirect' => 1]) }}">
                                            @csrf
                                            <x-button type="submit" variant="primary" size="sm">
                                                Lihat Detail
                                            </x-button>
                                        </form>
                                    @else
                                        <x-button href="{{ url($rawUrl) }}" variant="secondary" size="sm">
                                            Lihat Detail
                                        </x-button>
                                    @endif
                                @endif

                                @if ($isUnread)
                                    <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                                        @csrf
                                        <x-button type="submit" variant="secondary" size="sm">
                                            Tandai Dibaca
                                        </x-button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($notifications->hasPages())
                <div class="pt-1">
                    {{ $notifications->links() }}
                </div>
            @endif
        @endif
    </div>
</x-app-layout>
