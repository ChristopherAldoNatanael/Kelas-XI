# P2.1 — Laboratorium: Order, Spesimen, Hasil, Rentang Rujukan, dan Verifikasi

Status: **SELESAI (lapisan data + service)**  
Tanggal: 26 November 2026  
Migration: `2026_11_26_000011_create_laboratory_workflow_tables.php`

## 1. Tujuan

P2.1 memisahkan empat hal yang sebelumnya tercampur di `lab_results`:

1. Order pemeriksaan.
2. Daftar pemeriksaan yang benar-benar dipesan.
3. Spesimen fisik yang diambil.
4. Hasil per parameter dan verifikasinya.

Prinsip yang dipakai:

- Data `lab_results` lama tidak dihapus.
- Hasil legacy **tidak** dianggap terverifikasi.
- Rentang rujukan hanya dipakai bila benar-benar cocok; tidak ada nilai normal palsu.
- Verifikasi hanya oleh dokter.
- Hasil yang sudah final tidak dapat diedit; koreksi membuat baris baru.
- Semua tabel baru memakai `clinic_id` dan divalidasi di service.

## 2. Perubahan Skema

### 2.1 `lab_orders`

| Kolom | Keterangan |
|---|---|
| `clinic_id` | Isolasi tenant. |
| `medical_record_id` | Rekam medis terkait. |
| `encounter_id` | Encounter klinis. |
| `ordered_by`, `ordered_at` | Dokter dan waktu order. |
| `priority` | `routine`, `urgent`, `stat`. |
| `status` | `draft`, `ordered`, `partially_collected`, `completed`, `cancelled`. |
| `clinical_notes` | Alasan pemeriksaan. |

### 2.2 `lab_order_items`

| Kolom | Keterangan |
|---|---|
| `clinic_id`, `lab_order_id` | Tenant dan order induk. |
| `test_code`, `test_name` | Identitas pemeriksaan. |
| `specimen_type` | Jenis sampel yang diminta. |
| `status` | `ordered`, `collected`, `resulted`, `cancelled`. |
| `notes` | Instruksi khusus. |

Status item terpisah dari status order dan status hasil.

### 2.3 `lab_specimens`

| Kolom | Keterangan |
|---|---|
| `clinic_id`, `lab_order_id` | Tenant dan order induk. |
| `accession_number` | Unik per klinik. |
| `specimen_type` | Darah, urin, swab, jaringan, dll. |
| `collected_at`, `collected_by` | Waktu dan petugas pengambil. |
| `status` | `pending_collection`, `collected`, `received`, `rejected`, `processed`. |
| `rejection_reason` | Wajib bila ditolak. |

### 2.4 `lab_reference_ranges`

Kolom: `clinic_id`, `species`, `breed`, `sex`, `age_min_days`, `age_max_days`,
`test_code`, `parameter`, `unit`, `reference_low`, `reference_high`,
`reference_text`, `method`, `analyzer`, `specimen_type`, `effective_from`,
`effective_to`, `is_active`.

Index pencarian: `(clinic_id, species, test_code, parameter)`.

### 2.5 `lab_results` (additive)

Kolom baru:

- tenant/relasi: `clinic_id`, `lab_order_id`, `lab_order_item_id`, `lab_specimen_id`
- spesies dan rentang: `species`, `reference_low`, `reference_high`,
  `reference_text`, `reference_status`, `reference_range_id`
- status/waktu: `status`, `collected_at`, `resulted_at`, `verified_at`, `verified_by`
- metode: `method`
- temuan tambahan: `is_additional`, `additional_approved_by`
- koreksi/void: `correction_of_id`, `correction_reason`, `void_reason`,
  `voided_at`, `voided_by`

Status hasil: `pending`, `preliminary`, `verified`, `corrected`, `cancelled`,
`legacy`.

### 2.6 Penjaga database (MySQL trigger)

1. `lab_results_verified_immutable`
   - Hasil `verified`/`corrected` hanya boleh tetap atau `void`.
   - Isi klinis tidak boleh berubah.
   - `void` wajib beralasan dan tidak mengubah nilai.
2. `lab_results_legacy_no_verify`
   - `legacy` tidak boleh diubah langsung menjadi `verified`.
3. `lab_results_verified_no_delete`
   - Hasil `verified`/`corrected`/`legacy` tidak boleh dihapus.

## 3. Model

- `LabOrder`
- `LabOrderItem`
- `LabSpecimen`
- `LabReferenceRange`
- `LabResult` diperluas: konstanta status/reference, relasi order/item/spesimen/
  reference/verifier/koreksi, helper `isVerified()` dan `isLegacy()`.
- `MedicalRecord` mendapat relasi `labOrders()`.
- `ClinicalEncounter` mendapat relasi `labOrders()`.

## 4. Service

### 4.1 `LabReferenceService`

- Mencari rentang berdasarkan klinik, spesies, kode tes, dan parameter.
- Memfilter umur dari `date_of_birth` (atau `age` × 365), jenis kelamin,
  ras, metode, analyzer, jenis spesimen, dan masa berlaku.
- Memilih rentang paling spesifik, lalu yang paling baru berlaku.
- Bila tidak ada yang cocok:
  - `reference_status = reference_unavailable`
  - `reference_low`, `reference_high`, `reference_text`, `reference_range_id`
    bernilai `null`
  - `flag = null`
- Bila cocok:
  - mengisi rentang dan `reference_range_id`
  - menghitung `flag` dari nilai numerik: `low`, `normal`, `high`.
- Tidak pernah memakai rentang generik lintas spesies.

### 4.2 `LabOrderService`

- `create(record, data, actor)`
  - Membuat order + item dalam satu transaksi.
  - Minimal satu item.
  - Priority divalidasi.
