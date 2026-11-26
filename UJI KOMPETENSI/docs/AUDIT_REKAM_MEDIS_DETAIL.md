# Audit Detail — Rekam Medis Hewan PetHeal

Tanggal audit: 2026-07-11 · Cakupan: `PetHeal_Backend` (Laravel) + `PetHeal_Android` (klien)
Metode: bedah kode per file, penelusuran skema migrasi, penelusuran alur tulis/baca/ekspor,
pengecekan otorisasi per peran, dan pembacaan test yang ada.

Berkas yang diperiksa:

| Lapisan | Berkas |
|---|---|
| Model | `MedicalRecord`, `MedicalRecordAttachment`, `PrescriptionItem`, `Medicine`, `Vaccination`, `AuditLog`, `Booking` |
| Controller | `Admin/MedicalRecordController`, `Doctor/DoctorMedicalRecordController`, `Api/MedicalRecordController` |
| Service | `PrescriptionService`, `MedicineStockService`, `MedicalRecordAttachmentService`, `FCMService`, `PaymentStatusService` |
| Skema | 7 migrasi rekam medis + `prescription_items` + `medical_record_attachments` |
| View | `admin/medical-records/{index,create,edit,show,partials/*}`, `doctor/medical-records/{index,form,show}` |
| Rute | `routes/web.php` (admin + dokter), `routes/api.php`, `routes/console.php` |
| Klien | `Models.kt`, `MedicalRecordRepository.kt`, `MedicalRecordsScreen.kt` |
| Test | `MedicalRecordSoapAndAttachmentsTest`, `PayloadGateAndPermissionFixesTest`, dll. |

---

## 1. Skema saat ini (apa yang benar-benar tersimpan)

`medical_records`:

```
id, booking_id FK CASCADE, pet_id FK CASCADE, doctor_id FK CASCADE, clinic_id NULL FK
subjective, objective, assessment, plan        -- SOAP, nullable, teks bebas
diagnosis, treatment                            -- wajib (NOT NULL)
medicine, notes                                 -- teks bebas
next_visit_date, next_visit_time, reminder_sent -- pengingat kontrol
cost, treatment_cost, medicine_cost
total_medical_cost, extra_payment_amount, extra_payment_paid_amount,
extra_payment_status, extra_payment_order_id UNIQUE, extra_payment_date
created_at, updated_at
```

`medical_record_attachments`: `medical_record_id, path (disk public), original_name, caption, mime_type, size_bytes, uploaded_by`
`prescription_items`: `medical_record_id, medicine_id NULL, medicine_name, dose, quantity, unit, unit_price`

Yang **tidak ada** sama sekali: nomor rekam medis, penulis/pengubah (created_by/updated_by),
versi/revisi, status (draft/final/void), tanda tangan, hash integritas, alergi, tanda vital,
identitas pemilik di dalam record, identitas legal dokter, retensi, waktu finalisasi.

---

## 2. Yang sudah kuat (jangan dirusak saat refactor)

1. Aturan **satu booking satu rekam medis** ditegakkan di dua controller (bukan hanya di UI).
2. SOAP ditambahkan **tanpa merusak** `diagnosis`/`treatment` lama; ekspor tetap kompatibel.
3. `PrescriptionService` dipakai bersama admin & dokter → perilaku seragam, stok tidak dobel.
4. Stok memakai FEFO dan **menolak batch kedaluwarsa** dengan pesan yang bisa ditindaklanjuti.
5. Record + resep + lampiran + penyelesaian booking berada dalam **satu transaksi DB**.
6. Cache dashboard diinvalidasi setiap tulis (`forgetDashboardCaches`).
7. Isolasi klinik diterapkan pada hampir semua query (`clinic_id`), termasuk `Rule::exists` obat.
8. Snapshot nama & harga obat disimpan di `prescription_items` → resep lama tidak berubah saat master berubah.
9. Lampiran memakai `ImageService` (resize + re-encode) dan **menolak** tipe selain gambar/PDF.
10. `AuditLog::log()` sudah dipakai pada booking, dokter, dan obat.

