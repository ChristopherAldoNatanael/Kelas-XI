# Implementasi Peningkatan Rekam Medis Hewan

Dokumen ini mencatat apa yang benar-benar diubah pada PetHeal_Backend setelah
audit `docs/AUDIT_REKAM_MEDIS_DETAIL.md`, alasan di balik keputusan yang
mengubah perilaku lama, serta batas pekerjaan yang masih terbuka. Tujuannya
agar siapa pun yang melanjutkan tidak mengulang analisis atau, lebih buruk,
mengembalikan perilaku yang sengaja dihapus.

Terakhir diperbarui: 26 November 2026.
Status test: `php artisan test` — 572 test lulus (3.452 assertion).

---

## 1. Ringkasan perubahan

| Area | Sebelum | Sesudah |
|------|---------|---------|
| Lampiran | Disk `public`, URL `/storage/...` tanpa autentikasi | Disk privat, unduh lewat URL bertanda tangan berumur pendek, hash SHA-256 |
| Harga obat | `unit_price` dari input klien | Harga diambil dari master `medicines.price` di server |
| Hapus rekam medis | `delete()` permanen + file dihapus | Status `void`, isi dan lampiran tetap disimpan |
| Jejak perubahan | Hanya sebagian aksi admin, tanpa nilai sebelum/sesudah | Tabel revisi dengan snapshot sebelum/sesudah, alasan, IP, user agent |
| Status dokumen | Tidak ada | `final`, `void`, `archived`; satu booking satu rekam medis (unique index) |
| Isi klinis | Disembunyikan bila biaya tambahan belum lunas | Selalu ditampilkan; status tagihan tidak lagi mengunci diagnosis |
| Resep | Teks bebas | Item terstruktur: dosis, rute, frekuensi, durasi, batch, kedaluwarsa, antibiotik |
| Tanda vital | Tidak ada | Tabel `medical_record_vitals` dan form di admin/dokter |
| Identitas legal | Tidak ada | STR/SIP dokter, nomor izin klinik, microchip hewan |
| Resume medis | Tidak ada | PDF resmi dengan kop klinik, tanda tangan, cap isi, tautan verifikasi |
| Retensi | Tidak ada | Command arsip bulanan, tidak menghapus data |

---

## 2. Migrasi basis data

Dijalankan berurutan pada `2026_11_24_000001` sampai `000008`:

1. `add_document_lifecycle_to_medical_records`
   Menambah `status`, `created_by`, `updated_by`, `finalized_at`, `finalized_by`,
   `voided_at`, `voided_by`, `void_reason`, `content_hash`, `archived_at`,
   `outcome`, `cause_of_death`, `euthanasia_reason`, `disposal_method`.
   Menambah unique index `medical_records.booking_id` dan index status/tanggal.
2. `create_medical_record_revisions_table`
   `medical_record_id`, `changed_by`, `action`, `before` (JSON), `after` (JSON),
   `reason`, `ip_address`, `user_agent`.
3. `add_integrity_to_medical_record_attachments`
   `sha256`, `deleted_at` (soft delete).
4. `add_license_identity_to_doctors_and_clinics`
   Dokter: `str_number`, `str_expiry`, `sip_number`, `sip_expiry`,
   `signature_path`. Klinik: `license_number`, `license_expiry`,
   `responsible_doctor_id`.
5. `add_clinical_fields_to_prescription_items`
   `medicines.drug_class`, `medicines.is_antibiotic`;
   `prescription_items.batch_number`, `expiry_date`, `route`, `frequency`,
   `duration_days`, `instruction`, `drug_class`, `is_antibiotic`,
   `dispensed_at`, `replaced_at`, `replaced_by`.
6. `create_medical_record_clinical_tables`
   `medical_record_vitals`, `pet_allergies`, `treatment_consents`,
   `referrals`, `lab_results`.
7. `add_identity_to_pets`
   `microchip_number` (unique), `species_code`, `color`, `markings`,
   `neutered_at`, `blood_type`.
8. `create_consents_table`
   Catatan persetujuan pemilik (UU 27/2022): `user_id`, `pet_id`, `type`,
   `policy_version`, `granted_at`, `revoked_at`, `metadata`, `ip_address`.

Database test memakai `pet_heal_test` dan **tidak** ikut termigrasi otomatis
oleh `php artisan test`. Setelah menarik perubahan ini, jalankan sekali:

```bash
DB_DATABASE=pet_heal_test php artisan migrate --force
```

---

## 3. Perubahan perilaku yang disengaja

### 3.1 Diagnosis tidak lagi dikunci tagihan

Sebelumnya `MedicalRecord::maskClinicalFields()` mengosongkan diagnosis, SOAP,
terapi, catatan, dan lampiran bila `extra_payment_status` belum lunas. Praktik
itu tidak dapat dipertahankan: pemilik hewan berhak mengetahui kondisi
hewaninya, dan menahan informasi medis demi tagihan bertentangan dengan
kewajiban transparansi serta etika kedokteran hewan.

