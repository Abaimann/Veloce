<?php

declare(strict_types=1);

return [
    'statuses' => [
        'present' => 'Hadir',
        'late' => 'Terlambat',
        'wfh' => 'WFH',
        'leave' => 'Cuti',
        'sick' => 'Sakit',
        'permission' => 'Izin',
    ],
    'flags' => [
        'low_gps_accuracy' => 'Akurasi GPS rendah (> :meters m)',
        'client_clock_skew' => 'Waktu perangkat selisih > :minutes menit dari server',
        'duplicate_selfie_hash' => 'Foto selfie terdeteksi identik dengan absensi sebelumnya',
    ],
    'messages' => [
        'check_in_success' => 'Absen masuk berhasil dicatat pada :time WIB.',
        'check_out_success' => 'Absen pulang berhasil dicatat pada :time WIB.',
        'already_checked_in' => 'Anda sudah melakukan absen masuk hari ini.',
        'must_check_in_first' => 'Anda harus melakukan absen masuk terlebih dahulu sebelum absen pulang.',
        'already_checked_out' => 'Anda sudah menyelesaikan absen masuk dan absen pulang hari ini.',
        'blocked_by_leave' => 'Absensi tidak dapat dilakukan karena hari ini Anda tercatat :status yang sudah disetujui.',
        'blocked_non_workday' => 'Hari ini bukan hari kerja terjadwal Anda (libur/akhir pekan).',
        'outside_geofence' => 'Anda berada di luar radius kantor (:distance m dari :office, batas :radius m) dan tidak memiliki persetujuan WFH hari ini.',
        'no_active_office' => 'Belum ada lokasi kantor aktif yang dikonfigurasi. Hubungi Super Admin.',
        'rate_limited' => 'Terlalu banyak percobaan absen. Tunggu sebentar lalu coba kembali.',
    ],
];
