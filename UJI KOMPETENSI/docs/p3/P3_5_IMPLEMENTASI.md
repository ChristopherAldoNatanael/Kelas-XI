# P3.5 — UI Portal Pemilik Android

Status: **SELESAI (UI + unduhan PDF + kontrak unit test)**  
Tanggal: 26 November 2026

## 1. Tujuan

P3.5 menghubungkan API portal pemilik P3.4 ke aplikasi Android:

1. Riwayat amendment dapat dilihat tanpa membocorkan isi internal.
2. Pemilik dapat mengunduh salinan PDF rekam medis final.
3. Pemilik dapat mengajukan dan memantau permintaan PDP.
4. Kontrak 36 key `MedicalRecord` lama tidak berubah.

## 2. Model Baru

Ditambahkan di `Models.kt`:

- `MedicalRecordAmendmentSummary`
- `MedicalRecordAmendmentsResponse`
- `MedicalRecordExportTicket`
- `MedicalRecordExportResponse`
- `PrivacyRequest`
- `PrivacyRequestsResponse`
- `PrivacyRequestResponse`
- `PrivacyRequestCreateRequest`

Model amendment **hanya** memetakan field whitelist:

- `id`
- `type`
- `status`
- `requested_at`
- `reviewed_at`
- `applied_at`
- `applied_version_no`

Field `reason`, `before`, `after`, `review_notes`, snapshot kredensial, dan
`signature_path` tidak memiliki properti di model, sehingga tidak dapat
ditampilkan atau tersimpan di aplikasi pemilik meskipun server keliru
mengirimnya.

Label UI:

- Amendment: Menunggu tinjauan, Disetujui, Sudah diterapkan, Ditolak,
  Dibatalkan.
- PDP: Salinan data, Koreksi data, Penghapusan data, Pembatasan pemrosesan,
  Portabilitas data, Keberatan.
- Status PDP: Diterima, Diverifikasi, Diproses, Selesai, Ditolak.

Status tak dikenal ditampilkan apa adanya, bukan dipaksa menjadi label palsu.

## 3. API Client dan Repository

`ApiService`:

- `GET medical-records/{id}/amendments`
- `GET medical-records/{id}/export?format=pdf`
- `GET medical-record-exports/{token}` → `ResponseBody`
- `GET privacy-requests`
- `POST privacy-requests`

`MedicalRecordRepository`:

- `getAmendments(recordId)`
- `createExport(recordId)`
- `downloadExport(token)`
- `getPrivacyRequests()`
- `createPrivacyRequest(type, description, clinicId)`

Unduhan mengembalikan `ByteArray`; token tidak disimpan ke cache preferensi.
Pesan 410 dipetakan menjadi pesan yang jujur: salinan sudah kedaluwarsa atau
sudah pernah diunduh.

## 4. ViewModel

`MedicalRecordsViewModel` mendapat:

- `portalState`
  - status loading amendment;
  - daftar amendment;
  - status loading ekspor;
  - bytes PDF;
  - pesan/error ekspor;
  - daftar dan status permintaan PDP;
  - status submit PDP.
- `loadAmendments(recordId)`
- `requestExport(recordId)` — membuat token lalu langsung mengunduh bytes.
- `consumeExportBytes()`
- `loadPrivacyRequests()`
- `submitPrivacyRequest(type, description, clinicId)`
- `clearPortalMessages()`

Bytes PDF dibersihkan dari state setelah disimpan/dibuka, sehingga tidak
tertinggal di memori Compose setelah aksi selesai.

## 5. UI

Bagian baru di detail rekam medis: **Dokumen & Privasi**.

- **Riwayat koreksi rekam medis**
  - Bottom sheet berisi ringkasan amendment.
  - Menampilkan jenis, status, tanggal, dan versi diterapkan.
  - Tidak ada isi before/after.
- **Unduh salinan PDF**
  - Menampilkan spinner saat membuat token + mengunduh.
  - Android 10+ menyimpan ke `Downloads/PetHeal/rekam-medis-{id}.pdf`.
  - Android 7–9 menulis ke cache privat lalu membuka PDF lewat FileProvider.
  - Tidak meminta izin storage lebar.
- **Status permintaan**
  - Bottom sheet berisi permintaan PDP milik sendiri.
  - Menandai permintaan yang melewati SLA.
  - Menampilkan alasan penolakan bila ada.
- **Ajukan permintaan**
  - Dialog memilih Salinan/Koreksi/Penghapusan.
  - Uraian wajib minimal 5 karakter.
  - `clinic_id` diambil dari rekam medis yang sedang dibuka.
  - Setelah sukses, daftar status dimuat ulang.

Toast dipakai untuk hasil simpan/unduh dan error. Tidak ada perubahan pada
payload detail rekam medis lama.

## 6. File Unduhan PDF

Utility baru `util/MedicalRecordPortalFiles.kt`:

- `saveOrOpenMedicalRecordPdf(context, bytes, recordId)`
- hasil:
  - `Saved(displayName)`
  - `Opened`
  - `Failed`

Aturan:

- Android 10+ → MediaStore Downloads, folder `PetHeal`.
- Android 7–9 → file cache privat + `ACTION_VIEW` lewat FileProvider.
- Tidak memakai storage permission.
- Gagal tulis/hapus MediaStore → `Failed`, tanpa meninggalkan baris pending.

## 7. Pengujian

```bash
cd PetHeal_Android
./gradlew testDebugUnitTest \
  --tests "com.christopheraldoo.petheal.OwnerPortalContractTest"
```

Hasil:

- **5 passed, 0 failures, 0 errors**
  - Parsing ringkasan amendment.
  - Field internal `reason`/`before`/`after`/`signature_path` tidak masuk model.
  - Parsing tiket ekspor PDF.
  - Parsing status dan alasan penolakan PDP.
  - Parsing respons pembuatan PDP.
  - Status tak dikenal tidak membuat crash.

Kompilasi penuh `compileDebugKotlin` dan `assembleDebug` berhasil. Peringatan
yang muncul adalah peringatan lama di file lain, bukan error P3.5.

**Catatan jujur:** aplikasi belum dijalankan di emulator/perangkat pada putaran
ini, dan full test suite Android tidak dijalankan ulang.

## 8. Batasan yang Masih Terbuka

- Belum ada uji UI Compose/instrumentation untuk dialog dan bottom sheet.
- Belum ada uji end-to-end Android ke server nyata pada putaran ini.
- PDF masih dibuka/disimpan manual; belum ada penampil PDF internal.
- Android 7–9 memerlukan aplikasi penampil PDF eksternal.
- Tidak ada unduhan ulang karena token memang sekali pakai.
- Tidak ada notifikasi ketika status PDP berubah.
- Tidak ada unggah dokumen pendukung PDP.
- Halaman admin PDP belum ada.
- `clinic_id` PDP mengikuti klinik rekam medis yang dibuka, bukan pilihan
  multi-klinik.
- Model tidak menampilkan isi amendment, sesuai keputusan whitelist.
- Full suite Android belum dijalankan ulang.

## 9. Keputusan Penting

- UI pemilik memakai model sempit; field internal tidak dipetakan.
- Token ekspor tidak disimpan permanen di perangkat.
- PDF disimpan ke folder aplikasi di Downloads, bukan ke root storage.
- Tidak meminta izin storage lebar.
- Status tak dikenal ditampilkan jujur.
- Tidak ada perubahan pada 36 key payload `MedicalRecord` lama.
