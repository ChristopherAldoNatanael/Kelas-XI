# P2.2 — Consent, Kesejahteraan Hewan, dan Alur Darurat

Status: **SELESAI (lapisan data + service)**  
Tanggal: 26 November 2026  
Migration: `2026_11_26_000012_create_consent_templates_and_welfare.php`

## 1. Tujuan

P2.2 menutup tiga area:

1. **Consent tindakan** yang punya template berversi, hash teks, bukti, wali,
   dan pencabutan yang tercatat.
2. **Penilaian kesejahteraan hewan** berulang memakai lima kebebasan.
3. **Encounter darurat** tanpa booking, dengan dasar tindakan yang eksplisit
   dan **tanpa** memberikan kewenangan kredensial baru.

Prinsip:

- Consent tidak dihapus saat dicabut.
- Template tidak diedit di tempat; perubahan membuat versi baru.
- Skor kesejahteraan dihitung server-side.
- Darurat mencatat fakta klinis, bukan mengubah SIP/SIPP.
- Data lama tetap dipertahankan.

## 2. Perubahan Skema

### 2.1 Tabel baru `consent_templates`

| Kolom | Keterangan |
|---|---|
| `clinic_id` | Isolasi tenant. |
| `template_key` | Identitas stabil template. |
| `name`, `consent_type` | Nama dan jenis consent. |
| `version` | Versi immutable. |
| `body`, `body_hash` | Teks dan SHA-256 teks. |
| `is_current` | Hanya satu versi aktif per key. |
| `active_key` | Unik saat aktif; NULL untuk versi lama. |
| `created_by` | Pembuat. |

Unique: `(clinic_id, template_key, version)`.

### 2.2 Tabel baru `animal_welfare_assessments`

| Kolom | Keterangan |
|---|---|
| `clinic_id`, `medical_record_id`, `encounter_id` | Tenant dan relasi klinis. |
| `freedom_hunger_thirst` | Skor 1–5. |
| `freedom_discomfort` | Skor 1–5. |
| `freedom_pain_injury_disease` | Skor 1–5. |
| `freedom_fear_distress` | Skor 1–5. |
| `freedom_natural_behavior` | Skor 1–5. |
| `overall_score` | Jumlah skor, dihitung server. |
| `notes` | Temuan. |
| `assessed_by`, `assessed_at` | Pelaku dan waktu. |

Setiap penilaian baru adalah baris baru.

### 2.3 `treatment_consents` (additive)

Kolom baru:

- `clinic_id`
- `template_key`, `template_version`
- `consent_text_hash`
- `evidence_path`, `evidence_hash`
- `guardian_name`, `guardian_relation`
- `emergency_basis`
- `revoked_at`, `revoked_reason`, `revoked_by`

Kolom lama seperti `signature_path`, `signed_at`, `witness_name`, dan
`ip_address` tetap dipertahankan.

### 2.4 `consents` PDP (additive)

- `policy_hash`
- `withdrawn_at`
- `withdrawal_reason`
- `evidence_path`

`revoked_at` lama tetap diisi bersamaan agar kode lama tetap bekerja.

## 3. Model

- `ConsentTemplate`
- `AnimalWelfareAssessment`
- `TreatmentConsent` diperluas: tipe consent, dasar darurat, hash, bukti, wali,
  pencabutan, helper `isRevoked()` dan `isEmergency()`.
- `Consent` diperluas: `policy_hash`, `withdrawn_at`, alasan, bukti, helper
  `isWithdrawn()`.
- `MedicalRecord` mendapat relasi `welfareAssessments()`.
- `ClinicalEncounter` mendapat relasi `welfareAssessments()`.

## 4. Service

### 4.1 `ConsentService`

- `publishTemplate()`
  - Menonaktifkan versi lama dalam transaksi.
  - Membuat versi baru dengan hash SHA-256.
  - `active_key` unik mencegah dua versi aktif.
- `createTreatmentConsent()`
  - Wajib klinik, tipe valid, dan nama penanda tangan.
  - Bila memakai template, hash dan versi template disimpan.
  - Mendukung wali hukum (`guardian_name`, `guardian_relation`).
  - Bukti tanda tangan/gambar disimpan sebagai path + hash.
- `recordEmergencyConsent()`
  - Wajib `emergency_basis`.
  - Jenis consent `darurat`.
  - Tidak mengubah kredensial tenaga.
- `revoke()`
  - Wajib alasan.
  - Tidak menghapus consent atau bukti.
  - Pencabutan ganda ditolak.
- `grantPdpConsent()`
  - Hanya pemilik atau admin.
  - Menyimpan versi kebijakan dan hash teks kebijakan.
- `withdrawPdpConsent()`
  - Hanya pemilik atau admin.
  - Mengisi `withdrawn_at`, `revoked_at`, dan alasan.
  - Riwayat tetap ada.

