# P4.4 — Dashboard Admin Permintaan PDP

Status: **SELESAI**  
Tanggal: 26 November 2026  
Ruang lingkup: web admin (Blade), controller, route, test, navigasi.

## 1. Tujuan

Menutup celah operasional P3.3/P3.4: permintaan PDP sudah dapat dibuat dari
Android dan diproses lewat API/service, tetapi admin belum punya layar web
untuk melihat, memverifikasi, memproses, menyelesaikan, atau menolak.

## 2. Route Baru

Semua berada di bawah middleware `admin.auth` dan prefix `admin`:

| Method | Path | Name |
|---|---|---|
| GET | `/admin/privacy-requests` | `admin.privacy-requests.index` |
| GET | `/admin/privacy-requests/{id}` | `admin.privacy-requests.show` |
| POST | `/admin/privacy-requests/{id}/verify` | `admin.privacy-requests.verify` |
| POST | `/admin/privacy-requests/{id}/start` | `admin.privacy-requests.start` |
| POST | `/admin/privacy-requests/{id}/complete` | `admin.privacy-requests.complete` |
| POST | `/admin/privacy-requests/{id}/reject` | `admin.privacy-requests.reject` |

## 3. Controller

`App\Http\Controllers\Admin\PrivacyRequestController`

- Daftar dengan filter status, jenis, dan keterlambatan.
- Statistik: total, terbuka, lewat SLA, selesai.
- Detail permintaan + pemohon + klinik + penanggung jawab.
- Aksi memakai `PrivacyRequestService`, bukan menulis status langsung.
- `MedicalRecordLifecycleException` ditangkap dan dikembalikan sebagai
  flash `error`, bukan 500.
- Scope klinik:
  - admin klinik hanya melihat `clinic_id` kliniknya atau `NULL`;
  - permintaan klinik lain → **404**;
  - super admin tanpa klinik melihat semua.
- `response_path` divalidasi `nullable|string|max:255`.
- `rejection_reason` wajib, maksimal 20.000 karakter.

## 4. UI

### 4.1 Daftar

- kartu statistik;
- filter status/jenis/overdue;
- tabel: ID, pemohon, jenis, status, diajukan, tenggat, penanggung jawab;
- badge status dan badge “Lewat SLA”;
- tenggat lewat ditampilkan merah;
- pagination 20 per halaman;
- empty state.

### 4.2 Detail

- uraian permintaan dengan `whitespace-pre-line`;
- ringkasan status, pemohon, klinik, tanggal, penanggung jawab;
- tautan ke halaman pengguna admin;
- peringatan SLA;
- panel khusus permintaan `erasure`:
  - memanggil `PrivacyRequestService::erasureBlockReason()`;
  - menjelaskan retensi/legal hold;
  - mengingatkan untuk menolak dengan alasan hukum, bukan menghapus rekam
    medis final;
- form tindakan sesuai status:
  - `received` → verifikasi;
  - `verified` → mulai proses;
  - `verified`/`in_progress` → selesai + `response_path`;
  - semua status terbuka → tolak + alasan;
- konfirmasi sebelum selesai/tolak;
- flash sukses/gagal.

### 4.3 Navigasi

Sidebar admin mendapat tautan **“Permintaan PDP”** dengan ikon
`shield_person`, tepat setelah Rekam Medis, dan status aktif mengikuti
`admin.privacy-requests.*`.

## 5. Keamanan dan Audit

- CSRF aktif pada semua form.
- Hanya admin (middleware `admin.auth`).
- Service tetap memeriksa:
  - aktor admin;
  - kecocokan klinik;
  - transisi status yang sah;
  - alasan penolakan wajib.
- Setiap transisi menulis `audit_logs`:
  - `privacy_request.verified`;
  - `privacy_request.in_progress`;
  - `privacy_request.completed`;
  - `privacy_request.rejected`.
- Admin tidak dapat menghapus rekam medis melalui layar ini.
- Tidak ada data klinis yang ditampilkan di daftar.

## 6. Pengujian

```bash
DB_DATABASE=pet_heal_test php artisan test \
  tests/Feature/P44AdminPrivacyDashboardTest.php
```

Hasil: **3 passed, 25 assertions**.

Cakupan:

1. Admin melihat daftar dan detail permintaan kliniknya.
2. Alur `received → verified → in_progress → completed` berhasil.
3. `response_path` dan `handled_by` tersimpan.
4. Audit `privacy_request.completed` tercatat.
5. Penolakan tanpa alasan ditolak validasi.
6. Penolakan dengan alasan menyimpan alasan.
7. Permintaan klinik lain menghasilkan 404.

`php artisan view:cache` sukses. `php -l` bersih.

**Catatan jujur:** full test suite dijalankan terpisah setelah perubahan ini
dan hasilnya dicatat pada dokumen kesiapan produksi. Uji browser manual dengan
data nyata belum dilakukan.

## 7. Batasan

- Unggah/generate berkas jawaban ditambahkan pada P4.6.
- Belum ada notifikasi ke pemohon saat status berubah.
- Belum ada dashboard SLA time-series.
- Belum ada bulk action.
- Belum ada ekspor daftar PDP.
- Belum ada tanda tangan digital pada jawaban PDP.
- Permintaan tanpa klinik terlihat oleh semua admin klinik; keputusan akhir
  tetap di audit log.
- Belum ada pembatasan rate khusus untuk halaman admin PDP.
- Belum ada UI serupa di portal dokter.
- Uji aksesibilitas formal dan uji browser lintas perangkat belum dilakukan.

## 8. Keputusan Penting

- Aksi status tidak diduplikasi di controller; tetap lewat service.
- Admin klinik tidak dapat membuka permintaan klinik lain.
- Permintaan `erasure` diberi penjelasan retensi/legal hold sebelum ditolak.
- Tidak ada tombol hapus rekam medis di layar PDP.
- UI memakai komponen admin yang sudah ada agar konsisten.