Sekarang `can_view_full_record` bernilai `true` secara default. Klinik yang
benar-benar membutuhkan penundaan tampilan dapat menyalakan
`MEDICAL_RECORDS_GATE_CLINICAL=true`; sekalipun dinyalakan, tanggal kontrol
tetap dikirim karena merupakan instruksi medis. Test lama yang mengunci
perilaku masking sudah diubah menjadi
`test_payment_status_never_hides_clinical_content`.

### 3.2 Rekam medis tidak pernah dihapus

`DELETE /admin/medical-records/{id}` kini membatalkan, bukan menghapus:
status menjadi `void`, alasan wajib diisi, stok obat yang terlanjur dipotong
dikembalikan, dan baris tetap ada. Tombol di daftar rekam medis meminta alasan
lewat `prompt` lalu mengirim `void_reason`. Rekaman berstatus `void` tidak
menampilkan tombol edit.

Lampiran memakai soft delete. Berkas fisik baru dihapus lewat
`MedicalRecordAttachmentService::purge()`, yang hanya dipakai untuk pembersihan
berkas yatim — bukan alur klinis.

### 3.3 Harga obat diputuskan server

`PrescriptionService` mengabaikan `unit_price` dari request dan memakai
`medicines.price` milik klinik yang sama. Form hanya menampilkan biaya sebagai
teks baca-saja; angka yang dikirim klien tidak dapat menurunkan tagihan obat.

### 3.4 Satu booking satu rekam medis

Unique index `medical_records.booking_id` menutup balapan dua submit
bersamaan. Controller tetap memeriksa lebih dulu agar pesan error ramah, dan
menangkap `QueryException` duplikat sebagai lapisan kedua.

---

## 4. Peta file

### Model
- `app/Models/MedicalRecord.php` — status, hash isi, `finalize()`, `void()`,
  relasi vitals/revisions, perhitungan ulang biaya.
- `app/Models/MedicalRecordRevision.php`
- `app/Models/MedicalRecordVitals.php`
- `app/Models/PetAllergy.php`
- `app/Models/TreatmentConsent.php`
- `app/Models/Referral.php`
- `app/Models/LabResult.php`
- `app/Models/Consent.php`
- `app/Models/MedicalRecordAttachment.php` — disk privat, accessor `url`
  bertanda tangan, `sha256`.
- `app/Models/PrescriptionItem.php`, `Medicine.php`, `Doctor.php`, `Clinic.php`,
  `Pet.php` — field baru, cast, relasi.

### Service
- `app/Services/MedicalRecordRevisionService.php` — snapshot, diff, pencatatan
  revisi (termasuk salinan resep).
- `app/Services/MedicalRecordAttachmentService.php` — validasi, re-encode gambar
  1600 px, PDF apa adanya, hash, kuota, soft delete, purge.
- `app/Services/PrescriptionService.php` — harga server, field klinis baru,
  pemulihan stok sebelum koreksi resep.
- `app/Services/MedicineStockService.php` — batch FEFO dicatat per item,
  `markDispensed`.
- `app/Services/ImageService.php` — parameter disk (default tetap `public` agar
  pemakaian lama tidak berubah).

### Controller
- `app/Http/Controllers/Admin/MedicalRecordController.php`
- `app/Http/Controllers/Doctor/DoctorMedicalRecordController.php`
- `app/Http/Controllers/Api/MedicalRecordController.php`

### View
- `admin/medical-records/partials/soap-fields.blade.php` — SOAP + tanda vital +
  outcome.
- `admin/medical-records/partials/prescription-fields.blade.php` dan
  `prescription-row.blade.php` — resep terstruktur, peringatan alergi, biaya
  baca-saja.
- `admin/medical-records/show.blade.php` — status void, tanda vital, resep,
  alergi, riwayat perubahan.
- `doctor/medical-records/form.blade.php` — SOAP, tanda vital, alasan koreksi.
- `medical-records/resume.blade.php` — PDF resume resmi.
- `medical-records/verify.blade.php` — halaman verifikasi tanpa isi diagnosis.

### Lain
- `config/medical_records.php` — gate, retensi, disk/TTL/kuota lampiran,
  `policy_version`.
- `app/Console/Commands/ArchiveMedicalRecords.php` — `medical-records:archive`.
- `routes/console.php` — jadwal arsip bulanan.
- `routes/web.php` — rute bertanda tangan dan rute alergi.

---

## 5. Rute dan endpoint baru

| Metode | Rute | Nama | Keterangan |
|--------|------|------|------------|
| GET | `/medical-records/{record}/attachments/{attachment}` | `medical-records.attachments.download` | Wajib tanda tangan; URL dibuat dari accessor `$attachment->url` |
| GET | `/medical-records/{id}/verify` | `medical-records.verify` | Halaman verifikasi cap isi |
| GET | `/admin/medical-records/{id}/resume` | `admin.medical-records.resume` | PDF resume, dicatat di audit |
| GET | `/doctor/medical-records/{id}/resume` | `doctor.medical-records.resume` | Sama, khusus dokter |
| POST | `/admin/medical-records/{id}/allergies` | `admin.medical-records.allergies.store` | Catat alergi hewan |
| DELETE | `/admin/medical-records/{id}/allergies/{allergy}` | `admin.medical-records.allergies.destroy` | Nonaktifkan alergi |

