# P0.1 Tenant Isolation Audit

Tanggal audit: 26 November 2026  
Basis kode: commit `a36bd0c`  
Database snapshot: `docs/p0/schema.sql`, `docs/p0/row_counts.json`

## 1. Tujuan dan batas audit

Audit ini memeriksa apakah data klinis yang sudah ada benar-benar terikat pada
klinik dan pemiliknya. Audit **tidak** mengubah kode, skema, route, atau UI.
Temuan di bawah adalah dasar untuk P0.2 dan P1, bukan perbaikan yang sudah
dikerjakan.

Yang diperiksa:

- rekam medis, revisi, lampiran, tanda vital, alergi;
- resep dan stok obat;
- booking, service request, dan hasil laboratorium;
- akses pemilik melalui API;
- akses admin dan dokter melalui portal web.

## 2. Ringkasan hasil

| Area | Status saat ini | Bukti |
|---|---|---|
| Admin rekam medis | Ter-scope klinik untuk tenant | `Admin/MedicalRecordController` memakai `where('clinic_id', ...)` pada index, show, edit, update, void, resume |
| Dokter rekam medis | Ter-scope dokter + klinik | `Doctor/DoctorMedicalRecordController` memakai `where('doctor_id')->where('clinic_id')` |
| API pemilik | Ter-scope kepemilikan hewan/booking | `Api/MedicalRecordController` memakai `pets()->pluck('id')` atau `bookings()->find()` |
| Obat dan resep | Ter-scope klinik rekam medis | `PrescriptionService::syncForRecord()` memakai `where('clinic_id', $record->clinic_id)` |
| Lampiran | Hanya lewat URL bertanda tangan | Route `signed`, bukan folder publik |
| Revisi | Terikat pada rekam medis induk | Tidak ada route publik langsung ke revisi |
| Service request | Ter-scope klinik | Admin memakai `clinic_id`; dokter memakai `doctor_id` + `clinic_id` |
| Hasil laboratorium | Belum punya `clinic_id` sendiri | Masih menempel pada `medical_records`; menjadi batas P2 |

**Kesimpulan:** isolasi tenant untuk alur yang sudah ada **lulus untuk aktor
utama**, tetapi masih bergantung pada setiap query mengingat `clinic_id`.
Belum ada global scope atau policy terpusat. Ini risiko regresi, bukan
kebocoran yang terbukti saat audit.

## 3. Bukti query per aktor

### 3.1 Admin

`app/Http/Controllers/Admin/MedicalRecordController.php`

- `index()` baris 125–126: `MedicalRecord::with(...)->when($clinicId, where clinic_id)`.
- `create()` baris 157–159: `Booking::where('id')->where('clinic_id', $clinicId)`.
- `show()` baris 416–423: `MedicalRecord::where('id')->where('clinic_id', $clinicId)`.
- `edit()` baris 436–438: idem.
- `update()` baris 458–460: idem.
- `destroy()` baris 678–680: idem.
- `resumePdf()` baris 754–759: idem.
- `downloadAttachment()` baris 809: lampiran dicari lewat `medical_record_id`
  dari URL bertanda tangan, bukan lewat ID lampiran saja.

Catatan: `currentClinicId()` bernilai `null` hanya untuk `super_admin`.
Middleware `AdminAuth` memaksa semua role tenant memiliki klinik aktif
(`app/Http/Middleware/AdminAuth.php` baris 51–55), sehingga pola
`when($clinicId, ...)` tidak menjadi unscoped untuk tenant.

### 3.2 Dokter

`app/Http/Controllers/Doctor/DoctorMedicalRecordController.php`

- `index()` baris 62–63: `where('doctor_id')->where('clinic_id')`.
- `create()` baris 85–87: booking harus milik dokter dan klinik tersebut.
- `store()` baris 175–178: booking dikunci ulang dengan `doctor_id` + `clinic_id`.
- `show()` baris 302–308: idem.
- `edit()` baris 317–323: idem.
- `update()` baris 341–344: idem.
- `resumePdf()` baris 494–500: idem.

`DoctorPortalController::doctor()` juga menolak akun dokter yang profil
praktiknya berbeda klinik dengan akun (`403`).

### 3.3 API pemilik

`app/Http/Controllers/Api/MedicalRecordController.php`

- `index()` baris 165–168: `whereIn('pet_id', $request->user()->pets()->pluck('id'))`.
- `getByPet()` baris 200–206: hewan diverifikasi milik pengguna lebih dulu.
- `show()` baris 236–241: idem.
- `getByBooking()` baris 262–275: booking diverifikasi milik pengguna lebih dulu.
- `createExtraPayment()` baris 305–308: rekam medis dicari lewat booking milik pengguna.
- `paymentStatus()` baris 395–396: idem.