---

## 3. Ringkasan temuan

| ID | Tingkat | Temuan | Bukti |
|---|---|---|---|
| RM-01 | Kritis | Lampiran medis dapat diakses publik tanpa login | `MedicalRecordAttachmentService.php:56,66`, `MedicalRecordAttachment::getUrlAttribute` |
| RM-02 | Kritis | Harga satuan obat dipercaya dari input klien | `PrescriptionService.php:92` |
| RM-03 | Kritis | Rekam medis dapat dihapus permanen (record + berkas) | `Admin/MedicalRecordController.php:532-618` |
| RM-04 | Tinggi | Tidak ada revisi/jejak perubahan isi klinis | `Admin/.../update:368-460`, `Doctor/.../update:295-397` |
| RM-05 | Tinggi | Admin create/update rekam medis tidak menulis audit log | `Admin/MedicalRecordController.php:150-322, 368-460` |
| RM-06 | Tinggi | Rekam medis bisa diedit selamanya; tidak ada status final/void | skema `medical_records` |
| RM-07 | Tinggi | Isi klinis dikunci di balik tagihan (termasuk tanggal kontrol) | `MedicalRecord.php:130-160` |
| RM-08 | Tinggi | Resep terstruktur **tidak pernah dikirim** ke aplikasi pemilik | `Api/MedicalRecordController.php:20-89` |
| RM-09 | Tinggi | Pengingat kontrol tidak pernah terjadwal | `SendReminders.php:39-41`, `MedicalRecord::scopeUpcomingVisits` (tak terpakai) |
| RM-10 | Tinggi | Tidak ada identitas legal dokter/klinik (STR/SIP/izin) | `Doctor.php`, `Clinic.php` |
| RM-11 | Tinggi | Ekspor massal tidak diaudit + CSV formula injection | `Admin/...:462-530` |
| RM-12 | Tinggi | Tidak ada nomor unik per booking → duplikasi record mungkin | migrasi `2024_01_15_000006` |
| RM-13 | Tinggi | Edit resep menghapus riwayat resep asli | `PrescriptionService.php:43-45` |
| RM-14 | Tinggi | Tidak ada kaidah resep legal (batch, ED, signa, golongan obat) | `prescription_items` |
| RM-15 | Tinggi | Pembayaran tambahan tidak idempoten | `Api/MedicalRecordController.php:235-293` |
| RM-16 | Sedang | Biaya konsultasi 0 tidak mungkin disimpan | `Admin/...:217-220`, `Doctor/...:166-169` |
| RM-17 | Sedang | `partial` tidak pernah dihasilkan, tetapi ada di filter | `MedicalRecord::recalculatePaymentState` |
| RM-18 | Sedang | API mengirim model mentah `doctor`/`booking` | `Api/MedicalRecordController.php:84-86` |
| RM-19 | Sedang | Tidak ada tanda vital terstruktur | skema SOAP |
| RM-20 | Sedang | Tidak ada alergi/riwayat obat | skema |
| RM-21 | Sedang | Identitas hewan tidak lengkap (microchip, spesies bebas) | `pets` |
| RM-22 | Sedang | Satu record per booking → rawat inap tak terdokumentasi | relasi `hasOne` |
| RM-23 | Sedang | Tidak ada resume medis cetak + tanda tangan + verifikasi | `exportPdf/exportCsv` |
| RM-24 | Sedang | Tidak ada informed consent / outcome / rujukan / lab terstruktur | skema |
| RM-25 | Sedang | Lampiran: tanpa checksum, nama file pakai `time()`, tanpa batas total | `MedicalRecordAttachmentService.php` |
| RM-26 | Sedang | Hapus lampiran tidak diaudit, tidak ada soft-delete | `Admin/...:443-455` |
| RM-27 | Sedang | Validasi tanggal kontrol memakai zona waktu server | `Admin/...:388-397` |
| RM-28 | Sedang | Data sensitif tersimpan tanpa enkripsi at-rest | `config/filesystems.php` |
| RM-29 | Rendah | Tidak ada index `(pet_id, created_at)` untuk linimasa pasien | migrasi |
| RM-30 | Rendah | `AuditLog` tidak tamper-evident (bisa diedit dari DB) | `AuditLog.php` |
| RM-31 | Rendah | Tidak ada atribusi `created_by`/`updated_by` pada record | skema |
| RM-32 | Rendah | Pet detail memuat seluruh record tanpa paginasi | `Api/PetController.php:61` |
| RM-33 | Rendah | Tidak ada pembatasan ukuran total lampiran per record | skema |
| RM-34 | Rendah | Cakupan test tidak menyentuh audit, otorisasi API per-ID, duplikasi | `tests/Feature` |

