<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Penayangan isi klinis
    |--------------------------------------------------------------------------
    |
    | Secara default pemilik selalu dapat melihat diagnosa, terapi, dan jadwal
    | kontrol. Klinik yang memang ingin menahan detail sampai biaya tambahan
    | lunas dapat menyalakannya, tetapi tanggal kontrol tetap ditampilkan
    | karena merupakan instruksi medis.
    |
    */
    'gate_clinical_content' => (bool) env('MEDICAL_RECORDS_GATE_CLINICAL', false),

    /*
    |--------------------------------------------------------------------------
    | Retensi dokumen
    |--------------------------------------------------------------------------
    |
    | Rekam medis tidak dihapus. Setelah melewati masa retensi, rekaman
    | dipindahkan ke status arsip oleh command `medical-records:archive`.
    |
    */
    'retention_years' => (int) env('MEDICAL_RECORDS_RETENTION_YEARS', 5),
    'financial_retention_years' => (int) env('MEDICAL_RECORDS_FINANCIAL_RETENTION_YEARS', 10),

    /*
    |--------------------------------------------------------------------------
    | Lampiran
    |--------------------------------------------------------------------------
    */
    'attachment_disk' => env('MEDICAL_RECORDS_ATTACHMENT_DISK', 'local'),
    'attachment_url_ttl_minutes' => (int) env('MEDICAL_RECORDS_ATTACHMENT_URL_TTL', 30),
    'attachment_max_total_mb' => (int) env('MEDICAL_RECORDS_ATTACHMENT_MAX_TOTAL_MB', 200),

    /*
    |--------------------------------------------------------------------------
    | Kebijakan privasi
    |--------------------------------------------------------------------------
    */
    'policy_version' => env('MEDICAL_RECORDS_POLICY_VERSION', '2026-01'),
];