### 4.2 `AnimalWelfareService`

- `assess(record, data, actor)`
  - Lima skor wajib angka 1–5.
  - `overall_score` dihitung server.
  - Penilaian baru tidak menimpa penilaian lama.
- `assessEncounter(encounter, data, actor)`
  - Memerlukan rekam medis pada encounter tersebut.
- `seriesForRecord()` dan `latestForRecord()`.
- Penjaga klinik: `404 WELFARE_CLINIC_MISMATCH`.

### 4.3 `EmergencyEncounterService`

- `open(clinic, pet, data, actor)`
  - Membuat `clinical_encounters` tipe `emergency`.
  - `booking_id` selalu NULL.
  - Triage wajib: `red`, `orange`, `yellow`, atau `green`.
  - Dokter penanggung jawab harus berada di klinik yang sama.
  - **Tidak** mengubah `credential_status`, SIP, STR, atau SIPP.
- `updateTriage()`
  - Hanya untuk encounter darurat.
- `close()`
  - Mengisi disposisi, status `completed`, dan `closed_at`.

## 5. Integrasi Rekam Medis

- `MedicalRecord::computeContentHash()` memuat consent tindakan dan penilaian
  kesejahteraan.
- `MedicalRecordVersionService::buildSnapshot()` memuat:
  - detail consent (template, hash, wali, dasar darurat, bukti, pencabutan)
  - seluruh penilaian kesejahteraan
- Schema version snapshot dinaikkan ke `1.3`.
- `MedicalRecordRevisionService::snapshot()` memuat consent dan kesejahteraan.

Dengan begitu finalisasi rekam medis tidak mengabaikan consent atau penilaian
kesejahteraan.

## 6. Backfill

```bash
php artisan consents:backfill-treatment --dry-run
php artisan consents:backfill-treatment
```

- Mengisi `clinic_id` consent tindakan lama dari rekam medis.
- Tidak mengarang template, hash, bukti, atau tanda tangan.
- Hasil pada DB `pet_heal`: 0 consent lama, 0 baris diubah.

## 7. Pengujian

```bash
DB_DATABASE=pet_heal_test php artisan test \
  tests/Feature/P03LifecycleAndAuditChainTest.php \
  tests/Feature/P22ConsentWelfareEmergencyTest.php
```

Hasil:

- **12 passed, 92 assertions**.
- `P22ConsentWelfareEmergencyTest`: **5 passed, 49 assertions**.
  - Template v1 → v2: versi lama nonaktif, `active_key` NULL, hash teks benar.
  - Consent tindakan menyimpan versi template, hash, bukti; pencabutan
    beralasan dan tidak menghapus bukti; pencabutan ganda ditolak.
  - Kesejahteraan dapat dinilai berulang; skor total dan rata-rata benar;
    skor 6 ditolak.
  - Encounter darurat tanpa booking, triage wajib, kredensial dokter tetap
    `expired`, consent darurat wajib dasar, penutupan encounter benar.
  - PDP consent menyimpan hash kebijakan; pencabutan mengisi `withdrawn_at`
    dan `revoked_at`; consent aktif berkurang; pencabutan ganda ditolak.
  - Dokter dari klinik lain ditolak pada encounter darurat.
- `P03LifecycleAndAuditChainTest`: **7 passed, 43 assertions**.
- `php -l` bersih.
- Migration berhasil di `pet_heal_test` dan `pet_heal`.

**Catatan jujur:** full test suite tidak dijalankan ulang pada putaran ini.
Yang dijalankan hanya tes tertarget di atas.

## 8. Batasan yang Masih Terbuka

- Belum ada route/controller/UI untuk template consent, consent tindakan,
  kesejahteraan, atau encounter darurat.
- Belum ada unggah berkas bukti; service menerima path dan hash, tetapi
  penyimpanan aman/signed URL belum dibuat.
- Belum ada API pemilik untuk consent/kesejahteraan karena penambahan
  top-level key akan memutus kontrak Android lama.
- Belum ada alur persetujuan/penolakan credential exception khusus darurat;
  P1.1 sudah menyediakan tabel exception, tetapi UI-nya belum ada.
- Belum ada laporan tren kesejahteraan atau notifikasi penurunan skor.
- Triage hanya divalidasi pada level service; belum ada protokol klinis
  otomatis.

## 9. Keputusan Penting

- Template consent immutable per versi; koreksi teks = versi baru.
- Consent dicabut dengan pencatatan, bukan dihapus.
- Hash teks consent disimpan agar teks yang disetujui dapat dibuktikan.
- Kesejahteraan selalu baris baru; tidak ada penimpaan penilaian lama.
- Darurat tidak pernah menjadi jalur pintas kredensial.
- `booking_id` encounter darurat selalu NULL.
- `withdrawn_at` dan `revoked_at` PDP diisi bersamaan untuk kompatibilitas.