---

## 4. Detail temuan kritis

### RM-01 — Lampiran medis bisa dibuka siapa pun yang punya URL
`MedicalRecordAttachmentService::storeOne()` menulis ke `Storage::disk('public')`, dan
`getUrlAttribute()` mengembalikan `APP_URL/storage/medical-records/{id}/...`. Tidak ada
middleware, tidak ada signed URL. Rontgen, hasil lab, dan foto kondisi hewan adalah data
pribadi pemilik (UU 27/2022) sekaligus rahasia medis.

Dampak: enumerasi/tebak URL, kebocoran via cache/CDN, bekas di browser, dan tidak bisa dicabut.
Test `test_pdf_attachment_is_stored_and_deleted_with_its_file` justru mengunci perilaku ini.

Perbaikan:
1. Disk privat (`storage/app/private/medical-records`).
2. Route `GET /medical-records/{record}/attachments/{attachment}` dengan cek
   `auth` + `clinic_id` + peran, atau `URL::temporarySignedRoute(..., 5)`.
3. Simpan `sha256` dan tolak file > batas total.
4. Migrasi berkas lama, hapus folder publik, sesuaikan test.

### RM-02 — Harga obat ditentukan klien
```php
$unitPrice = (float) ($row['unit_price'] ?? $medicine->price);   // PrescriptionService:92
```
`unit_price` divalidasi `nullable|numeric|min:0` dan **dirender sebagai input editable** di
`partials/prescription-fields.blade.php:85-88`. Siapa pun dengan akses form bisa mengisi 0 dan
menghapus nilai obat dari tagihan tanpa jejak; `medicine_cost` final ikut turun.

Perbaikan: harga selalu dari `medicines.price`/`MedicineBatch`; diskon hanya lewat field
terpisah `discount_amount` + alasan + audit; tolak `unit_price` dari payload.

### RM-03 — Rekam medis bisa dihapus permanen
`destroy()` menghapus record, item resep (cascade), berkas lampiran, dan mengembalikan stok.
Satu-satunya penjaga adalah "sudah ada pembayaran". Rekam medis tanpa pembayaran bisa lenyap
tanpa sisa isi — hanya tersisa satu baris `AuditLog` berisi ringkasan.

Perbaikan: kolom `voided_at`, `voided_by`, `void_reason`; record void tetap tampil (dicoret),
tetap ikut ekspor, dan tidak bisa diedit lagi. Retensi minimum 5 tahun (klinis) / 10 tahun
(keuangan); job arsip, bukan hapus.

---

## 5. Detail temuan tinggi

### RM-04 / RM-05 — Jejak perubahan tidak memadai
- Admin `store()` dan `update()` **tidak memanggil `AuditLog`** sama sekali.
- Dokter memanggil `AuditLog::log()` tetapi hanya deskripsi "memperbarui rekam medis #id" —
  tanpa nilai lama/baru.
- `updated_at` hanya timestamp; tidak ada siapa dan mengapa.
- `AuditLog` dapat diedit/dihapus langsung dari DB; tidak ada hash chain.

Akibat: pertanyaan "siapa mengubah diagnosis dari X menjadi Y dan kapan" tidak bisa dijawab.
Untuk dokumen medis ini bukan sekadar nice-to-have.

