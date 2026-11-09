# FASE 6 — Pendaftaran Klinik Mandiri (Online)

Tanggal: 2026-11-09

## Tujuan

Pemilik klinik baru dapat mendaftar sendiri dari halaman publik `/admin/register`
tanpa harus menghubungi super admin, tetapi klinik dan akun baru tetap hanya
dibuat setelah super admin menyetujui.

## Alur Final (satu jalur, tanpa duplikasi)

```
/admin/register
  ├─ Tab "Gabung klinik"          → pilih klinik existing (alur lama)
  └─ Tab "Daftarkan klinik baru"
        Step 1  Data klinik
                nama, slug (otomatis dari nama, bisa diedit), alamat,
                telepon, email opsional, warna utama, deskripsi opsional
        Step 2  Data pemilik
                nama lengkap, email (diverifikasi), WhatsApp/telepon,
                password + konfirmasi
        Kirim kode 6 digit ke email pemilik (berlaku 10 menit, throttle)
        Verifikasi kode
        Halaman status pada /admin/register:
        "Permintaan sedang ditinjau" (bukan halaman status terpisah)
              ↓
        Super admin approve di /admin/join-requests
              ↓
        Email "klinik disetujui" berisi tautan login
              ↓
        Login → dashboard klinik; logo diunggah di Pengaturan → Klinik
```

Tidak ada klinik, akun, atau file logo yang dibuat sebelum approval. Logo
sengaja diunggah setelah login agar tidak ada file orphan dari payload OTP.

## Keputusan Teknis

| Hal | Keputusan |
|---|---|
| Tabel | Extend `clinic_join_requests`, bukan tabel baru |
| Jenis | `type` = `join` (default) / `new_clinic` |
| `clinic_id` | Nullable + `nullOnDelete`; diisi saat approve `new_clinic` |
| Data klinik | Snapshot kolom `requested_*` |
| Password | `Hash::make`, tersimpan di `password_hash` (tidak pernah plaintext) |
| Slug | Auto dari nama di browser, editable sebelum submit, unik terhadap klinik + request pending |
| Approval | `super_admin` saja, transaksi + `lockForUpdate` |
| Scope query | Route super admin TIDAK memfilter `currentClinicId()` |
| Notifikasi | OTP email (existing) + `ClinicRegistrationApprovedMail` / `ClinicRegistrationRejectedMail` |
| Alur lama | Tidak berubah perilaku maupun tujuan redirect-nya |

## Guardrail

- Email pemilik tidak boleh sudah menjadi user atau punya request pending.
- Slug tidak boleh bentrok dengan klinik aktif maupun request `new_clinic` pending.
- Nama klinik + telepon yang sama ditolak (klinik existing atau request pending).
- Saat approve, slug dan email dicek ulang di dalam row lock; konflik menghasilkan
  pesan validasi yang jelas dan transaksi rollback.
- Approve kedua tidak mungkin (status `pending` + lock → 404).
- Reject menyimpan `review_note` opsional dan tidak membuat data apa pun.
- Semua form memakai `@csrf`, validasi server, dan escaping Blade.

## File yang Diubah / Ditambah

- `database/migrations/2026_11_09_000001_add_self_registration_to_clinic_join_requests_table.php`
- `app/Models/ClinicJoinRequest.php` — `type`, fillable snapshot, scope `newClinic()`
- `app/Http/Controllers/Admin/AdminAuthController.php` — dua mode registrasi,
  verifikasi, status, approve/reject, email keputusan
- `app/Mail/ClinicRegistrationApprovedMail.php`, `ClinicRegistrationRejectedMail.php`
- `resources/views/admin/auth/join-request.blade.php` — tab mode, field data klinik,
  status permintaan, slug otomatis
- `resources/views/admin/join-requests.blade.php` — filter tipe, detail snapshot,
  form tolak dengan alasan
- `resources/views/emails/clinic-registration-{approved,rejected}(-text).blade.php`
- `resources/lang/{id,en}/clinics.php`
- `tests/Feature/ClinicSelfRegistrationTest.php` — 14 test / 90 assertion

## Verifikasi

- `php artisan test` → **229 passed / 1389 assertions** (termasuk 14 test baru).
- `php artisan view:cache` → sukses.
- `npm run build` → sukses (`app-CVaOraGG.css`, `app-C-6D00GQ.js`).
- Migration dijalankan di `pet_heal` dan `pet_heal_test`.
- `GET /api/public/clinics` diuji memuat klinik yang baru disetujui.

## Sisa / Catatan

- Uji visual browser desktop 1440px dan mobile 375px belum dijalankan pada sesi
  ini karena tidak ada browser driver di environment.
- Semgrep/Snyk tidak tersedia di environment; pemeriksaan dilakukan lewat
  `php -l`, `view:cache`, dan suite feature test.
- Klinik yang disetujui langsung muncul di API publik, jadi aplikasi Android
  tidak perlu update APK.