Resume dan verifikasi memakai `content_hash` (SHA-256) yang dihitung dari field
klinis dan item resep. Mengubah isi setelah dokumen dicetak membuat halaman
verifikasi menyatakan cap isi tidak cocok.

---

## 6. Perubahan API pemilik hewan

`Api\MedicalRecordController::serializeRecord()` sekarang mengirim:

- `subjective`, `objective`, `assessment`, `plan`;
- `vitals` (bila ada);
- `allergies` hewan;
- `prescription` terstruktur dari `prescriptionItems` (sebelumnya tidak pernah
  di-eager-load sehingga selalu kosong);
- `clinic` (nama, alamat, telepon, izin);
- `doctor` (nama, spesialisasi, STR/SIP);
- data booking yang sudah dirapikan (tanggal, jam, layanan).

Eager load di `show()` dan `getByBooking()`:
`booking.clinic, pet.allergies, doctor, prescriptionItems, vitals, attachments`.

Aplikasi Android belum diperbarui untuk membaca field baru ini. Field lama
tetap dikirim, jadi versi lama tidak rusak.

---

## 7. Cara memverifikasi

```bash
php artisan migrate --force
DB_DATABASE=pet_heal_test php artisan migrate --force
php artisan view:cache
php artisan test
php artisan medical-records:archive --dry-run
```

Test yang relevan:

- `tests/Feature/MedicalRecordSoapAndAttachmentsTest.php`
- `tests/Feature/MedicalRecordGovernanceTest.php`
- `tests/Feature/PayloadGateAndPermissionFixesTest.php`
- `tests/Feature/AdminFilterConsistencyTest.php`

---

## 8. Batas pekerjaan (belum selesai)

Hal-hal berikut sudah punya skema/model tetapi belum punya alur UI penuh.
Jangan menganggapnya selesai:

1. **Persetujuan tindakan (`treatment_consents`, `consents`)** — tabel dan model
   ada, belum ada layar pengambilan persetujuan maupun tanda tangan pemilik.
2. **Rujukan (`referrals`) dan hasil laboratorium (`lab_results`)** — model ada,
   belum ada CRUD/endpoint.
3. **Addendum rekam medis** — koreksi saat ini dicatat sebagai revisi; endpoint
   addendum resmi (tambahan yang tidak menimpa isi lama) belum dibuat.
4. **Tanda tangan digital dokter** — `signature_path` tersimpan, resume masih
   mencetak nama/STR/SIP tanpa gambar tanda tangan.
5. **UI alergi hanya di admin** — dokter melihat peringatan di form resep, tetapi
   belum bisa menambah alergi sendiri.
6. **Retensi berkas fisik** — command arsip mengubah status, belum memindahkan
   berkas ke penyimpanan dingin.
7. **Enkripsi at-rest** — lampiran belum dienkripsi di disk; masih mengandalkan
   kontrol akses dan disk privat.
8. **API pemilik hewan** — belum ada endpoint ekspor data pribadi
   (`/me/data-export`), penarikan persetujuan, atau unduh resume dari aplikasi.

---

## 9. Catatan untuk yang melanjutkan

- Jangan mengembalikan `unit_price` sebagai input yang dipercaya. Harga obat
  selalu dari master.
- Jangan mengubah `destroy()` menjadi `delete()`. Pembatalan harus lewat
  `void()` agar audit dan retensi tetap utuh.
- Jangan menambahkan kembali penyembunyian diagnosis berbasis status bayar.
- Setiap penulisan baru ke rekam medis wajib memotret `snapshot()` sebelum
  perubahan dan memanggil `record()` sesudahnya.
- Lampiran baru harus lewat `MedicalRecordAttachmentService`; jangan menyimpan
  ke disk `public`.
- `config('medical_records.attachment_disk')` default `local`. Mengubahnya ke
  `public` akan membuka kembali kebocoran yang sudah ditutup.

Catatan jadwal: jam layanan klinik kini mengikat slot booking dan penawaran
admin. Rincian ada di `docs/JADWAL_KLINIK_DAN_SLOT.md`.

## Form rekam medis dokter = admin

`resources/views/doctor/medical-records/form.blade.php` sengaja memakai struktur
yang sama dengan `admin/medical-records/create.blade.php` dan `edit.blade.php`:
satu panel "Dokumentasi klinis" berisi diagnosis/tindakan, SOAP, tanda vital,
resep, lampiran, biaya, catatan, dan kunjungan berikutnya, ditutup baris aksi.
Jangan mengubah salah satu form saja; perbedaan tampilan antara portal dokter
dan admin pernah dilaporkan sebagai bug UI. Perbedaan yang sah hanya data
booking (dokter melihat pasien dan pemiliknya) dan tombol kembali.