Perbaikan minimal:
```
medical_record_revisions: id, medical_record_id, changed_by, changed_at,
  before JSON, after JSON, reason, ip_address
medical_records: created_by, updated_by, finalized_at, finalized_by, content_hash
audit_logs: prev_hash, hash
```
Tulis revisi di `store`, `update`, `void`, dan saat resep berubah.

### RM-06 — Tidak ada status dokumen
Tidak ada konsep draft → final → void. Rekam medis yang sudah dibayar, diekspor, atau menjadi
dasar klaim masih bisa diubah bebas oleh admin mana pun di klinik. Perbaikan: `status`
(`draft|final|void`), kunci edit setelah `final` kecuali lewat addendum.

### RM-07 — Gate klinis di balik tagihan
`maskClinicalFields()` mengosongkan diagnosis, SOAP, terapi, catatan, **dan `next_visit_date`**
bila `extra_payment_status` belum `paid`/`not_required`. Efek samping klinis: pemilik tidak
melihat jadwal kontrol padahal itu instruksi medis. Gate ini dipakai di 5 titik API
(`PetController`, `DashboardController`, `BookingController`, `MedicalRecordController`).

Perbaikan: ringkasan klinis selalu tampil; tagihan ditampilkan sebagai banner terpisah.
Bila kebijakan klinik tetap ingin menahan detail, sediakan tombol "minta salinan rekam medis"
yang selalu tersedia dan tercatat.

### RM-08 — Resep tidak sampai ke pemilik
`serializeRecord()` memuat `booking.clinic, pet, doctor, attachments` — **tidak** memuat
`prescriptionItems`, dan payload tidak punya kunci `prescription`. Aplikasi hanya menerima
`medicine` (teks bebas). Jadi daftar obat terstruktur, dosis, jumlah, dan biaya obat per item
tidak pernah terlihat oleh pemilik, meski tersimpan rapi di DB.

Perbaikan: kirim `prescription: [{medicine_name, dose, quantity, unit, unit_price, subtotal}]`
(field klinis tetap kena gate yang wajar), lalu render di `MedicalRecordsScreen`.

### RM-09 — Pengingat kontrol tidak pernah berjalan
`scopeUpcomingVisits()` dan `scopeVisitsTomorrow()` ada tetapi **tidak dipanggil di mana pun**.
`SendReminders` hanya memproses booking dan vaksinasi. `FCMService::sendVaccinationReminder`
dipanggil **saat record disimpan** (bukan H-1/H-3), dan `medical_records.reminder_sent` tidak
pernah di-set `true`.

Akibat: pemilik menerima notifikasi kontrol terlalu dini; tidak ada pengingat menjelang
tanggal; `reminder_sent` selalu `false` sehingga scope akan mengirim ulang tanpa batas bila
nanti diaktifkan.

Perbaikan: tambah `sendMedicalRecordReminders()` di command (H-3 dan H-0), set `reminder_sent`
setelah sukses, dan hentikan pengiriman langsung saat store.

### RM-10 — Identitas legal tidak ada
`doctors` tidak punya `str_number`, `sip_number`, masa berlaku, atau gambar tanda tangan;
`clinics` tidak punya nomor izin usaha klinik hewan. Akibatnya dokumen cetak tidak sah dan
tidak ada kontrol dokter yang izinnya kedaluwarsa.

### RM-11 — Ekspor massal
`exportPdf` (maks 200, dipotong diam-diam) dan `exportCsv` (tanpa batas, streaming) tidak
menulis audit log. CSV memakai `fputcsv` mentah: nilai yang dimulai `=`, `+`, `-`, `@` akan
dieksekusi Excel (CSV formula injection dari kolom diagnosis/terapi yang bisa diisi siapa pun
di klinik). Perbaikan: catat `medical_record.exported` (jumlah baris, filter, IP, user) dan
awali sel berbahaya dengan `'`.

