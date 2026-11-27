# P1.1 — Kredensial, Gate Finalisasi, dan Snapshot per Versi

Status: **implementasi teknis selesai dan teruji terarah (targeted tests)**.
Tanggal verifikasi: 26 November 2026.
Rujukan: `docs/REKAM_MEDIS_ROADMAP_REGULASI.md` §4.7, §5.3, §P1.1.

> Full suite `php artisan test` **tidak dijalankan ulang** setelah P1.1, sesuai
> permintaan pengguna. Test terarah dan regresi rekam medis dicatat di bagian 9.

---

## 1. Tujuan P1.1

1. STR/SIP/SIPP tidak lagi hanya berupa kolom teks di `doctors`.
2. Riwayat kredensial tersimpan; kredensial lama tidak ditimpa.
3. Hanya satu kredensial aktif per pemegang, jenis, klinik, dan cakupan.
4. Finalisasi normal ditolak bila STR/SIP/izin klinik tidak sah.
5. Snapshot kredensial dibuat per versi rekam medis dan tidak dapat berubah.
6. Kredensial historis lama ditandai `exception`, bukan dikarang.
7. Otorisasi paramedik dibatasi SIPP, action code, dan mode supervisi.
8. Admin tidak dapat memberikan kewenangan klinis.

---

## 2. Migrasi

| Migrasi | Isi |
|---|---|
| `2026_11_26_000007_create_credential_and_paramedic_tables.php` | `paramedics`, `professional_credentials`, `credential_exceptions`, `paramedic_authorizations`, `medical_record_credential_snapshots`. |
| `2026_11_26_000008_add_credential_cache_and_snapshot_guards.php` | Cache `doctors.credential_status/verified_at`; trigger append-only snapshot. |

Prinsip:

- additif; tidak menghapus kolom `doctors.str_number`, `sip_number`, dan
  tanggalnya;
- `active_key` unik mencegah kredensial aktif ganda;
- snapshot memakai unique `(version_id, holder_type, holder_id, credential_type)`;
- trigger MySQL menolak UPDATE dan DELETE snapshot;
- rollback menghapus trigger terlebih dahulu.

---

## 3. Tabel Baru

### 3.1 `professional_credentials`

Menyimpan STR, SIP, SIPP, dan riwayatnya.

Kolom kunci:

- `clinic_id`, `doctor_id`/`paramedic_id`;
- `credential_type`, `number`, `issued_at`, `expires_at`;
- `practice_scope`, `status`;
- `verified_at`, `verified_by`, `document_path`;
- `is_current`, `active_key` unik, `superseded_at`, `notes`.

Aturan:

- `active_key` hanya diisi saat kredensial aktif dan berlaku;
- penerbitan baru menonaktifkan baris lama (`is_current=false`,
  `active_key=NULL`, `superseded_at=now`) dalam transaksi yang sama;
- baris lama tetap ada sebagai riwayat;
- nomor tanpa tanggal berlaku tidak dianggap sah; status backfill menjadi
  `pending` (exception).

### 3.2 `medical_record_credential_snapshots`

Menyimpan kondisi kredensial saat versi dibuat:

- STR dokter;
- SIP dokter;
- izin klinik (`CLINIC_LICENSE`);
- `credential_status`: `valid`, `expired`, `missing`, `exception`;
- salinan JSON lengkap;
- `version_id` dan `medical_record_id`.

Snapshot tidak pernah diperbarui. Perubahan SIP/STR hanya memengaruhi versi
baru.

### 3.3 `credential_exceptions`

Mencatat keadaan darurat atau kredensial bermasalah **tanpa** mengubah status
kredensial menjadi sah. Berisi konteks darurat, dasar hukum/SOP, reviewer
eksternal, bukti, dan status review.

### 3.4 `paramedics` dan `paramedic_authorizations`