- `collectSpecimen(order, data, actor)`
  - Membuat spesimen dengan accession unik per klinik.
  - Status awal `collected`.
- `transitionSpecimen(specimen, status, actor, reason)`
  - Menerima `received`, `rejected`, `processed`.
  - Penolakan wajib alasan.
- `markItemCollected(item, actor)`
- `recordResult(order, item, data, actor)`
  - Hasil wajib menunjuk item pada order yang sama.
  - Spesimen opsional harus milik order yang sama.
  - Menghitung rentang dan flag.
  - Status hasil awal `preliminary`.
  - Item menjadi `resulted`.
  - Status order dihitung ulang.
  - Temuan tambahan (`is_additional`) hanya boleh disetujui dokter.
- `verifyResult(result, actor)`
  - Hanya dokter.
  - `legacy` ditolak dengan `LAB_RESULT_LEGACY_REQUIRES_CORRECTION`.
  - Hasil final tidak dapat diverifikasi ulang.
  - Mengisi `verified_by`, `verified_at`, status `verified`.
- `correctResult(original, data, reason, actor)`
  - Hanya dokter.
  - Membuat baris baru berstatus `corrected`, `correction_of_id` menunjuk
    baris lama, dengan alasan.
  - Baris lama tidak berubah.
- Penjaga klinik: aktor dari klinik lain mendapat `404 LAB_CLINIC_MISMATCH`.

## 5. Status dan Aturan Transisi

- Status order: `draft` → `ordered` → `partially_collected` → `completed`
  (atau `cancelled`).
- Status item: `ordered` → `collected` → `resulted` (atau `cancelled`).
- Status spesimen: `pending_collection` → `collected` → `received` → `processed`
  (atau `rejected`).
- Status hasil: `pending`/`preliminary` → `verified`; koreksi menghasilkan
  `corrected`; data lama `legacy`.
- Satu status tidak dipakai untuk mewakili status lain.

## 6. Integrasi Rekam Medis

- `MedicalRecord::computeContentHash()` sekarang memuat ringkasan `lab_results`
  termasuk status verifikasi, rentang, flag, dan koreksi.
- `MedicalRecordVersionService::buildSnapshot()` memuat detail hasil lab baru
  dan schema version dinaikkan ke `1.2`.
- `MedicalRecordRevisionService::snapshot()` memuat `lab_results`.
- Dengan demikian finalisasi rekam medis tidak mengabaikan hasil laboratorium.

## 7. Backfill

```bash
php artisan lab:backfill-results --dry-run
php artisan lab:backfill-results
```

Perilaku:

- Hanya memproses `lab_results` lama dengan `clinic_id` null dan tanpa order.
- Mengisi `clinic_id` dari rekam medis.
- Mengisi `species` dari hewan.
- Menetapkan `status = legacy`.
- Menetapkan `reference_status = reference_unavailable`.
- Tidak membuat order, item, atau spesimen palsu.
- Tidak menandai hasil lama sebagai `verified`.

Hasil pada DB `pet_heal`: 0 hasil lab lama, 0 baris diubah.

## 8. Pengujian

```bash
DB_DATABASE=pet_heal_test php artisan test \
  tests/Feature/P03LifecycleAndAuditChainTest.php \
  tests/Feature/P21LaboratoryWorkflowTest.php
```

Hasil:

- **13 passed, 81 assertions**.
- `P21LaboratoryWorkflowTest`: **6 passed, 38 assertions**.
  - Order dengan dua item, spesimen, dan status terpisah.
  - Hasil dengan rentang cocok menghasilkan flag `high` dan verifikasi dokter.
  - Admin tidak dapat memverifikasi.
  - Tanpa rentang cocok: `reference_unavailable`, rentang null, flag null.
  - Hasil legacy tidak dapat diverifikasi; koreksi membuat baris baru dan
    baris lama tetap `legacy`.
  - Database menolak perubahan hasil `verified` dan promosi `legacy` → `verified`.
  - Temuan tambahan harus disetujui dokter.
- `P03LifecycleAndAuditChainTest`: **7 passed, 43 assertions** (regresi lifecycle
  dan audit chain tetap lulus).
- `php -l` bersih.
- Migration berhasil di `pet_heal_test` dan `pet_heal`.

**Catatan jujur:** full test suite tidak dijalankan ulang pada putaran ini.
Yang dijalankan hanya tes tertarget di atas.

## 9. Batasan yang Masih Terbuka

- Belum ada route/controller/UI order laboratorium, pengambilan spesimen,
  input hasil, verifikasi, atau koreksi.
- Belum ada API `lab_orders` di payload pemilik. Penambahan top-level key akan
  memutus kontrak Android lama, jadi perlu fase kontrak terpisah.
- Belum ada layar master `lab_reference_ranges`.
- Belum ada cetak label barcode spesimen.
- Belum ada integrasi analyzer/HL7 dan notifikasi nilai kritis.
- Belum ada workflow persetujuan temuan tambahan di UI.
- Backfill tidak dapat mengembalikan verifikasi lama yang tidak pernah tercatat;
  hasil lama tetap `legacy` sampai dikoreksi dokter.

## 10. Keputusan Penting

- Hasil laboratorium tidak boleh ada tanpa item order, kecuali temuan tambahan
  yang disetujui dokter.
- `legacy` adalah status final historis, bukan status yang bisa diverifikasi.
- Koreksi selalu baris baru; data lama tidak ditimpa.
- Rentang rujukan wajib berasal dari klinik yang sama.
- Bila tidak ada rentang cocok, sistem tidak menampilkan nilai normal.
- Status order, item, spesimen, dan hasil tidak disatukan dalam satu kolom.