### RM-12 — Duplikasi record
Tidak ada `unique` pada `medical_records.booking_id`; penjagaan hanya `exists()` sebelum
`insert`. Dua submit bersamaan (double-click, retry) dapat membuat 2 record untuk 1 booking,
lalu `booking->medicalRecord` (hasOne) memilih salah satu secara acak. Perbaikan: unique index
+ tangani `QueryException` sebagai pesan "sudah ada rekam medis".

### RM-13 — Riwayat resep hilang saat koreksi
`syncForRecord()` melakukan `prescriptionItems()->delete()` lalu membuat ulang. Harga/nama
disnapshot, tetapi **item asli yang sudah diberikan tidak tersimpan**. Bila dokter mengubah
resep setelah obat diserahkan, tidak ada bukti apa yang benar-benar diserahkan sebelumnya
(yang tersisa hanya gerakan stok). Perbaikan: append-only `prescription_revisions` atau
soft-delete item dengan `replaced_at`/`replaced_by`.

### RM-14 — Resep belum memenuhi kaidah farmasi
Tidak ada: nomor batch/lot yang diserahkan, ED obat yang diserahkan, rute, frekuensi, durasi,
instruksi pemakaian, golongan obat (bebas/bebas terbatas/keras/narkotika), flag antibiotik.
Akibat: tidak bisa menarik obat saat recall, tidak bisa melaporkan obat keras/narkotika, dan
tidak bisa mencetak resep berformat `Rp.` yang sah.

### RM-15 — Pembayaran tambahan tidak idempoten
`createExtraPayment()` hanya menolak status `paid`/`not_required`; status `pending` tetap
diizinkan membuat transaksi baru. Ketukan ganda menghasilkan beberapa order Midtrans untuk
tagihan yang sama. Perbaikan: kunci per record (`lockForUpdate`), pakai ulang pending order
yang belum kedaluwarsa, dan catat percobaan.

---

## 6. Detail temuan sedang & rendah

| ID | Inti masalah | Perbaikan singkat |
|---|---|---|
| RM-16 | `cost == 0` dianggap "kosong" lalu diisi `booking.total_amount`; konsultasi gratis mustahil | bedakan `null` vs `0`, atau field `consultation_free` |
| RM-17 | `partial` diterima filter & dihitung, tetapi state machine tidak pernah menghasilkannya | tambah state `partial` saat `0 < paid < amount`, atau buang dari filter |
| RM-18 | `doctor` dan `booking` dikirim mentah (termasuk `user_id`, hash `invite_token`, nominal internal) | pakai resource/array terpilih (data minimization) |
| RM-19 | Tanda vital tidak terstruktur | tabel `medical_record_vitals` |
| RM-20 | Alergi & riwayat obat tidak ada | `pet_allergies`, `medication_history` + peringatan di form resep |
| RM-21 | `pets.species`/`gender` string bebas; tanpa microchip, steril, golongan darah | enum + master spesies + `microchip_number` unik |
| RM-22 | Satu record per booking | `medical_record_entries` untuk progres harian/rawat inap |
| RM-23 | Tidak ada resume medis per pasien (kop, tanda tangan, QR) | view cetak + `content_hash` + `verify/{token}` |
| RM-24 | Consent/outcome/rujukan/lab tidak ada | tabel terpisah, lihat lampiran |
| RM-25 | Nama berkas `time()`, tanpa checksum, tanpa kuota | ULID + sha256 + `MAX_TOTAL_MB` per record |
| RM-26 | Hapus lampiran tanpa audit & permanen | `deleted_at` + `medical_record.attachment.deleted` log |
| RM-27 | `after:today` memakai zona waktu server | kolom `timezone` klinik + validasi berbasis tanggal klinik |
| RM-28 | Teks diagnosis & berkas tanpa enkripsi | enkripsi kolom sensitif / disk terenkripsi |
| RM-29 | Linimasa pasien bisa lambat | index `(pet_id, created_at)` |
| RM-30 | Audit log bisa diubah | append-only + hash chain |
| RM-31 | Tidak tahu admin vs dokter yang menulis | `created_by`, `updated_by` |
| RM-32 | Detail pet memuat semua record | paginasi/limit + `latest 20` |
| RM-33 | Lampiran tak dibatasi total | kuota per record + per klinik |
| RM-34 | Test tidak menyentuh audit/otorisasi ID/duplikasi | lihat bagian 9 |

