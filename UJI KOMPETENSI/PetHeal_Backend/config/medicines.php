<?php

/**
 * Konfigurasi inventaris obat.
 *
 * Ambang peringatan kedaluwarsa TIDAK di-hardcode di model/controller.
 * Nilai default bisa diubah lewat .env atau AppSetting (runtime, tanpa deploy)
 * dengan key `medicine_expiry_warning_days`.
 */
return [
    // Berapa hari sebelum ED sebuah batch mulai ditandai "hampir kedaluwarsa".
    'expiry_warning_days' => (int) env('MEDICINE_EXPIRY_WARNING_DAYS', 60),

    // Tempat penyimpanan bukti retur / berita acara pemusnahan.
    'document_disk' => 'public',
    'document_dir' => 'medicine-mutations',

    // Batas upload dokumen pendukung (KB).
    'document_max_kb' => 4096,
];
