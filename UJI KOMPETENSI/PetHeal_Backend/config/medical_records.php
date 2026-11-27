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
    | Lifecycle, versi, dan amendment
    |--------------------------------------------------------------------------
    |
    | Semua flag default OFF. Nyalakan per fase setelah acceptance test lulus.
    | Saat lifecycle aktif, hanya dokter yang dapat memfinalkan rekam medis.
    |
    */
    'encounters_enabled' => (bool) env('MEDICAL_RECORDS_ENCOUNTERS_ENABLED', true),
    'lifecycle_enforcement_enabled' => (bool) env('MEDICAL_RECORDS_LIFECYCLE_ENFORCEMENT_ENABLED', false),
    'amendments_enabled' => (bool) env('MEDICAL_RECORDS_AMENDMENTS_ENABLED', false),
    'credential_gate_enabled' => (bool) env('MEDICAL_RECORDS_CREDENTIAL_GATE_ENABLED', false),

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
    | Enkripsi lampiran at-rest
    |--------------------------------------------------------------------------
    |
    | Lampiran baru disimpan dengan AES-256-GCM. Kunci memakai APP_KEY bila
    | MEDICAL_RECORDS_ATTACHMENT_KEY tidak diisi. Berkas lama tanpa header
    | PHRM1 tetap dibaca sebagai plaintext agar arsip tidak terputus.
    |
    */
    'attachment_encryption_enabled' => (bool) env('MEDICAL_RECORDS_ATTACHMENT_ENCRYPTION_ENABLED', true),
    'attachment_encryption_key' => env('MEDICAL_RECORDS_ATTACHMENT_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Kebijakan privasi
    |--------------------------------------------------------------------------
    */
    'policy_version' => env('MEDICAL_RECORDS_POLICY_VERSION', '2026-01'),

    /*
    |--------------------------------------------------------------------------
    | Pelaporan penyakit menular
    |--------------------------------------------------------------------------
    |
    | Tenggat laporan mengikuti kewajiban 24 jam. Nilai ini tidak boleh
    | diperpanjang diam-diam; perubahan harus melalui keputusan tertulis.
    |
    */
    'notifiable_report_deadline_hours' => (int) env('MEDICAL_RECORDS_NOTIFIABLE_DEADLINE_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Permintaan PDP
    |--------------------------------------------------------------------------
    |
    | SLA internal untuk menanggapi permintaan akses/koreksi/ekspor. Nilai ini
    | bukan pengganti tenggat hukum yang berlaku dan wajib ditinjau legal.
    |
    */
    'privacy_request_due_days' => (int) env('MEDICAL_RECORDS_PRIVACY_DUE_DAYS', 14),

    /*
    |--------------------------------------------------------------------------
    | Alert health check
    |--------------------------------------------------------------------------
    |
    | Webhook menerima JSON ringkasan tanpa data pasien. Token opsional dikirim
    | sebagai Bearer dan tidak pernah dicatat ke log.
    |
    */
    'health_alert_enabled' => (bool) env('MEDICAL_RECORDS_HEALTH_ALERT_ENABLED', true),
    'health_alert_webhook' => env('MEDICAL_RECORDS_HEALTH_ALERT_WEBHOOK', ''),
    'health_alert_token' => env('MEDICAL_RECORDS_HEALTH_ALERT_TOKEN', ''),
    'health_alert_timeout' => (int) env('MEDICAL_RECORDS_HEALTH_ALERT_TIMEOUT', 5),
    'health_extra_required_triggers' => [],
];