---

## 7. Matriks kelengkapan standar rekam medis veteriner

| Komponen standar | Status | Catatan |
|---|---|---|
| Identitas hewan (nama, spesies, breed, sex, umur/DOB, berat, microchip) | Sebagian | microchip, steril, warna/tanda belum ada |
| Identitas pemilik & kontak | Tidak di record | hanya lewat relasi `booking.user`; berubah bila data user diubah |
| Tanggal/jam kunjungan | Ada | `created_at`; tidak ada jam mulai/selesai pemeriksaan |
| Anamnesis (S) | Ada (opsional) | teks bebas |
| Pemeriksaan fisik (O) | Sebagian | teks bebas; tidak ada suhu/HR/RR/CRT/BCS |
| Diagnosis (A) | Ada | wajib; tanpa kode standar |
| Prognosis | Tidak ada | — |
| Terapi/tindakan (P) | Ada | teks + resep terstruktur |
| Resep (dosis, jumlah, aturan pakai, batch, ED) | Sebagian | batch/ED/signa/golongan tidak ada |
| Instruksi pulang & jadwal kontrol | Sebagian | tanggal ada, instruksi tidak |
| Lampiran penunjang (lab/rontgen) | Ada | tanpa metadata pemeriksaan/nilai rujukan |
| Identitas & tanda tangan dokter + no. STR/SIP | Tidak ada | — |
| Identitas & izin klinik | Tidak ada | — |
| Persetujuan pemilik (tindakan/Data) | Tidak ada | — |
| Catatan perkembangan/rawat inap | Tidak ada | satu record per booking |
| Outcome (sembuh/rujuk/mati/euthanasia) | Tidak ada | — |
| Jejak perubahan & finalisasi | Tidak ada | — |
| Retensi & pemusnahan terjadwal | Tidak ada | — |
| Salinan untuk pemilik (resume medis) | Tidak ada | — |

---

## 8. Matriks otorisasi saat ini

| Aksi | Admin klinik | Dokter (portal) | Pemilik (API) | Catatan |
|---|---|---|---|---|
| Lihat daftar | ✔ (clinic-scoped) | ✔ (doctor + clinic) | ✔ (pet miliknya) | ok |
| Lihat detail | ✔ | ✔ hanya record sendiri | ✔ | ok |
| Buat | ✔ booking klinik | ✔ booking miliknya | ✘ | ok |
| Ubah | ✔ **selamanya** | ✔ record sendiri | ✘ | tidak ada kunci final |
| Hapus | ✔ bila belum bayar | ✘ | ✘ | melanggar retensi |
| Unduh lampiran | ✔ | ✔ | ✔ via URL publik | **tanpa otorisasi** |
| Ekspor massal | ✔ | ✘ | ✘ | tanpa audit |
| Ubah harga obat | ✔ dari payload | ✔ dari payload | ✘ | **tidak tervalidasi server** |

---

## 9. Kesenjangan test

Yang diuji (`MedicalRecordSoapAndAttachmentsTest`, `PayloadGateAndPermissionFixesTest`, dll.):
SOAP tersimpan, PDF/gambar tersimpan & terhapus, masking saat terkunci, cache invalidasi,
tanggal kontrol hanya divalidasi saat diubah.

Yang **belum** diuji dan berisiko regresi:
1. Admin tidak bisa membuat record untuk booking klinik lain (ada di Phase1, tapi tidak untuk update lintas klinik lewat ID).
2. Akses API `GET /medical-records/{id}` dengan ID milik pengguna lain (IDOR).
3. Akses lampiran tanpa login.
4. Duplikasi record saat dua request bersamaan.
5. Audit log tercipta untuk create/update/void.
6. Harga obat tidak bisa diubah klien.
7. Stok kembali tepat setelah void (bukan delete).
8. Pengingat kontrol terkirim H-3 dan `reminder_sent` berubah.
9. Ekspor tercatat di audit.
10. CSV injection tidak lolos.

