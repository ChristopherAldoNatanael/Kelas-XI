# P1.2 — Farmasi: Resep, Dispensing, Stok, Reversal, Dosis, dan Registrasi Obat

Status: **SELESAI (lapisan data + service)**  
Tanggal: 26 November 2026  
Migration: `2026_11_26_000009_create_pharmacy_dispensing_tables.php`

## 1. Tujuan

P1.2 menutup celah farmasi yang sebelumnya hanya berupa `prescription_items`
dan pemotongan stok otomatis:

- Resep memiliki header (`prescriptions`) dan nomor resep.
- Penyerahan obat tercatat sebagai transaksi (`dispensations`) beserta item per batch.
- Double submit dicegah dengan idempotency key.
- Pembatalan/penyerahan salah tidak menghapus histori; dipakai reversal.
- Stok selalu berubah melalui `MedicineStockService` (ledger append-only).
- Obat memiliki data registrasi (nomor, status, masa berlaku, spesies, withdrawal).
- Dosis diperiksa terhadap `dose_references` milik klinik, **non-blocking**, dengan alasan override.
- Semua tabel baru memakai `clinic_id` dan foreign key komposit/tenant-scoped.

## 2. Perubahan Skema

### 2.1 `medicines` (additive)

| Kolom | Tipe | Keterangan |
|---|---|---|
| `registration_number` | varchar(80) nullable | Nomor registrasi Kementan/BPOM sesuai dokumen. |
| `registration_status` | varchar(20) default `unknown` | `registered`, `not_registered`, `unknown`, `expired`, `withdrawn`. |
| `registration_expires_at` | date nullable | Masa berlaku registrasi. |
| `approved_species` | varchar(255) nullable | Spesies yang disetujui pada label. |
| `withdrawal_period` | varchar(80) nullable | Masa henti obat (withdrawal period). |

Index: `medicines_clinic_reg_status_idx (clinic_id, registration_status)`.

### 2.2 Tabel baru

- `prescriptions` — header resep per rekam medis, nomor unik per klinik,
  `status` (`draft`, `issued`, `partially_dispensed`, `dispensed`, `cancelled`),
  `prescribed_by`, `prescribed_at`, `notes`.
- `dispensations` — transaksi penyerahan/reversal. Kolom penting:
  `kind` (`dispense`, `reversal`), `status` (`completed`, `partially_reversed`,
  `reversed`), `idempotency_key` unik, `reversal_of_id`, `reason`.
- `dispensation_items` — baris obat per batch, termasuk snapshot nama/batch/expiry/harga,
  `stock_movement_id`, `reversal_of_item_id`, `original_batch_id`, `reversed_quantity`,
  `registration_status_snapshot`, `eligibility_override_reason`.
- `dose_references` — referensi dosis per klinik dan spesies; mendukung
  `dose_basis` (`fixed`, `mg_per_kg`, dll.), `dose_min`, `dose_max`, `max_dose`,
  `source`, `effective_from`, `is_active`.

### 2.3 `prescription_items` (additive)

`prescription_id`, `dose_value`, `dose_unit`, `dose_basis`,
`weight_at_prescription`, `concentration`, `calculated_dose`,
`dose_check_status` (`ok`, `warning`, `overridden`, `not_checked`),
`dose_check_notes`, `dosage_form`, `strength`, `total_quantity`,
`withdrawal_period`, `dose_reference_id`, `dose_reference_source`.

### 2.4 `medicine_stock_movements` (additive)

`dispensation_item_id`, `reference_type`, `reference_id`,
`idempotency_key` (unik), `reversal_of_movement_id`, `movement_kind`, `notes`.

Dengan ini setiap pergerakan stok dapat dilacak ke dispensasi dan item asal.

## 3. Service dan Alur

### 3.1 `DoseCheckService`

- Hanya membaca `dose_references` aktif milik klinik yang sama.
- Mencocokkan nama obat, atau `drug_class` bila tersedia.
- Menghitung `calculated_dose` untuk basis `mg_per_kg` memakai berat saat resep
  (`weight_at_prescription`) atau berat hewan.
- Status `warning` bila di luar `dose_min`/`dose_max` atau melebihi `max_dose`.
- **Tidak memblokir** penyimpanan resep.
- Bila tidak ada referensi: `not_checked` dengan catatan eksplisit.
- Tidak memakai angka generik lintas spesies.

### 3.2 `PrescriptionService` (kompatibilitas)

Alur lama tetap dipertahankan agar Android/admin tidak berubah:

- Header `prescriptions` dibuat otomatis per rekam medis.
- Item resep baru mengisi kolom dosis, total quantity, withdrawal, dan hasil dose check.
- Bila status `warning` dan pengguna mengisi `dose_check_notes`, status menjadi
  `overridden` dan alasan tersimpan.
- Stok masih dipotong melalui `consumePrescription()` seperti sebelumnya.
- Header otomatis ditandai `dispensed` sebagai proyeksi historis alur lama.

### 3.3 `DispensationService`

`dispense(Prescription, items[], idempotencyKey, actor)`:

1. Validasi idempotency key (wajib, maksimal 80 karakter).
2. Bila key sudah ada, kembalikan transaksi lama tanpa efek samping.
3. Transaksi DB + row lock pada resep.
4. Tolak resep `cancelled`.
5. Validasi item milik resep, sisa quantity, dan stok.
6. Cek registrasi obat:
   - `registered` + belum kedaluwarsa → boleh.
   - Status lain → `MEDICINE_NOT_ELIGIBLE` (HTTP 422), **kecuali** aktor berperan
     `doctor` dan mengisi `eligibility_override_reason`.
7. Batch dipilih FEFO (expiry terdekat, batch tanpa expiry di akhir), hanya batch
   stok > 0 dan belum kedaluwarsa.
8. Setiap pengambilan batch menghasilkan satu `dispensation_items` dan satu
   pergerakan stok keluar dengan idempotency key turunan.
9. Status resep dihitung ulang: `partially_dispensed` atau `dispensed`.

`reverse(Dispensation, items[], idempotencyKey, reason, actor)`:

1. Hanya dispensasi `kind=dispense` yang dapat dibalik.
2. Idempotent: key sama mengembalikan reversal yang sudah ada.
3. Stok masuk kembali ke **batch asal**, bukan batch baru.
4. Item asli tidak dihapus; `reversed_quantity` diisi.
5. `dispensation_items` reversal menyimpan `reversal_of_item_id` dan
   `original_batch_id`.
6. Status dispensasi asli menjadi `partially_reversed` atau `reversed`.
7. Status resep dihitung ulang.

Semua perubahan stok tetap melalui `MedicineStockService`, sehingga ledger,
hash/jejak audit, dan aturan stok tidak dilewati.

## 4. Backfill

Command baru:

```bash
php artisan pharmacy:backfill-prescriptions --dry-run
php artisan pharmacy:backfill-prescriptions
```

- Mengelompokkan `prescription_items` lama yang belum punya `prescription_id`.
- Membuat header `prescriptions` bertanda `dispensed` karena stoknya sudah
  dipotong oleh alur lama.
- Idempotent: item yang sudah tertaut tidak disentuh.

Hasil pada DB `pet_heal`:

- 3 rekam medis memiliki item resep tanpa header.
- 3 header dibuat.
- 6 item ditautkan.
- 0 item orphan.
- Semua 76 obat lama berstatus `unknown` (belum diverifikasi), bukan otomatis
  dianggap terdaftar.

## 5. UI Registrasi Obat

Form tambah/edit obat admin mendapat bagian **Registrasi Obat**:

- Nomor registrasi.
- Status registrasi.
- Berlaku sampai.
- Spesies yang disetujui.
- Masa henti obat (withdrawal).

Validasi server ditambahkan pada `MedicineController`. Status kosong disimpan
sebagai `unknown`.

## 6. Pengujian

Perintah yang dijalankan:

```bash
DB_DATABASE=pet_heal_test php artisan test \
  tests/Feature/MedicineStockTest.php \
  tests/Feature/P12PharmacyDispensingTest.php
```

Hasil:

- **29 passed, 104 assertions**.
- `P12PharmacyDispensingTest`: 4 passed, 30 assertions.
  - Dispensing idempotent, tidak double deduct, menolak sisa berlebih.
  - Reversal memakai batch asal dan idempotent.
  - Status registrasi memblokir, override dokter tercatat, registrasi kedaluwarsa ditolak.
  - Dose check non-blocking dan alasan override tersimpan.
- `MedicineStockTest`: 25 passed, 74 assertions (regresi alur stok lama).

`php -l` bersih untuk seluruh file PHP baru/berubah. `php artisan view:cache`
berhasil (Blade valid).

**Catatan jujur:** full test suite tidak dijalankan ulang pada putaran ini.
Yang dijalankan hanya tes tertarget di atas, sesuai instruksi agar tidak
berulang kali menjalankan seluruh suite.

## 7. Batasan yang Masih Terbuka

- Belum ada route/controller/UI dispensing dan reversal; saat ini service siap
  dipanggil dari layar farmasi berikutnya.
- Belum ada layar master `dose_references`; data masih diisi lewat seeder/DB
  atau akan menyusul pada UI formularium.
- Import obat massal belum mengisi kolom registrasi baru.
- Obat lama berstatus `unknown`; perlu verifikasi manual sebelum penyerahan
  memakai alur baru (atau override dokter dengan alasan).
- Belum ada laporan kepatuhan registrasi/expired otomatis.
- Tanda tangan/apoteker penanggung jawab belum dipisahkan dari aktor user.

## 8. Keputusan Penting

- Idempotency key wajib; tanpa key transaksi ditolak.
- Reversal tidak menghapus data dan tidak membuat batch baru.
- Dose check tidak pernah memblokir; hanya memberi peringatan dan alasan override.
- Registrasi obat diperiksa saat dispensing, bukan saat resep ditulis, agar
  dokter tetap dapat meresepkan sambil mencatat alasan.
- Override registrasi hanya untuk peran dokter, bukan admin.
- Backfill tidak mengarang nomor registrasi atau status terdaftar.
