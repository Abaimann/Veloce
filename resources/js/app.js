import './bootstrap';
import Alpine from 'alpinejs';
import attendanceCamera from './camera-attendance';

window.Alpine = Alpine;

document.addEventListener('alpine:init', () => {
    Alpine.data('sidebarShell', () => ({
        collapsed: document.documentElement.dataset.sidebarCollapsed === 'true',
        toggle() {
            this.collapsed = !this.collapsed;
            document.documentElement.dataset.sidebarCollapsed = this.collapsed ? 'true' : 'false';
            try {
                window.localStorage.setItem('veloce_sidebar_collapsed', this.collapsed ? '1' : '0');
            } catch {
                // Abaikan jika storage tidak tersedia
            }
        },
    }));
    Alpine.data('attendanceCamera', attendanceCamera);
    Alpine.data('serverClock', (initialServerEpochMs) => ({
        offsetMs: initialServerEpochMs - Date.now(),
        timeText: '',
        dateText: '',
        timerId: null,
        init() {
            this.updateClock();
            this.timerId = setInterval(() => this.updateClock(), 1000);
        },
        destroy() {
            if (this.timerId) {
                clearInterval(this.timerId);
            }
        },
        updateClock() {
            const serverNow = new Date(Date.now() + this.offsetMs);
            this.timeText = new Intl.DateTimeFormat('id-ID', {
                timeZone: 'Asia/Jakarta',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false,
            }).format(serverNow).replaceAll('.', ':') + ' WIB';

            const rawDate = new Intl.DateTimeFormat('id-ID', {
                timeZone: 'Asia/Jakarta',
                weekday: 'long',
                day: 'numeric',
                month: 'long',
                year: 'numeric',
            }).format(serverNow);

            this.dateText = rawDate;
        },
    }));
});

document.addEventListener('DOMContentLoaded', () => {
    const brandNavy = getComputedStyle(document.documentElement)
        .getPropertyValue('--brand-navy')
        .trim();
    const themeMeta = document.querySelector('meta[name="theme-color"]');
    if (themeMeta && brandNavy) {
        themeMeta.setAttribute('content', brandNavy);
    }

    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    }
});

Alpine.start();