---

## 10. Rencana remediasi (berurutan, dengan kriteria terima)

**Tahap 1 — Keamanan & integritas (1 sprint)**
1. Lampiran privat + route ber-otorisasi (RM-01, RM-25, RM-26).
2. Harga obat server-side + hapus input `unit_price` (RM-02).
3. Void menggantikan delete + retensi (RM-03, RM-06).
4. Revisi + audit create/update/void/ekspor (RM-04, RM-05, RM-11, RM-30, RM-31).
5. Unique `booking_id` (RM-12).

Kriteria terima: test baru untuk 1–5 hijau; tidak ada URL `/storage/medical-records` yang bisa
diakses tanpa sesi; `medical_record_revisions` terisi pada setiap perubahan.

**Tahap 2 — Klinis & farmasi (1 sprint)**
6. Identitas legal dokter/klinik + kunci edit setelah final (RM-10).
7. Resep: batch/ED/signa/golongan + histori append-only (RM-13, RM-14).
8. Tanda vital + alergi (RM-19, RM-20).
9. Resep dikirim ke API & Android (RM-08).
10. Pengingat kontrol terjadwal (RM-09).

**Tahap 3 — Dokumen & kepatuhan (1 sprint)**
11. Resume medis cetak + QR verifikasi (RM-23).
12. Consent, outcome, rujukan, lab terstruktur (RM-24).
13. Retensi/arsip + enkripsi + pembatasan ekspor (RM-28).
14. Idempotensi pembayaran tambahan (RM-15).

**Tahap 4 — Penyempurnaan (opsional)**
15. Progres rawat inap (RM-22), microchip (RM-21), index & paginasi (RM-29, RM-32, RM-33),
    timezone klinik (RM-27), pembersihan filter `partial` (RM-17), resource API (RM-18).

---

## 11. Lampiran — usulan perubahan skema

```php
// medical_records
$table->string('status', 16)->default('draft');        // draft|final|void
$table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
$table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
$table->timestamp('finalized_at')->nullable();
$table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
$table->timestamp('voided_at')->nullable();
$table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
$table->string('void_reason', 255)->nullable();
$table->string('content_hash', 64)->nullable();
$table->unique('booking_id');

// medical_record_revisions
id, medical_record_id, changed_by, action, before JSON, after JSON, reason, ip_address, created_at

// medical_record_vitals
id, medical_record_id, temperature_c, heart_rate, respiratory_rate, crt_seconds,
mucosa, hydration, bcs (1-9), pain_score, weight_kg, measured_at

// pet_allergies
id, pet_id, allergen, reaction, severity, noted_by, noted_at

// prescription_items
+ batch_number, expiry_date, route, frequency, duration_days, instruction,
  drug_class, is_antibiotic, dispensed_at, replaced_at, replaced_by

// doctors / clinics
+ str_number, str_expiry, sip_number, sip_expiry, signature_path
+ license_number, license_expiry, responsible_doctor_id

// medical_record_attachments
+ sha256, deleted_at
```

**Endpoint yang perlu ditambah**
```
GET  /medical-records/{record}/attachments/{attachment}   (auth + tenant)
GET  /medical-records/{id}/resume.pdf                     (cetak resmi)
GET  /verify/{token}                                      (keaslian dokumen)
POST /medical-records/{id}/finalize
POST /medical-records/{id}/void
POST /medical-records/{id}/addendum
GET  /me/data-export                                      (hak subjek data)
```

---

## 12. Status implementasi (26 November 2026)

Perubahan yang sudah masuk ke `PetHeal_Backend` beserta test penjaganya. Rincian
teknis ada di `docs/REKAM_MEDIS_IMPLEMENTASI.md`.

