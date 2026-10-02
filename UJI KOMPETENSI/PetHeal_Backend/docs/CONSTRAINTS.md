# CONSTRAINTS.md — Batasan Keras (JANGAN dilanggar tanpa izin user)

1. **Model tenancy FINAL: shared DB + `clinic_id`.** DILARANG membuat DB-per-klinik, schema-per-klinik, atau koneksi dinamis per tenant. Alasan: gratis, migrasi ringan, cocok untuk uji kompetensi.
2. **Android FINAL: 1 APK dinamis.** DILARANG memakai build flavors / `applicationIdSuffix` / beda APK per klinik / ganti `applicationId`. Tenant di runtime via `X-Clinic-Slug` + `GET /public/clinics`. Launcher icon & `applicationId` tetap `com.christopheraldoo.petheal`.
3. **100% GRATIS.** DILARANG menambah layanan berbayar (Pusher paid, S3 paid, domain paid, ngrok paid, Play Console di luar $25 opsional). Tetap: MySQL lokal, ngrok Free, Firebase Spark, Midtrans Sandbox, logo di `storage/app/public`.
4. **Backward compatible.** DILARANG menghapus kolom/field/endpoint lama atau mengubah response breaking. Field baru = nullable/opsional → backfill → NOT NULL. Response lama + `clinic:{...}` tanpa hapus field.
5. **`start-https.bat` / `ssl/ssl-router.php` DIABAIKAN.** Jangan dijalankan, jangan dijadikan acuan HTTPS. HTTPS hanya via ngrok.
6. **Jangan akses `https://127.0.0.1:8000` / `https://localhost:8000`.** Itu penyebab `Unsupported SSL request`. Pakai `http://127.0.0.1:8000` lokal atau `https://<ngrok>/...` untuk HTTPS.
7. **`config('app.maintenance')` adalah ARRAY, bukan bool.** DILARANG memakai `if (config('app.maintenance'))`. Pakai `app()->isDownForMaintenance()`. (Pernah menyebabkan `/` redirect abadi ke `/maintenance`.)
8. **Keamanan tenant di-enforce di backend, bukan cuma hide UI.** `clinic_admin` tidak boleh lolos via `?clinic_id=` lain. Semua query admin/API wajib filter `clinic_id` + middleware `EnsureClinicAccess`.
9. **User terikat 1 klinik** (`users.clinic_id`). 1 akun = 1 klinik. Jika butuh lintas-klinik di masa depan, migrasi ke pivot `clinic_user` — JANGAN diam-diam mengubah ke pivot tanpa diskusi.
10. **File baru hanya bila perlu.** Utamakan edit file existing. Dokumentasi hanya di `docs/`. Jangan commit/push tanpa perintah eksplisit.