- `paramedics` terikat satu klinik dan dokter penyelia.
- `paramedic_authorizations` menyimpan `action_code`, mode supervisi
  (`none`, `available`, `real_time`), masa berlaku, approver, dan `active_key`
  unik.

---

## 4. Service

### 4.1 `ProfessionalCredentialService`

File: `app/Services/ProfessionalCredentialService.php`.

Kemampuan:

- `issue()` — menerbitkan kredensial baru dan menonaktifkan yang lama;
- `current()` — kredensial aktif berdasarkan klinik/pemegang/jenis;
- `evaluateFinalization()` — daftar masalah kredensial dan snapshot;
- `assertCanFinalize()` — menolak finalisasi dengan kode stabil;
- `captureSnapshots()` — menyimpan snapshot per versi secara idempotent;
- `reviewerSnapshot()` — snapshot STR/SIP reviewer amendment.

Status snapshot:

| Kondisi | Status |
|---|---|
| Aktif dan belum kedaluwarsa | `valid` |
| Tanggal berlaku sudah lewat | `expired` |
| Belum ada baris kredensial | `missing` |
| `pending`/`revoked`/tidak dapat dibuktikan | `exception` |

Gate normal memeriksa:

1. dokter penanggung jawab ada;
2. STR dokter;
3. SIP dokter pada klinik record;
4. izin klinik dan masa berlakunya.

Kode error:

- `422 CREDENTIAL_MISSING`;
- `422 CREDENTIAL_EXPIRED`.

Konteks error memuat daftar field dan status agar UI dapat menampilkan pesan
per kolom.

### 4.2 `ParamedicAuthorizationService`

File: `app/Services/ParamedicAuthorizationService.php`.

Aturan:

- paramedik harus aktif;
- SIPP harus ada dan berlaku;
- action harus memiliki otorisasi aktif dan dalam masa berlaku;
- mode `none` hanya untuk dokumentasi, vital, spesimen, dan tindakan
  non-invasif;
- mode `available` memerlukan dokter penyelia aktif di klinik yang sama;
- mode `real_time` memerlukan konfirmasi dokter;
- pelanggaran → `403 PARAMEDIC_NOT_AUTHORIZED`;
- penerbitan otorisasi hanya oleh dokter di klinik yang sama; admin →
  `403 CLINICAL_APPROVER_REQUIRED`.

---

## 5. Integrasi Gate Finalisasi

`MedicalRecordLifecycleService::finalize()` sekarang:

1. memeriksa lifecycle dan kewenangan dokter;
2. bila `credential_gate_enabled=true`, memanggil
   `ProfessionalCredentialService::assertCanFinalize()`;
3. membuat versi immutable;
4. menyimpan snapshot STR/SIP/izin klinik untuk versi tersebut.

`MedicalRecordAmendmentService`:

- menyimpan `reviewer_credential_snapshot` saat amendment klinis disetujui;
- menyimpan snapshot kredensial untuk versi hasil amendment.

Emergency tidak memanggil gate ini dan tidak mengubah status kredensial.
Exception dicatat terpisah.

---

## 6. Konfigurasi

File: `config/medical_records.php`.

```php
'credential_gate_enabled' => env('MEDICAL_RECORDS_CREDENTIAL_GATE_ENABLED', false),
```

Default **OFF** sesuai keputusan roadmap: flag klinis dinyalakan setelah UAT.
Saat ON, finalisasi normal tanpa STR/SIP/izin klinik yang sah akan ditolak.

---

## 7. Command

| Command | Fungsi |
|---|---|
| `credentials:backfill-from-doctors {--dry-run} {--chunk=200}` | Backfill STR/SIP dari kolom lama `doctors`. |
| `medical-records:backfill-credential-snapshots {--dry-run} {--chunk=200}` | Menandai versi lama tanpa snapshot sebagai `exception`. |
| `medical-records:credential-review {--status=missing,exception} {--json}` | Daftar kerja review + SLA 10 hari kerja (read-only). |

Catatan backfill:

- nomor lama tanpa tanggal berlaku → `pending` / exception;
- backfill tidak mengarang nomor, tanggal, atau verifikasi;
- versi lama tidak diberi status `valid` dari kredensial hari ini.

---

## 8. Hasil di Database Lokal

Database: `pet_heal`.

Migrasi:

- `2026_11_26_000007` DONE
- `2026_11_26_000008` DONE

Backfill kredensial dokter:

- dokter dengan STR/SIP legacy: **0**
- tidak ada kredensial yang dibuat (tidak mengarang data).

Backfill snapshot:

- versi tanpa snapshot: **14**
- snapshot dibuat: **42** (3 per versi: STR, SIP, CLINIC_LICENSE)
- seluruhnya berstatus **`exception`** karena finalisasi historis tidak
  memiliki bukti snapshot pada saat itu.
- `audit:verify-chain`: tetap valid, 0 masalah.
- `medical-records:credential-review --json`: **42 item**, 0 terlambat SLA,
  tenggat awal 10 Desember 2026.

---

## 9. Test

File baru: `tests/Feature/P11CredentialGateTest.php`.

Hasil: **8 passed, 32 assertions**.

Cakupan:

1. finalisasi tanpa kredensial → `CREDENTIAL_MISSING`;
2. STR/SIP/izin valid → finalisasi berhasil dan 3 snapshot `valid`;
3. SIP kedaluwarsa → `CREDENTIAL_EXPIRED`;
4. kredensial baru menonaktifkan lama tanpa menghapus riwayat;
5. snapshot lama tidak berubah saat kredensial master berubah;
6. exception darurat tidak mengubah status kredensial;
7. paramedik: SIPP + otorisasi + mode supervisi, admin tidak boleh memberi
   kewenangan;
8. amendment klinis menyimpan snapshot reviewer.

Regresi terarah:

| Kelompok | Hasil |
|---|---|
| P0.1 + P0.2 + P0.3 + governance + P1.1 | **28 passed, 128 assertions** |
| P1.1 sendiri | **8 passed, 32 assertions** |

Tidak dijalankan:

- full suite setelah P1.1;
- test UI kredensial/paramedik karena UI belum dibuat pada fase ini.

---

## 10. Batasan dan Lanjutan

Belum termasuk P1.1:

1. **UI manajemen kredensial** — tambah/ubah/verifikasi STR, SIP, SIPP.
2. **Unggah dokumen kredensial** — kolom `document_path` sudah ada; alur
   unggah privat dan signed URL belum dibuat.
3. **UI pengecualian kredensial darurat** — tabel dan model siap; route
   `POST /doctor/medical-records/{id}/credential-exceptions` belum dibuat.
4. **UI paramedik dan otorisasi** — service siap; halaman admin belum ada.
5. **Peringatan kedaluwarsa otomatis** — scheduler notifikasi SIP/STR belum
   dibuat.
6. **Review SLA 10 hari kerja** — daftar kerja record `exception` belum
   memiliki halaman khusus.
7. **Grant database production** — trigger hanya lapisan database aplikasi;
   user aplikasi tetap harus dibatasi.

---

## 11. Kriteria Lulus P1.1

| Kriteria | Status |
|---|---|
| Finalisasi normal ditolak bila SIP/STR tidak valid | Lulus (test) |
| Snapshot kredensial tidak berubah setelah data master berubah | Lulus (test) |
| Emergency dan kredensial tercatat terpisah | Lulus (model + test) |
| Record legacy `missing`/`exception` masuk daftar review | Lulus: command daftar review + SLA; halaman UI belum dibuat |
| `active_key` unik dan riwayat kredensial | Lulus (test + unique index) |
| Admin tidak dapat memberi kewenangan klinis | Lulus (test) |
| Paramedik di luar SIPP/otorisasi ditolak | Lulus (test) |
| Snapshot per versi | Lulus (test + backfill) |