| RM | Status | Bukti / catatan |
|----|--------|-----------------|
| RM-01 | Selesai | Lampiran di disk `local`, rute `medical-records.attachments.download` bertanda tangan, SHA-256, soft delete |
| RM-02 | Selesai | `PrescriptionService` memakai `medicines.price`; input `unit_price` diabaikan |
| RM-03 | Selesai | `destroy()` memanggil `void()`; data dan lampiran tetap disimpan |
| RM-04 | Selesai | `medical_record_revisions` menyimpan before/after + alasan + IP |
| RM-05 | Selesai | Admin dan dokter menulis revisi + `AuditLog` pada create/update/void/export |
| RM-06 | Sebagian | Status `final`/`void`/`archived` dan unique booking; endpoint addendum resmi belum ada |
| RM-07 | Selesai | Gate tagihan dimatikan secara default; `test_payment_status_never_hides_clinical_content` |
| RM-08 | Selesai | `serializeRecord()` mengirim `prescription` terstruktur, vitals, allergies, clinic, doctor |
| RM-09 | Selesai | `SendReminders` mengirim H-3/H-0 untuk `next_visit_date`, lalu mengisi `reminder_sent` |
| RM-10 | Sebagian | STR/SIP dan izin klinik tersimpan, tampil di form admin dan resume; gambar tanda tangan belum dipakai |
| RM-11 | Selesai | `auditExport()` + `csvSafe()` mencegah formula injection |
| RM-12 | Selesai | Unique index `medical_records.booking_id` |
| RM-13 | Sebagian | Riwayat resep lama masuk snapshot JSON revisi; baris `prescription_items` tetap diganti saat koreksi |
| RM-14 | Selesai | Batch, ED, rute, frekuensi, durasi, instruksi, golongan, flag antibiotik |
| RM-15 | Selesai | `PaymentService` memakai ulang payment pending yang belum kedaluwarsa |
| RM-16 | Selesai | `resolveConsultationCost()` membedakan `null` (tidak dikirim) dan `0` (gratis) |
| RM-17 | Selesai | `recalculatePaymentState()` menghasilkan status `partial` |
| RM-18 | Selesai | API mengirim array terpilih untuk booking/doctor/clinic, bukan model mentah |
| RM-19 | Selesai | `medical_record_vitals` + form admin/dokter + tampilan detail |
| RM-20 | Sebagian | `pet_allergies` + peringatan resep + CRUD admin; riwayat obat belum ada |
| RM-21 | Sebagian | Microchip, warna, tanda khusus, steril, golongan darah; `species` masih string |
| RM-22 | Belum | Rawat inap/progres harian belum dimodelkan |
| RM-23 | Selesai | Resume PDF + `content_hash` + halaman verifikasi bertanda tangan |
| RM-24 | Sebagian | Tabel consent/rujukan/lab sudah dibuat; belum ada alur UI |
| RM-25 | Selesai | Nama ULID, SHA-256, kuota total per rekam medis |
| RM-26 | Sebagian | Soft delete; penghapusan lampiran belum menulis audit per lampiran |
| RM-27 | Belum | Validasi tanggal kontrol masih memakai zona waktu server |
| RM-28 | Belum | Belum ada enkripsi at-rest untuk lampiran |
| RM-29 | Selesai | Index `(pet_id, created_at)` dan `(status, created_at)` |
| RM-30 | Belum | `AuditLog` belum tamper-evident |
| RM-31 | Selesai | `created_by`/`updated_by`, `finalized_*`, `voided_*` |
| RM-32 | Belum | Pet detail masih memuat seluruh rekam medis tanpa paginasi |
| RM-33 | Selesai | Kuota total lampiran per rekam medis |
| RM-34 | Sebagian | Ditambah `MedicalRecordGovernanceTest` (unique booking, void, signed URL, hash); audit otorisasi API per-ID belum menyeluruh |

Test yang dijalankan: `php artisan test` — 572 lulus, 3.452 assertion.
