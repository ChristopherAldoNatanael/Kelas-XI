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
app/Models/ (17): User, Pet, Doctor, Booking, Service, MedicalRecord, DoctorReview,
  PaymentMethod, Notification, Vaccination, WeightRecord, DeviceToken, AuditLog,
  AppSetting, Clinic, ClinicJoinRequest (+ HasPhotoUrl trait)
app/Http/Controllers/Api/: Auth, Booking, Pet, Doctor, Service, MedicalRecord, Payment,
  PaymentMethod, MidtransWebhook, Dashboard, Notification, DeviceToken, Vaccination,
  WeightRecord, Service, PublicClinic, Health
app/Http/Controllers/Admin/: AdminAuth, Dashboard, Booking, Doctor, MedicalRecord,
  NotificationSettings, Payment, User, Service, Clinic
app/Http/Middleware/: AdminAuth (alias admin.auth), ApiAuthenticate,
  EnsureSuperAdmin (alias super_admin), SetLocale, CacheStaticAssets
app/helpers.php: currentClinic/currentClinicId (request-cache) + isSuperAdmin,
  tenantClinicId, requireTenantClinicId, applyClinicScope (fail-closed, Phase 1)
routes/web.php (Blade admin), routes/api.php (prefix /api, JSON, 64 routes)
database/migrations/ (45 file incl. 2026_10_02_* multi-clinic + 2026_10_03_000001 clinic indexes, lihat DB_SCHEMA.md)
```

## Route Ringkas
- Web publik: `GET /` (welcome; FIX: pakai `app()->isDownForMaintenance()`, JANGAN `config('app.maintenance')` karena array selalu truthy → redirect abadi ke maintenance), `GET /maintenance`, `GET /down.json`, `GET|POST /admin/login`, `GET|POST /admin/register` (TERBUKA — akan ditutup saat multi-klinik).
- Web proteksi `admin.auth` prefix `admin`: `POST /logout`, `GET /` dashboard, settings, notification-settings, audit-logs, resource users, bookings (+confirm/complete/cancel/send-reminder/export), resource doctors, medical-records (+export/create/store/show/edit/update/destroy), services (+template/import/resource), payments (+show/confirm/send-reminder/export).
- API publik: `POST auth/login|register|firebase-login|register-direct` (throttle auth), `POST auth/forgot-password|verify-reset-code|reset-password` (throttle password-reset), `GET /health`, `GET /payment-methods`, `GET /services|/services/{id}`, `POST /midtrans/webhook` (throttle webhook).
- API proteksi (`throttle:api` + `ApiAuthenticate`): `POST auth/logout`, `GET|PUT auth/profile`, `POST auth/profile/photo`, `DELETE auth/account`, `GET /dashboard`, device-token, notifications, pets (+with-photo/photo/weight/vaccinations), doctors (+slots?date=, reviews), bookings (index/store/show/destroy + upcoming/cancel/reschedule/medical-record), medical-records (index/show + pay/payment-status), payment (preflight/snap-token/transaction-status/sync-status/remaining/booking).

## Alur Auth
- Admin: form email+password → `User::where(email)->where(role,'admin')` + `Hash::check` → `Auth::login` + regenerate session (Blade). `AdminAuth` middleware tolak non-admin.
- API: email+password (`registerDirect`) atau Firebase `id_token` (`firebaseLogin`/`register`) → Sanctum token. Semua request bawa `Authorization: Bearer`. `role` default `user`. Nilai dikenal: `user`, `admin`, `doctor` (hanya helper `isDoctor()`, tanpa route khusus).

## Status Multi-Tenancy: ADA (shared DB + clinic_id, Fase 1–4 + Phase 1 hardening)

- Tabel `clinics` + `clinic_id` di `users/doctors/services/bookings` (NOT NULL pasca
  backfill `petheal-pusat`), `medical_records/payment_events/audit_logs/notifications`
  (nullable, nullOnDelete), `users.clinic_id` nullable (untuk super_admin).
  `tenant_id` di `config/firebase.php` = Firebase Auth, tidak relevan.
- Katalog terfilter: `GET /doctors` via klinik user; `GET /services` via klinik user
  ATAU anonim + `?clinic_slug=` eksplisit (tanpa slug → 422). `GET /payment-methods` global.
- Booking cegah double-book per `clinic_id+doctor_id+date+time` (lockForUpdate).
- User-scoped via `user_id` (pets/bookings/medical/payments/notifications) + clinic check
  (Phase 1: null-clinic tenant → 403; cross-clinic ID → 404/422 validasi tenant-aware).
- Admin: `clinic_id` scope via `currentClinicId()`; gate `AdminAuth` tolak tenant tanpa
  klinik; super_admin overview + `POST /admin/switch-clinic`.
- API contract FROZEN (Phase 2, 2026-10-03): envelope `{success,message?,data?}`,
  koleksi `{success,data[],pagination}`, error `{success:false,message,errors?}`,
  401 selalu `Unauthenticated.`. Pengecualian frozen: webhook, raw transaction-status,
  nested weight/vaccination, `data:null` medical-record kosong. Lihat `API_CONTRACT.md`
  + `API_DOCUMENTATION.md`.
- Contract tests: `tests/Feature/Phase1TenantIsolationTest.php` (20 test) +
  `tests/Feature/Phase2ApiContractTest.php` (19 test) +
  `tests/Feature/Phase3StabilizationTest.php` (15 test) +
  `tests/Feature/Phase4WebAdminTest.php` (15 test) +
  `tests/Feature/Phase5SuperAdminTest.php` (11 test) +
  `tests/Feature/Phase6IntegrationWorkflowsTest.php` (9 test) +
  `tests/Feature/Phase6SecurityMatrixTest.php` (7 test). Lihat `docs/PROGRESS.md`.
- Full integration (Phase 6, 2026-10-03): 97/97 hijau; workflows lintas modul
  + matriks negatif + fresh-chain valid; nol perubahan produk. Siap Phase 7.
- Web clinic admin (Phase 4, 2026-10-03): 12 modul terinventarisasi; sidebar
  sesuai role; warning toast + error banner; export hormati filter; remember-me
  aktif; 3 view orphan dihapus. Bilingual: mekanisme `?lang=` + `lang/{id,en}`
  berjalan; dashboard/CRUD masih hardcoded campur (debt i18n khusus).
- Web super admin (Phase 5, 2026-10-03): 12 route terkunci; approve atomik;
  kredensial acak; switch = session-only; oversight inactive = keputusan produk;
  clinics/* bilingual. Boundary Admin↔Super↔Tenant terverifikasi.
- Backend stabilization (Phase 3, 2026-10-03): monotonic payment status, atomic
  admin writes (lock/transaction), scoped admin exists, inactive-clinic gates,
  sanitized API 500s, service/doctor cache versioning. Kontrak Phase 2 intact
  (aditif saja). Detail: `docs/PROGRESS.md`.
