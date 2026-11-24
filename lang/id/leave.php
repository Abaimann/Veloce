<?php

declare(strict_types=1);

return [
    'statuses' => [
        'pending' => 'Menunggu Persetujuan',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
        'cancelled' => 'Dibatalkan',
    ],
    'approval_statuses' => [
        'pending' => 'Menunggu',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
    ],
    'messages' => [
        'submitted' => 'Pengajuan :type berhasil dikirim untuk diproses.',
        'approved_step' => 'Pengajuan diteruskan ke tahap persetujuan berikutnya.',
        'approved_final' => 'Pengajuan telah disetujui secara final.',
        'rejected' => 'Pengajuan telah ditolak.',
        'cancelled' => 'Pengajuan berhasil dibatalkan.',
        'no_workdays' => 'Rentang tanggal yang dipilih tidak mengandung hari kerja efektif (hanya akhir pekan atau hari libur).',
        'cross_year_not_allowed' => 'Pengajuan cuti/izin tidak boleh melintasi pergantian tahun. Ajukan secara terpisah per tahun.',
        'attachment_required' => 'Jenis pengajuan :type wajib melampirkan dokumen pendukung.',
        'overlap_exists' => 'Sudah ada pengajuan aktif atau disetujui pada rentang tanggal tersebut.',
        'attendance_exists' => 'Terdapat catatan absensi masuk/pulang pada salah satu tanggal dalam rentang yang dipilih.',
        'insufficient_balance' => 'Saldo cuti tahunan tidak mencukupi (tersedia :available hari, dibutuhkan :required hari).',
        'cannot_self_approve' => 'Anda tidak diperbolehkan menyetujui pengajuan Anda sendiri.',
        'cannot_cancel' => 'Hanya pengajuan berstatus menunggu persetujuan yang dapat dibatalkan.',
        'already_finalized' => 'Pengajuan ini sudah diproses sebelumnya.',
    ],
];
