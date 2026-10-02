# PROJECT_CONTEXT.md — PetHeal Backend

## Stack
- Laravel 12, PHP 8.2+ (runtime 8.3.31), MySQL `pet_heal` (127.0.0.1:3306, root, password kosong)
- Queue: `database`. RateLimiter di `AppServiceProvider.php:38-68`: api 60/mnt, auth 8/mnt, admin-login 5/mnt, password-reset 3/mnt, uploads 10/mnt, payment 10/mnt, webhook 120/mnt, api-heavy 30/mnt.

## Env & Serve
- `.env`: `APP_URL=https://envious-reselect-darn.ngrok-free.dev`, `ASSET_URL` sama, `APP_ENV=local`, `APP_DEBUG=true`. `local.properties` Android HARUS URL sama + `/api/`.
- Serve: `php artisan serve --host=127.0.0.1 --port=8000` + `ngrok http 8000`. Web UI ngrok: `http://127.0.0.1:4040`.
- `AppServiceProvider.php:28-35`: force `https` jika `APP_ENV=production` ATAU (`APP_URL` mengandung `ngrok` + diawali `https://`). Konsekuensi: akses lokal via `http://127.0.0.1:8000` bisa redirect ke `https://...` — akses via ngrok untuk hasil benar.
- ABAIKAN `start-https.bat` / `ssl/ssl-router.php` (klaim HTTPS palsu, `php -S` tak support TLS). JANGAN buka `https://127.0.0.1:8000` (error `Unsupported SSL request`).
- Admin demo `admin@petheal.com` / `admin123`.

## Struktur Folder Penting
```
app/Models/ (14): User, Pet, Doctor, Booking, Service, MedicalRecord, DoctorReview,
  PaymentMethod, Notification, Vaccination, WeightRecord, DeviceToken, AuditLog, AppSetting
app/Http/Controllers/Api/: Auth, Booking, Pet, Doctor, Service, MedicalRecord, Payment,
  PaymentMethod, MidtransWebhook, Dashboard, Notification, DeviceToken, Vaccination, WeightRecord
app/Http/Controllers/Admin/: AdminAuth, Dashboard, Booking, Doctor, MedicalRecord,
  NotificationSettings, Payment, User, Service
app/Http/Middleware/: AdminAuth (alias admin.auth), ApiAuthenticate
routes/web.php (Blade admin), routes/api.php (prefix /api, JSON)
database/migrations/ (36 file, lihat DB_SCHEMA.md)
config/app.php: 'maintenance' = ['driver'=>'file','store'=>'database'] (ARRAY, bukan bool!)
```

## Route Ringkas
- Web publik: `GET /` (welcome; FIX: pakai `app()->isDownForMaintenance()`, JANGAN `config('app.maintenance')` karena array selalu truthy → redirect abadi ke maintenance), `GET /maintenance`, `GET /down.json`, `GET|POST /admin/login`, `GET|POST /admin/register` (TERBUKA — akan ditutup saat multi-klinik).
- Web proteksi `admin.auth` prefix `admin`: `POST /logout`, `GET /` dashboard, settings, notification-settings, audit-logs, resource users, bookings (+confirm/complete/cancel/send-reminder/export), resource doctors, medical-records (+export/create/store/show/edit/update/destroy), services (+template/import/resource), payments (+show/confirm/send-reminder/export).
- API publik: `POST auth/login|register|firebase-login|register-direct` (throttle auth), `POST auth/forgot-password|verify-reset-code|reset-password` (throttle password-reset), `GET /health`, `GET /payment-methods`, `GET /services|/services/{id}`, `POST /midtrans/webhook` (throttle webhook).
- API proteksi (`throttle:api` + `ApiAuthenticate`): `POST auth/logout`, `GET|PUT auth/profile`, `POST auth/profile/photo`, `DELETE auth/account`, `GET /dashboard`, device-token, notifications, pets (+with-photo/photo/weight/vaccinations), doctors (+slots?date=, reviews), bookings (index/store/show/destroy + upcoming/cancel/reschedule/medical-record), medical-records (index/show + pay/payment-status), payment (preflight/snap-token/transaction-status/sync-status/remaining/booking).

## Alur Auth
- Admin: form email+password → `User::where(email)->where(role,'admin')` + `Hash::check` → `Auth::login` + regenerate session (Blade). `AdminAuth` middleware tolak non-admin.
- API: email+password (`registerDirect`) atau Firebase `id_token` (`firebaseLogin`/`register`) → Sanctum token. Semua request bawa `Authorization: Bearer`. `role` default `user`. Nilai dikenal: `user`, `admin`, `doctor` (hanya helper `isDoctor()`, tanpa route khusus).

## Status Multi-Tenancy: BELUM ADA
- Tidak ada tabel `clinics`/`tenants`, tidak ada `clinic_id` di tabel manapun (satu-satunya `tenant_id` = `config/firebase.php` untuk Firebase Auth, tidak relevan).
- Katalog global: `GET /doctors`, `/services`, `/payment-methods` tanpa filter. Booking cegah double-book per `doctor_id+date+time` global.
- User-scoped via `user_id` (pets/bookings/medical/payments/notifications). Admin lihat SEMUA data tanpa filter.
