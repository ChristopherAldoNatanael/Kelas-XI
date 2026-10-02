# AGENTS.md — PetHeal Backend (Laravel 12)

> File ini DIBACA OTOMATIS tiap sesi agent. Patuhi tanpa kecuali.

## 1. Bahasa & Gaya Komunikasi
- Bahasa: **Indonesia** untuk penjelasan ke user. Istilah teknis tetap English (migration, middleware, tenant).
- Jawaban singkat, faktual, tanpa pujian berlebihan. Jangan tebak — baca file dulu via `read`/`grep`/`glob`.
- Setiap menyebut fungsi/kode, sertakan `path:line` (cth `routes/web.php:32`).

## 2. Stack & Cara Jalan (JANGAN diubah seenaknya)
- Laravel 12, PHP ^8.2 (runtime 8.3.31), MySQL `pet_heal` @127.0.0.1:3306 root/(kosong), Sanctum 4, Spatie Permission 6.
- Serve BENAR: `php artisan serve --host=127.0.0.1 --port=8000` (HTTP murni) + `ngrok http 8000` → `https://envious-reselect-darn.ngrok-free.dev`.
- LARANGAN KERAS: jangan akses `https://127.0.0.1:8000` (menyebabkan `Invalid request (Unsupported SSL request)`). Jangan pakai `start-https.bat` / `ssl/ssl-router.php` — `php -S` tidak support TLS, file itu menyesatkan, ABAIKAN.
- `.env` aktif: `APP_URL=https://envious-reselect-darn.ngrok-free.dev`, `ASSET_URL` sama. `AppServiceProvider.php:33` force `https` jika APP_URL mengandung `ngrok`. Jadi redirect lokal bisa jadi `https://...` — itu expected, bukan bug, selama diakses via ngrok.
- Admin demo: `admin@petheal.com` / `admin123`. URL: lokal `http://127.0.0.1:8000/admin/login`, ngrok `https://envious-reselect-darn.ngrok-free.dev/admin/login`.

## 3. Gaya Kode
- Ikuti gaya Laravel 12 existing: controller di `app/Http/Controllers/Api/*` dan `Admin/*`, FormRequest bila validasi panjang, Resource secukupnya.
- Migration: selalu sertakan `down()`, nama deskriptif `xxxx_xx_xx_xxxx_add_clinic_id_to_X_table.php`, tambah index untuk kolom filter (`clinic_id`, `status`, `booking_date`).
- Jangan hapus field/kolom/endpoint lama (backward compat). Field baru = nullable/opsional dulu, backfill, baru NOT NULL.
- Validasi: slug `lowercase-hyphen unique`, warna `^#[0-9A-Fa-f]{6}$`, upload logo max 2MB (png/jpg/webp) via Intervention Image seperti photo dokter/pet.

## 4. Arsitektur Multi-Klinik (keputusan final, JANGAN diganti tanpa izin user)
- Model: **shared DB + `clinic_id`** (BUKAN DB-per-klinik). Lihat `docs/CONSTRAINTS.md` + `docs/TENANCY_RULES.md`.
- User TERIKAT 1 klinik (`users.clinic_id`). 1 akun = 1 klinik.
- Android = **1 APK dinamis** (BUKAN flavors / beda APK). Tenant via header `X-Clinic-Slug` + `GET /public/clinics`.
- 100% GRATIS: tanpa service berbayar baru. Tetap MySQL lokal, ngrok Free, Firebase Spark, Midtrans Sandbox.

## 5. Perintah Standar
```powershell
# dari folder PetHeal_Backend
php artisan config:clear; php artisan cache:clear
php artisan migrate --force
php artisan route:list --path=api
php artisan route:list --path=admin
curl.exe -s --max-time 15 "https://envious-reselect-darn.ngrok-free.dev/api/health"
curl.exe -s --max-time 10 http://127.0.0.1:8000/admin/login -o NUL -w "%{http_code}\n"
```
- Jangan `commit/push/PR` kecuali diminta eksplisit. Jangan ubah git config, jangan force-push.
- Jangan buat file baru kecuali perlu; utamakan edit file existing. Jangan buat `*.md` dokumentasi baru di luar `docs/` kecuali diminta.

## 6. Kapan Harus Bertanya (STOP, jangan tebak)
- Tanya jika: (a) mau ganti model tenancy, (b) mau hapus kolom/endpoint lama, (c) mau tambah dependency berbayar, (d) slug/role/SDK berubah, (e) request user ambigu antara 2+ interpretasi.
- Gunakan tool `question` dengan opsi + rekomendasi jelas.

## 7. Verifikasi Wajib Setelah Edit
- Setelah edit backend: `php artisan config:clear` + curl lokal + curl ngrok + `route:list` relevan.
- Setelah edit route `/`: pastikan `/` = 200 (pernah bug `config('app.maintenance')` array-truthy → sekarang `app()->isDownForMaintenance()`, lihat `routes/web.php:32`).
- Update `docs/PROGRESS.md` (apa diubah, kenapa, apa tersisa) setiap selesai fase. Lihat `docs/PLAN.md` untuk posisi fase.
