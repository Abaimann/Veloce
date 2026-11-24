const EARTH_RADIUS_METERS = 6371000;

function toRadians(degrees) {
    return (degrees * Math.PI) / 180;
}

function haversineMeters(lat1, lng1, lat2, lng2) {
    const dLat = toRadians(lat2 - lat1);
    const dLng = toRadians(lng2 - lng1);

    const a =
        Math.sin(dLat / 2) * Math.sin(dLat / 2) +
        Math.cos(toRadians(lat1)) *
            Math.cos(toRadians(lat2)) *
            Math.sin(dLng / 2) *
            Math.sin(dLng / 2);

    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

    return EARTH_RADIUS_METERS * c;
}

export function attendanceCamera(config = {}) {
    return {
        checkInUrl: config.checkInUrl ?? '',
        checkOutUrl: config.checkOutUrl ?? '',
        mode: config.mode ?? 'check_in',
        offices: Array.isArray(config.offices) ? config.offices : [],
        hasApprovedWfh: Boolean(config.hasApprovedWfh),
        enforceGeofenceOnCheckout:
            config.enforceGeofenceOnCheckout !== undefined
                ? Boolean(config.enforceGeofenceOnCheckout)
                : true,
        csrfToken:
            config.csrfToken ??
            document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ??
            '',
        maxWidth: Number(config.maxWidth) > 0 ? Number(config.maxWidth) : 720,
        jpegQuality:
            Number(config.jpegQuality) > 0 && Number(config.jpegQuality) <= 1
                ? Number(config.jpegQuality)
                : 0.72,
        maxAcceptableAccuracy:
            Number(config.maxAcceptableAccuracy) > 0
                ? Number(config.maxAcceptableAccuracy)
                : 100,

        stream: null,
        cameraLoading: false,
        cameraReady: false,
        cameraError: null,

        capturedBlob: null,
        previewUrl: null,

        watchId: null,
        lat: null,
        lng: null,
        accuracy: null,
        locationLoading: false,
        locationError: null,
        nearestOffice: null,
        distanceMeters: null,
        isInsideOffice: false,

        submitting: false,
        submitError: null,
        submitSuccess: null,

        _pageHideHandler: null,

        init() {
            if (!['check_in', 'check_out'].includes(this.mode)) {
                return;
            }

            this._pageHideHandler = () => {
                this.stopCamera();
                this.stopGeolocation();
            };
            window.addEventListener('pagehide', this._pageHideHandler);

            this.$nextTick(() => {
                this.startCamera();
                this.startGeolocation();
            });
        },

        destroy() {
            if (this._pageHideHandler) {
                window.removeEventListener('pagehide', this._pageHideHandler);
                this._pageHideHandler = null;
            }

            this.stopCamera();
            this.stopGeolocation();
            this.revokePreviewUrl();
        },

        get locationChipText() {
            if (this.locationError) {
                return 'GPS bermasalah';
            }

            if (this.lat === null || this.lng === null) {
                return 'Mencari lokasi GPS...';
            }

            if (this.hasApprovedWfh) {
                return 'Mode WFH Aktif';
            }

            if (this.nearestOffice && this.distanceMeters !== null) {
                return this.isInsideOffice
                    ? `Di dalam area kantor - ${this.distanceMeters} m`
                    : `Di luar area kantor - ${this.distanceMeters} m`;
            }

            return 'Lokasi kantor belum tersedia';
        },

        get locationChipTone() {
            if (this.locationError) {
                return 'danger';
            }

            if (this.lat === null || this.lng === null) {
                return 'info';
            }

            if (this.hasApprovedWfh) {
                return 'info';
            }

            if (this.nearestOffice) {
                return this.isInsideOffice ? 'success' : 'warning';
            }

            return 'warning';
        },

        get hasLowAccuracy() {
            return (
                this.accuracy !== null &&
                Number(this.accuracy) > this.maxAcceptableAccuracy
            );
        },

        get canCapture() {
            return (
                this.cameraReady &&
                !this.cameraError &&
                !this.previewUrl &&
                !this.submitting
            );
        },

        get canSubmit() {
            return (
                Boolean(this.capturedBlob) &&
                Boolean(this.previewUrl) &&
                this.lat !== null &&
                this.lng !== null &&
                !this.submitting
            );
        },

        async startCamera() {
            this.stopCamera();
            this.cameraError = null;
            this.cameraReady = false;

            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                this.cameraError =
                    window.isSecureContext === false
                        ? 'Akses kamera memerlukan koneksi aman (HTTPS). Buka aplikasi melalui HTTPS lalu muat ulang halaman ini.'
                        : 'Browser Anda tidak mendukung akses kamera langsung. Gunakan Google Chrome, Safari, atau browser modern versi terbaru.';
                return;
            }

            this.cameraLoading = true;

            try {
                const stream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: 'user',
                        width: { ideal: 1280 },
                        height: { ideal: 960 },
                    },
                    audio: false,
                });

                this.stream = stream;

                const video = this.$refs.video;
                if (!video) {
                    this.stopCamera();
                    return;
                }

                video.srcObject = stream;
                video.muted = true;
                video.setAttribute('playsinline', '');

                await new Promise((resolve) => {
                    if (video.readyState >= 1 && video.videoWidth > 0) {
                        resolve();
                        return;
                    }

                    const onLoaded = () => {
                        video.removeEventListener('loadedmetadata', onLoaded);
                        resolve();
                    };

                    video.addEventListener('loadedmetadata', onLoaded, { once: true });
                    setTimeout(resolve, 1500);
                });

                try {
                    await video.play();
                } catch {
                    // Sebagian browser memutar otomatis setelah metadata siap.
                }

                this.cameraReady = true;
            } catch (error) {
                this.cameraError = this.resolveCameraErrorMessage(error);
                this.stopCamera();
            } finally {
                this.cameraLoading = false;
            }
        },

        stopCamera() {
            if (this.stream) {
                this.stream.getTracks().forEach((track) => track.stop());
                this.stream = null;
            }

            if (this.$refs.video) {
                this.$refs.video.srcObject = null;
            }

            this.cameraReady = false;
        },

        resolveCameraErrorMessage(error) {
            const name = error?.name || '';

            if (name === 'NotAllowedError' || name === 'PermissionDeniedError') {
                return 'Izin kamera ditolak. Buka pengaturan situs browser Anda (ikon gembok di bilah alamat), ubah izin Kamera menjadi "Izinkan", lalu tekan tombol "Coba Lagi".';
            }

            if (name === 'NotFoundError' || name === 'DevicesNotFoundError') {
                return 'Kamera depan tidak ditemukan pada perangkat ini. Pastikan perangkat memiliki kamera aktif dan tidak sedang terputus.';
            }

            if (name === 'NotReadableError' || name === 'TrackStartError') {
                return 'Kamera sedang digunakan oleh aplikasi lain. Tutup aplikasi kamera atau panggilan video lain, lalu tekan "Coba Lagi".';
            }

            if (name === 'OverconstrainedError') {
                return 'Kamera perangkat tidak memenuhi konfigurasi resolusi yang diminta. Coba muat ulang halaman atau gunakan browser lain.';
            }

            return 'Kamera gagal diakses. Pastikan izin kamera telah diberikan di pengaturan browser lalu tekan "Coba Lagi".';
        },

        startGeolocation() {
            this.stopGeolocation();
            this.locationError = null;

            if (!('geolocation' in navigator)) {
                this.locationError =
                    'Browser atau perangkat Anda tidak mendukung layanan GPS. Gunakan perangkat dengan GPS aktif dan browser modern.';
                return;
            }

            this.locationLoading = true;

            this.watchId = navigator.geolocation.watchPosition(
                (position) => {
                    this.lat = Number(position.coords.latitude);
                    this.lng = Number(position.coords.longitude);
                    this.accuracy = Number.isFinite(position.coords.accuracy)
                        ? Math.round(position.coords.accuracy * 100) / 100
                        : 0;
                    this.locationLoading = false;
                    this.locationError = null;
                    this.updateNearestOffice();
                },
                (error) => {
                    this.locationLoading = false;
                    this.locationError = this.resolveLocationErrorMessage(error);
                },
                {
                    enableHighAccuracy: true,
                    timeout: 15000,
                    maximumAge: 0,
                },
            );
        },

        stopGeolocation() {
            if (this.watchId !== null && 'geolocation' in navigator) {
                navigator.geolocation.clearWatch(this.watchId);
                this.watchId = null;
            }
        },

        resolveLocationErrorMessage(error) {
            switch (error?.code) {
                case 1:
                    return 'Izin lokasi (GPS) ditolak. Aktifkan GPS perangkat dan pilih "Izinkan" untuk Lokasi pada pengaturan browser (ikon gembok di bilah alamat), lalu tekan "Perbarui Lokasi".';
                case 2:
                    return 'Posisi GPS tidak tersedia saat ini. Pastikan GPS/Layanan Lokasi perangkat aktif dan tidak dalam mode pesawat.';
                case 3:
                    return 'Waktu pencarian sinyal GPS habis. Pindah ke area yang lebih terbuka atau dekat jendela lalu tekan "Perbarui Lokasi".';
                default:
                    return 'Gagal membaca koordinat GPS. Pastikan layanan lokasi aktif dan coba kembali.';
            }
        },

        updateNearestOffice() {
            if (this.lat === null || this.lng === null || this.offices.length === 0) {
                this.nearestOffice = null;
                this.distanceMeters = null;
                this.isInsideOffice = false;
                return;
            }

            let bestOffice = null;
            let bestDistance = Infinity;
            let insideOffice = null;
            let insideDistance = Infinity;

            for (const office of this.offices) {
                const officeLat = Number(office.lat);
                const officeLng = Number(office.lng);
                const radius = Number(office.radius_meters);
                const distance = haversineMeters(this.lat, this.lng, officeLat, officeLng);

                if (distance <= radius && distance < insideDistance) {
                    insideOffice = office;
                    insideDistance = distance;
                }

                if (distance < bestDistance) {
                    bestOffice = office;
                    bestDistance = distance;
                }
            }

            if (insideOffice) {
                this.nearestOffice = insideOffice;
                this.distanceMeters = Math.round(insideDistance);
                this.isInsideOffice = true;
            } else if (bestOffice) {
                this.nearestOffice = bestOffice;
                this.distanceMeters = Math.round(bestDistance);
                this.isInsideOffice = false;
            }
        },

        capture() {
            if (!this.canCapture) {
                return;
            }

            const video = this.$refs.video;
            const canvas = this.$refs.canvas;

            if (!video || !canvas || !video.videoWidth || !video.videoHeight) {
                this.submitError = 'Video kamera belum siap. Tunggu sebentar lalu coba ambil foto ulang.';
                return;
            }

            const sourceWidth = video.videoWidth;
            const sourceHeight = video.videoHeight;
            const targetWidth = sourceWidth > this.maxWidth ? this.maxWidth : sourceWidth;
            const targetHeight = Math.max(1, Math.round((sourceHeight / sourceWidth) * targetWidth));

            canvas.width = targetWidth;
            canvas.height = targetHeight;

            const context = canvas.getContext('2d');
            if (!context) {
                this.submitError = 'Gagal menyiapkan kanvas gambar pada perangkat.';
                return;
            }

            // Mirror horizontal agar hasil selfie kamera depan sesuai dengan pratinjau cermin.
            context.save();
            context.translate(targetWidth, 0);
            context.scale(-1, 1);
            context.drawImage(video, 0, 0, targetWidth, targetHeight);
            context.restore();

            canvas.toBlob(
                (blob) => {
                    if (!blob) {
                        this.submitError = 'Gagal memproses foto selfie. Silakan tekan Ulangi dan coba kembali.';
                        return;
                    }

                    this.revokePreviewUrl();
                    this.capturedBlob = blob;
                    this.previewUrl = URL.createObjectURL(blob);
                    this.submitError = null;
                },
                'image/jpeg',
                this.jpegQuality,
            );
        },

        retake() {
            if (this.submitting) {
                return;
            }

            this.revokePreviewUrl();
            this.capturedBlob = null;
            this.submitError = null;
        },

        revokePreviewUrl() {
            if (this.previewUrl) {
                URL.revokeObjectURL(this.previewUrl);
                this.previewUrl = null;
            }
        },

        async submit() {
            if (this.submitting || !this.capturedBlob) {
                return;
            }

            if (this.lat === null || this.lng === null || this.accuracy === null) {
                this.submitError =
                    'Koordinat GPS belum diperoleh. Aktifkan lokasi perangkat dan tekan "Perbarui Lokasi" terlebih dahulu.';
                return;
            }

            const targetUrl = this.mode === 'check_out' ? this.checkOutUrl : this.checkInUrl;
            if (!targetUrl) {
                this.submitError = 'Alamat tujuan pengiriman absensi tidak ditemukan.';
                return;
            }

            this.submitting = true;
            this.submitError = null;

            const formData = new FormData();
            const fileName = this.mode === 'check_out' ? 'selfie-pulang.jpg' : 'selfie-masuk.jpg';

            formData.append('photo', this.capturedBlob, fileName);
            formData.append('lat', String(this.lat));
            formData.append('lng', String(this.lng));
            formData.append('accuracy', String(this.accuracy));
            formData.append('client_time', new Date().toISOString());

            try {
                const response = await fetch(targetUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                    body: formData,
                });

                const data = await response.json().catch(() => ({}));

                if (response.ok) {
                    this.submitSuccess = data.message || 'Absensi berhasil dicatat.';
                    this.stopCamera();
                    this.stopGeolocation();

                    if (data.redirect_url) {
                        window.location.assign(data.redirect_url);
                    }

                    return;
                }

                if (data?.errors && typeof data.errors === 'object') {
                    const messages = Object.values(data.errors).flat().filter(Boolean);
                    this.submitError =
                        messages[0] || data.message || 'Data absensi tidak valid. Silakan periksa kembali.';
                } else if (response.status === 429) {
                    this.submitError =
                        data?.message ||
                        'Terlalu banyak percobaan absen. Tunggu sebentar lalu coba kembali.';
                } else if (response.status === 419) {
                    this.submitError =
                        'Sesi keamanan halaman telah kedaluwarsa. Muat ulang halaman lalu coba kembali.';
                } else {
                    this.submitError =
                        data?.message || 'Gagal mencatat absensi. Silakan coba beberapa saat lagi.';
                }
            } catch {
                this.submitError =
                    'Gagal menghubungi server. Periksa koneksi internet Anda lalu coba kirim ulang.';
            } finally {
                this.submitting = false;
            }
        },
    };
}

export default attendanceCamera;