Tidak ada endpoint API yang menerima `clinic_id` dari klien untuk memilih
klinik. Klinik selalu berasal dari booking/rekam medis.

## 4. Temuan yang harus diselesaikan di fase berikutnya

### T-01 — API pemilik mengirim semua status rekam medis

`Api/MedicalRecordController::index()` dan `getByPet()` tidak memfilter
`status`. Draf dan rekam medis yang belum final dapat muncul di aplikasi
pemilik bila dibuat melalui jalur yang tidak menetapkan status final.

**Dampak:** pemilik dapat melihat konten yang belum ditandatangani.
**Target:** P1.1/P3 — endpoint pemilik hanya mengirim `final`, `void`, dan
`archived`; draf tidak pernah dikirim.

### T-02 — Nomor STR/SIP dokter ikut terkirim ke pemilik

`serializeRecord()` baris 151–152 mengirim `str_number` dan `sip_number`.

**Dampak:** data registrasi profesional tidak diperlukan aplikasi pemilik dan
memperbesar permukaan data pribadi.
**Target:** P3 — serializer whitelist; STR/SIP hanya untuk admin/dokter yang
berwenang.

### T-03 — Model `pet` dikirim mentah

Baris 158 mengirim `$record->pet` tanpa whitelist.

**Dampak:** field internal hewan ikut terbawa bila model berubah.
**Target:** P3 — DTO eksplisit untuk `pet`.

### T-04 — Isolasi bergantung pada disiplin query

Sebagian controller memakai `when($clinicId, ...)`, bukan helper
`applyClinicScope()` yang fail-closed. Saat ini aman karena middleware, tetapi
route baru yang lupa memasang middleware dapat membuka data lintas klinik.

**Target:** P0.2/P1 — policy dan relasi encounter; semua query klinis lewat
satu service/scope.

### T-05 — `lab_results` belum punya `clinic_id`

Tabel `lab_results` masih datar dan menempel pada rekam medis. Isolasi
bergantung pada rekam medis induk.

**Target:** P2.1 — `lab_orders`, spesimen, dan hasil dengan `clinic_id`
eksplisit.

### T-06 — Rekam medis dapat dibuat tanpa hash konten

`MedicalRecord::create()` dari seeder/script tidak menghitung `content_hash`.
Hash baru dihitung saat `finalize()`. Data lama yang dibuat di luar controller
dapat memiliki hash `null`.

**Dampak:** verifikasi keaslian dokumen tidak lengkap untuk data non-final.
**Target:** P0.2 — backfill hash untuk rekam medis final lama; draf tidak perlu
hash sampai ditandatangani.

### T-07 — Tidak ada policy terpusat

Belum ada `MedicalRecordPolicy`, `EncounterPolicy`, atau policy klinis lain.
Otorisasi masih berupa pengecekan role di middleware dan query.

**Target:** P1.1 — policy per aktor, termasuk larangan admin memfinalkan
rekam medis setelah flag lifecycle aktif.

## 5. Aktor dan batas yang diuji

| Aktor | Boleh | Tidak boleh |
|---|---|---|
| `super_admin` | melihat semua klinik, tanpa menandatangani rekam medis | mengubah isi klinis tanpa kewenangan dokter |
| `clinic_admin` / `admin` | mengelola rekam medis kliniknya | melihat klinik lain; memfinalkan setelah flag aktif |
| `doctor` | rekam medis miliknya di kliniknya | rekam medis dokter/klinik lain |
| pemilik hewan | rekam medis hewannya | rekam medis hewan pengguna lain |
| publik | verifikasi dokumen dan unduh lampiran lewat URL bertanda tangan | membuka folder storage atau menebak ID |

## 6. Perintah bukti

```bash
cd PetHeal_Backend
php artisan test --filter=P01CharacterizationTest
```

Hasil yang diharapkan saat P0.1 ditutup:

- 5 test lulus;
- kontrak JSON pemilik sama dengan fixture;
- akses lintas pemilik, lintas klinik, dan lintas dokter menghasilkan 404;
- satu booking tetap satu rekam medis.

## 7. Keputusan P0.1

Audit ini **tidak** menemukan kebocoran lintas klinik yang dapat direproduksi
pada alur admin, dokter, atau API pemilik yang ada. Audit menemukan tujuh
risiko yang harus ditutup sebelum fitur klinis baru dinyalakan. Tidak ada
perubahan skema atau UI yang dilakukan pada tahap ini.
